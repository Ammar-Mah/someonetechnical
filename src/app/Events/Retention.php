<?php

/**
 * Old intake requests and old log files are deleted on a schedule (#78).
 *
 * The host has no queue worker and no cron job we control (ARCHITECTURE.md →
 * Constraints), so the schedule is a page load: public/index.php calls
 * sweepIfDue(), which does the work at most once a DAY. A Cache key remembers
 * the last run; a non-blocking lock on cache/retention.lock keeps two page
 * loads from sweeping together. The one that loses the lock simply renders.
 *
 * The periods are INTAKE_RETENTION_DAYS and LOG_RETENTION_DAYS, resolved in
 * runtime.php. The privacy notice prints them through period(), so it can
 * never state a period the sweep does not enforce.
 *
 * Not a Handler: nothing here is reachable from updater.php. Every method is
 * static, and the class is not a Component.
 */
class Retention
{
    /** Seconds between two sweeps. */
    public const INTERVAL = 86400;

    /** The Cache key holding the time of the last sweep. */
    public const DONE_KEY = 'retention.swept';

    /** A day's log file, and its rotated backup: app-2026-09-29.log.php, app-2026-09-29.1.log.php. */
    private const LOG_FILE = '/^app-(\d{4}-\d{2}-\d{2})(\.\d+)?\.log\.php$/';

    /**
     * Sweep, unless a sweep ran inside INTERVAL or another request is running
     * one now. A failed part is an error line, not a broken page.
     */
    public static function sweepIfDue(): void
    {
        if (Cache::get(self::DONE_KEY) !== null) {
            return;
        }

        $handle = @fopen((defined('ROOT') ? ROOT : dirname(__DIR__, 3)) . '/cache/retention.lock', 'c');
        if ($handle === false) {
            Log::warn('app', 'retention sweep skipped: lock unavailable');
            return;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return; // another request holds it, and is sweeping
        }

        // Each part is tried on its own, and the day is marked done either way:
        // a sweep that fails says so once a day, not on every page load.
        try {
            if (Cache::get(self::DONE_KEY) === null) {
                foreach (['requests', 'logs'] as $part) {
                    try {
                        self::$part();
                    } catch (Throwable $e) {
                        Log::exception('app', $e, ['job' => 'retention sweep', 'part' => $part]);
                    }
                }
                Cache::set(self::DONE_KEY, date('Y-m-d H:i:s'), self::INTERVAL);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Delete every intake request sent more than INTAKE_RETENTION_DAYS ago.
     *
     * One delete() per row rather than deleteAll(), so each row leaves its own
     * audit line; IntakeRequest has no soft delete, so each is gone for good.
     *
     * @return int[] the ids deleted
     */
    public static function requests(?int $now = null): array
    {
        $days   = (int)INTAKE_RETENTION_DAYS;
        $cutoff = date('Y-m-d H:i:s', ($now ?? time()) - $days * 86400);

        $ids = Model::transaction(function () use ($cutoff) {
            $ids = [];
            foreach (IntakeRequest::query()->where('created_at', '<', $cutoff)->get() as $request) {
                $ids[] = (int)$request->getKey();
                $request->delete();
            }
            return $ids;
        });

        Log::info('app', 'retention: intake requests deleted', [
            'removed' => count($ids),
            'ids'     => $ids,
            'days'    => $days,
            'cutoff'  => $cutoff,
        ]);

        return $ids;
    }

    /**
     * Delete every day's log file dated more than LOG_RETENTION_DAYS ago, by
     * the date in its name. Today's file is never old enough.
     *
     * @return string[] the file names deleted
     */
    public static function logs(?string $dir = null, ?int $now = null): array
    {
        $days   = (int)LOG_RETENTION_DAYS;
        $dir  ??= (defined('ROOT') ? ROOT : dirname(__DIR__, 3)) . '/logs';
        $cutoff = date('Y-m-d', ($now ?? time()) - $days * 86400);

        $removed = [];
        $failed  = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $name) {
            if (preg_match(self::LOG_FILE, $name, $m) !== 1 || $m[1] >= $cutoff) {
                continue;
            }
            if (@unlink($dir . '/' . $name)) {
                $removed[] = $name;
            } else {
                $failed[] = $name;
            }
        }

        if ($failed !== []) {
            Log::warn('app', 'retention: log files not deleted', ['files' => $failed]);
        }
        Log::info('app', 'retention: log files deleted', [
            'removed' => count($removed),
            'files'   => $removed,
            'days'    => $days,
            'cutoff'  => $cutoff,
        ]);

        return $removed;
    }

    /** A period as the privacy notice says it: "12 months", "2 years", "90 days", "1 day". */
    public static function period(int $days): string
    {
        if ($days === 365) return '12 months';
        if ($days > 365 && $days % 365 === 0) return ($days / 365) . ' years';

        return $days . ($days === 1 ? ' day' : ' days');
    }
}
