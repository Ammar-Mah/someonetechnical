<?php

/**
 * Unified logging for the framework.
 *
 * Design goals, in order:
 *
 *  1. Free when off. Every entry point starts with a single static boolean
 *     check. Nothing is formatted, nothing is buffered, no shutdown handler is
 *     registered, no file is touched. Toggle with LOG_ENABLED in runtime.php.
 *
 *  2. Cheap when on. Entries accumulate in memory and are written with ONE
 *     append at shutdown, instead of one file_put_contents per line. There is
 *     no debug_backtrace() on the hot path — the previous Logger called it on
 *     every single write, which dominated its cost. Callers that would have to
 *     build an expensive context array can pass a closure instead; it is only
 *     invoked if the entry actually passes the level and channel filters.
 *
 *  3. Enough information to be useful. Every line carries a request id, so the
 *     nine parallel requests the UI fires to open one project can be told
 *     apart. With LOG_METRICS on, each request also emits a summary: how long
 *     boot took versus the handler, how many queries ran and how long they
 *     took, how many bytes went back, and peak memory.
 *
 * Output is JSON Lines — one self-contained JSON object per line, so the file
 * stays greppable by eye but is also trivially machine-readable.
 *
 * Files are written behind a "<?php exit;" guard and named .log.php, so a
 * direct HTTP request returns an empty body. That matters here because there
 * is no .htaccess in this project and logs/ sits inside the document root.
 */
final class Log
{
    public const DEBUG = 10;
    public const INFO  = 20;
    public const WARN  = 30;
    public const ERROR = 40;

    private const NAMES = [self::DEBUG => 'debug', self::INFO => 'info', self::WARN => 'warn', self::ERROR => 'error'];
    private const LEVELS = ['debug' => self::DEBUG, 'info' => self::INFO, 'warn' => self::WARN, 'error' => self::ERROR];

    private const GUARD = "<?php exit; ?>\n";

    /** Hard cap so a runaway loop cannot exhaust memory. */
    private const MAX_BUFFER = 2000;

    private static bool $on = false;
    private static bool $booted = false;
    private static int $min = self::INFO;
    private static array $channels = [];
    private static bool $metrics = false;
    private static int $slowQueryMs = 0;
    private static int $maxFileKb = 4096;

    private static array $buffer = [];
    private static int $dropped = 0;
    private static bool $summarised = false;
    private static bool $fatalLogged = false;

    private static string $rid = '';
    private static float $t0 = 0.0;
    private static array $marks = [];
    private static array $counters = [];
    private static array $fields = [];

    // ---------------------------------------------------------------- boot

    /**
     * Read configuration and arm the logger. Safe to call more than once.
     * Called from initialize.inc.php immediately after the runtime constants
     * are defined, so that everything after it can log.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        self::$on = defined('LOG_ENABLED') && LOG_ENABLED;
        if (!self::$on) {
            return; // nothing else costs anything
        }

        $level = defined('LOG_LEVEL') ? strtolower((string)LOG_LEVEL) : 'info';
        self::$min = self::LEVELS[$level] ?? self::INFO;

        self::$channels   = (defined('LOG_CHANNELS') && is_array(LOG_CHANNELS)) ? array_flip(LOG_CHANNELS) : [];
        self::$metrics    = !defined('LOG_METRICS') || LOG_METRICS;
        self::$slowQueryMs = defined('LOG_SLOW_QUERY_MS') ? (int)LOG_SLOW_QUERY_MS : 0;
        self::$maxFileKb  = defined('LOG_MAX_FILE_KB') ? (int)LOG_MAX_FILE_KB : 4096;

        self::$t0 = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        try {
            self::$rid = bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            self::$rid = substr(md5((string)mt_rand()), 0, 8);
        }

        register_shutdown_function([self::class, 'flush']);
    }

    public static function enabled(): bool
    {
        return self::$on;
    }

    /** True only if an entry at this level/channel would actually be recorded. */
    public static function wants(int $level, string $channel = ''): bool
    {
        if (!self::$on || $level < self::$min) {
            return false;
        }
        return self::$channels === [] || $channel === '' || isset(self::$channels[$channel]);
    }

    public static function requestId(): string
    {
        return self::$rid;
    }

    // ------------------------------------------------------------- writing

    /**
     * @param array|callable|null $context array, or a closure returning one.
     *        The closure is only called when the entry passes the filters.
     */
    public static function write(int $level, string $channel, string $message, array|callable|null $context = null): void
    {
        if (!self::$on || $level < self::$min) {
            return;
        }
        if (self::$channels !== [] && !isset(self::$channels[$channel])) {
            return;
        }
        if (count(self::$buffer) >= self::MAX_BUFFER) {
            self::$dropped++;
            return;
        }

        if (is_callable($context)) {
            try {
                $context = $context();
            } catch (Throwable $e) {
                $context = ['context_error' => $e->getMessage()];
            }
        }

        $entry = [
            'ts'  => date('Y-m-d H:i:s'),
            'ms'  => round((microtime(true) - self::$t0) * 1000, 1),
            'rid' => self::$rid,
            'lvl' => self::NAMES[$level] ?? 'info',
            'ch'  => $channel,
            'msg' => $message,
        ];
        if (is_array($context) && $context !== []) {
            $entry['ctx'] = $context;
        }

        self::$buffer[] = $entry;
    }

