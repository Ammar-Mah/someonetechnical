<?php

/**
 * Minimal file-backed cache for the framework.
 *
 * No external dependencies, no namespacing. Entries are stored as JSON behind a
 * PHP guard prefix so that a direct HTTP request to a cache file returns nothing
 * even though the cache directory lives inside the document root.
 *
 * Every method degrades to "no cache" rather than throwing: if the directory is
 * not writable the callers still get correct (just uncached) data.
 */
class Cache
{
    /** Prefix written to every cache file so a direct web request yields an empty body. */
    private const GUARD = "<?php exit; ?>\n";

    /** In-process memo, so repeated reads in one request never touch disk twice. */
    private static array $memo = [];

    private static ?string $dir = null;
    private static ?bool $usable = null;

    private static function dir(): string
    {
        if (self::$dir === null) {
            self::$dir = (defined('ROOT') ? ROOT : dirname(__DIR__, 3)) . DIRECTORY_SEPARATOR . 'cache';
        }
        return self::$dir;
    }

    private static function usable(): bool
    {
        if (self::$usable !== null) {
            return self::$usable;
        }
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        self::$usable = is_dir($dir) && is_writable($dir);
        return self::$usable;
    }

    private static function path(string $key): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $key);
        return self::dir() . DIRECTORY_SEPARATOR . $safe . '.cache.php';
    }

    /**
     * @param mixed $default returned when the key is absent or expired
     * @return mixed
     */
    /** Marks a key that is known to be absent, so a miss is only paid once. */
    private const MISS = "\0__cache_absent__\0";

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            // A miss is memoised too. Without this, a key that is not there was
            // re-read from disk on every single get() in the request — and the
            // callers most likely to ask repeatedly are exactly the ones whose
            // key does not exist yet.
            return self::$memo[$key] === self::MISS ? $default : self::$memo[$key];
        }
        if (!self::usable()) {
            return $default;
        }

        $path = self::path($key);
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            self::$memo[$key] = self::MISS;
            return $default;
        }

        $json = substr($raw, strlen(self::GUARD));
        $entry = json_decode($json, true);
        if (!is_array($entry) || !array_key_exists('v', $entry)) {
            self::$memo[$key] = self::MISS;
            return $default;
        }

        $expires = (int)($entry['e'] ?? 0);
        if ($expires !== 0 && $expires < time()) {
            // Drop it now rather than leaving it to be overwritten some day:
            // an expired entry that is never written again is a file that never
            // goes away.
            @unlink($path);
            self::$memo[$key] = self::MISS;
            return $default;
        }

        self::$memo[$key] = $entry['v'];
        return $entry['v'];
    }

    /**
     * @param int $ttl seconds; 0 means "never expires"
     */
    public static function set(string $key, mixed $value, int $ttl = 0): bool
    {
        self::$memo[$key] = $value;
        if (!self::usable()) {
            return false;
        }

        $json = json_encode(
            ['e' => $ttl > 0 ? time() + $ttl : 0, 'v' => $value],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return false;
        }

        // Write to a unique temp file then rename, so a concurrent reader never
        // sees a half-written entry.
        $path = self::path($key);
        $tmp = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, self::GUARD . $json, LOCK_EX) === false) {
            return false;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /**
     * Return the cached value, or run $producer, store its result and return it.
     * A producer that throws is allowed to propagate — nothing is cached.
     *
     * @return mixed
     */
    public static function remember(string $key, int $ttl, callable $producer): mixed
    {
        $sentinel = "\0__cache_miss__\0";
        $hit = self::get($key, $sentinel);
        if ($hit !== $sentinel) {
            return $hit;
        }

        $value = $producer();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
        if (self::usable()) {
            @unlink(self::path($key));
        }
    }

    /**
     * Drop every entry, or only those whose key starts with $prefix.
     */
    public static function flush(string $prefix = ''): void
    {
        foreach (array_keys(self::$memo) as $k) {
            if ($prefix === '' || str_starts_with($k, $prefix)) {
                unset(self::$memo[$k]);
            }
        }
        if (!self::usable()) {
            return;
        }
        $safePrefix = $prefix === '' ? '' : preg_replace('/[^A-Za-z0-9._-]/', '_', $prefix);
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . $safePrefix . '*.cache.php') ?: [] as $file) {
            @unlink($file);
        }
    }
}