    public static function debug(string $channel, string $message, array|callable|null $context = null): void
    {
        self::write(self::DEBUG, $channel, $message, $context);
    }

    public static function info(string $channel, string $message, array|callable|null $context = null): void
    {
        self::write(self::INFO, $channel, $message, $context);
    }

    public static function warn(string $channel, string $message, array|callable|null $context = null): void
    {
        self::write(self::WARN, $channel, $message, $context);
    }

    public static function error(string $channel, string $message, array|callable|null $context = null): void
    {
        self::write(self::ERROR, $channel, $message, $context);
    }

    /** Convenience for Throwables — records class, message, origin and a trimmed trace. */
    public static function exception(string $channel, Throwable $e, array $extra = []): void
    {
        if (!self::$on || self::ERROR < self::$min) {
            return;
        }
        self::write(self::ERROR, $channel, get_class($e) . ': ' . $e->getMessage(), array_merge([
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
        ], $extra));
    }

    // ------------------------------------------------------------- metrics

    /** Timing checkpoint, in milliseconds since the request began. */
    public static function mark(string $name): void
    {
        if (!self::$on) return;
        self::$marks[$name] = round((microtime(true) - self::$t0) * 1000, 1);
    }

    /** Increment a numeric counter reported in the request summary. */
    public static function count(string $key, int|float $by = 1): void
    {
        if (!self::$on) return;
        self::$counters[$key] = (self::$counters[$key] ?? 0) + $by;
    }

    /** Attach an arbitrary field to the request summary. */
    public static function set(string $key, mixed $value): void
    {
        if (!self::$on) return;
        self::$fields[$key] = $value;
    }

    /**
     * Record a database query. Called from Database::query().
     * Always counts; only writes a line when the query is slower than
     * LOG_SLOW_QUERY_MS, or when the db channel is at debug level.
     */
    public static function query(string $sql, float $ms, int $rows = -1): void
    {
        if (!self::$on) return;

        self::$counters['queries'] = (self::$counters['queries'] ?? 0) + 1;
        self::$counters['ms_db'] = round((self::$counters['ms_db'] ?? 0) + $ms, 1);

        $slow = self::$slowQueryMs > 0 && $ms >= self::$slowQueryMs;
        if (!$slow && !self::wants(self::DEBUG, 'db')) {
            return;
        }

        self::write($slow ? self::WARN : self::DEBUG, 'db', $slow ? 'slow query' : 'query', [
            'ms'   => round($ms, 1),
            'rows' => $rows < 0 ? null : $rows,
            'sql'  => self::condense($sql),
        ]);
    }

    private static function condense(string $sql): string
    {
        $sql = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
        return strlen($sql) > 400 ? substr($sql, 0, 400) . ' …' : $sql;
    }

    /**
     * Round every float in an entry to a whole number before it is encoded.
     *
     * Not cosmetic — it is a size fix. json_encode prints a float using
     * serialize_precision, and on a server where that is 17 rather than -1 a
     * perfectly ordinary `round(22.4, 1)` comes out as
     *
     *   "ms":22.39999999999999857891452847979962825775146484375
     *
     * That is 51 characters where 2 would do, on several fields of every line, and
     * it is what was inflating these files. Rounding at the source is not enough
     * because a float is never exactly 22.4 in binary; the only values that
     * serialise short whatever the ini says are integers.
     *
     * Whole milliseconds are ample for what these lines are for. Applied centrally
     * so a caller's float cannot reintroduce the problem — if something ever needs a
     * fraction, it should log it as a preformatted string on purpose.
     */
    private static function shortenFloats(array $entry): array
    {
        foreach ($entry as $key => $value) {
            if (is_float($value)) {
                $entry[$key] = is_finite($value) ? (int)round($value) : 0;
            } elseif (is_array($value)) {
                $entry[$key] = self::shortenFloats($value);
            }
        }
        return $entry;
    }

    // --------------------------------------------------------------- flush

    /**
     * Write everything buffered, plus the request summary. Registered as a
     * shutdown function; safe to call directly and safe to call twice.
     */
    public static function flush(): void
    {
        if (!self::$on) {
            return;
        }

        // Capture a fatal here rather than relying on some other shutdown
        // function running first. This also has to happen before append()
        // touches the filesystem: any diagnostic raised down there — even a
        // suppressed one — replaces what error_get_last() reports, which would
        // hide the fatal from every handler that runs after us.
        self::captureFatal();

        // flush() can legitimately run more than once — the fatal-error shutdown
        // handler flushes explicitly so the fatal is not lost — but the request
        // summary must only be emitted once.
        if (self::$metrics && !self::$summarised) {
            self::$summarised = true;
            self::$buffer[] = self::summary();
        }
        if (self::$dropped > 0) {
            self::$buffer[] = [
                'ts' => date('Y-m-d H:i:s'), 'ms' => 0.0, 'rid' => self::$rid,
                'lvl' => 'warn', 'ch' => 'log',
                'msg' => 'buffer full, entries dropped',
                'ctx' => ['dropped' => self::$dropped, 'cap' => self::MAX_BUFFER],
            ];
        }

        if (self::$buffer === []) {
            return;
        }

        $lines = '';
        foreach (self::$buffer as $entry) {
            $json = json_encode(
                self::shortenFloats($entry),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
            if ($json !== false) {
                $lines .= $json . "\n";
            }
        }

        // Reset before writing so a failure cannot cause a double write if
        // flush() runs again.
        self::$buffer = [];
        self::$dropped = 0;

        self::append($lines);
    }

    /**
     * Record the fatal that ended the request, if there is one and it has not
     * been recorded already. Idempotent, so the framework's own fatal handler
     * can also call Log::error('fatal', …) without producing a duplicate.
     */
    public static function captureFatal(): void
    {
        if (!self::$on || self::$fatalLogged) {
            return;
        }
        $e = error_get_last();
        if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        self::$fatalLogged = true;
        self::write(self::ERROR, 'fatal', (string)($e['message'] ?? 'fatal error'), [
            'file' => $e['file'] ?? null,
            'line' => $e['line'] ?? null,
            'type' => $e['type'],
        ]);
    }

    private static function summary(): array
    {
        $totalMs = round((microtime(true) - self::$t0) * 1000, 1);

        $ctx = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri'    => strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '-',
            'status' => function_exists('http_response_code') ? (http_response_code() ?: 200) : 200,
            'ms'     => $totalMs,
            'mem_kb' => (int)round(memory_get_peak_usage(true) / 1024),
        ];

        // Read the session at summary time rather than at boot: the user may
        // only be established later in the request (the dev login bypass and
        // the OAuth callback both do this).
        if (!isset(self::$fields['user']) && isset($_SESSION)) {
            $u = $_SESSION['user'] ?? $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
            if ($u !== null) $ctx['user'] = $u;
        }

        // Derive the boot/handler split from marks when they are available:
        // the difference is what the audit needs in order to show that the
        // per-request bootstrap cost actually came down.
        if (isset(self::$marks['boot_done'])) {
            $ctx['ms_boot'] = self::$marks['boot_done'];
            $ctx['ms_work'] = round($totalMs - self::$marks['boot_done'], 1);
        }
        if (self::$marks !== []) {
            $ctx['marks'] = self::$marks;
        }

        // Always present, so the summary lines can be sorted on them even for
        // requests that ran no queries or returned no body.
        $ctx['queries'] = self::$counters['queries'] ?? 0;
        $ctx['ms_db']   = self::$counters['ms_db'] ?? 0;
        $ctx['bytes']   = self::$counters['bytes'] ?? 0;

        foreach (self::$counters as $k => $v) {
            $ctx[$k] = $v;
        }
        foreach (self::$fields as $k => $v) {
            $ctx[$k] = $v;
        }

        return [
            'ts'  => date('Y-m-d H:i:s'),
            'ms'  => $totalMs,
            'rid' => self::$rid,
            'lvl' => 'info',
            'ch'  => 'request',
            'msg' => 'request complete',
            'ctx' => $ctx,
        ];
    }

    private static function dir(): string
    {
        return (defined('ROOT') ? ROOT : dirname(__DIR__, 3)) . DIRECTORY_SEPARATOR . 'logs';
    }

    /**
     * Append to today's file.
     *
     * Deliberately raises no diagnostics — not even suppressed ones. This runs
     * at shutdown, and anything it emits would overwrite error_get_last() and
     * so hide a fatal from handlers that run after it. Every filesystem call is
     * therefore guarded by a predicate rather than by "@".
     */
    private static function append(string $lines): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            clearstatcache(true, $dir);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return; // logging must never break the request
        }

        $day  = date('Y-m-d');
        $path = $dir . DIRECTORY_SEPARATOR . 'app-' . $day . '.log.php';

        $exists = is_file($path);
        $size = $exists ? (int)filesize($path) : 0;   // filesize() only when the file is there

        // Keep one rotated backup so a chatty debug channel cannot fill the disk.
        if ($exists && self::$maxFileKb > 0 && $size > self::$maxFileKb * 1024) {
            if (@rename($path, $dir . DIRECTORY_SEPARATOR . 'app-' . $day . '.1.log.php')) {
                $exists = false;
                $size = 0;
            }
        }

        if (!$exists || $size === 0) {
            $lines = self::GUARD . $lines;
        }

        @file_put_contents($path, $lines, FILE_APPEND | LOCK_EX);
    }
}
