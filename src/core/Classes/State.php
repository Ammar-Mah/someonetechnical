<?php

/**
 * Per-user interface state, addressed by dot path.
 *
 * Everything lives under ONE session key as a nested array, which is what makes
 * it safe to hand out freely: there is no namespace to collide in, no key
 * naming convention to remember, and clearing it is one call.
 *
 *   State::set('theme', 'dark');
 *   State::set(['groups', $id, 'open'], true);
 *   State::get('groups.7.open', false);
 *   State::merge(['filters', $view], ['status' => 'open']);
 *   State::remove(['groups', $id]);
 *
 * Use it for anything that should survive a reload but belongs to this person
 * rather than to the data: open tabs and panels, collapsed groups, chosen
 * filters, sort order, language, theme.
 *
 * Writes re-acquire the session lock first (see Session::acquire), so a
 * read-modify-write is still correct even though the request dropped the lock
 * early to let parallel AJAX calls through.
 */
class State
{
    /** Everything is stored under this one session key. */
    private const BASE_KEY = 'app.ui';

    private static function load(): array
    {
        $raw = Session::get(self::BASE_KEY);
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private static function save(array $state): void
    {
        Session::set(self::BASE_KEY, $state);
    }

    /** Accepts 'a.b.c' or ['a', 'b', 'c']. */
    private static function splitPath(string|array $path): array
    {
        if (is_array($path)) {
            return array_values(array_filter($path, fn($v) => $v !== '' && $v !== null));
        }
        $path = trim($path);
        if ($path === '') {
            return [];
        }
        return array_values(array_filter(explode('.', $path), fn($v) => $v !== ''));
    }

    /** An empty path returns the whole state array. */
    public static function get(string|array $path, mixed $default = null): mixed
    {
        $keys = self::splitPath($path);
        if (!$keys) {
            return self::load();
        }
        $cur = self::load();
        foreach ($keys as $key) {
            if (!is_array($cur) || !array_key_exists($key, $cur)) {
                return $default;
            }
            $cur = $cur[$key];
        }
        return $cur === null ? $default : $cur;
    }

    /** Intermediate levels are created as needed. */
    public static function set(string|array $path, mixed $value): mixed
    {
        $keys = self::splitPath($path);
        if (!$keys) {
            return $value;
        }

        // Re-acquire the lock *before* loading, so this whole read-modify-write
        // runs against current data even if the lock was released earlier.
        Session::acquire();

        $state = self::load();
        $cur =& $state;
        for ($i = 0; $i < count($keys) - 1; $i++) {
            $k = (string)$keys[$i];
            if (!isset($cur[$k]) || !is_array($cur[$k])) {
                $cur[$k] = [];
            }
            $cur =& $cur[$k];
        }
        $cur[(string)$keys[count($keys) - 1]] = $value;
        self::save($state);
        return $value;
    }

    /** Shallow-merge an array into whatever is at $path. */
    public static function merge(string|array $path, array $value): array
    {
        Session::acquire();
        $prev = self::get($path, []);
        $prev = is_array($prev) ? $prev : [];
        $next = array_merge($prev, $value);
        self::set($path, $next);
        return $next;
    }

    public static function remove(string|array $path): void
    {
        $keys = self::splitPath($path);
        if (!$keys) {
            return;
        }

        Session::acquire();

        $state = self::load();
        $cur =& $state;
        for ($i = 0; $i < count($keys) - 1; $i++) {
            $k = (string)$keys[$i];
            if (!isset($cur[$k]) || !is_array($cur[$k])) {
                return;
            }
            $cur =& $cur[$k];
        }
        unset($cur[(string)$keys[count($keys) - 1]]);
        self::save($state);
    }

    /** Forget everything. Call on logout. */
    public static function clear(): void
    {
        Session::acquire();
        self::save([]);
    }

    /**
     * Write several sibling values in one read-modify-write.
     *
     * Setting a flag on a hundred rows one call at a time would take the
     * session lock, rewrite the whole state array and save it a hundred times.
     *
     *   State::setMany(['groups', $viewId], $ids, ['open' => true]);
     *
     * @param string|array $path   the parent path
     * @param array        $keys   child keys to touch
     * @param array        $values values merged into each child
     * @return int how many children were written
     */
    public static function setMany(string|array $path, array $keys, array $values): int
    {
        $keys = array_values(array_filter(array_map('strval', $keys), fn($k) => $k !== ''));
        if ($keys === [] || $values === []) return 0;

        Session::acquire();

        $state = self::load();
        $node =& $state;
        foreach (self::splitPath($path) as $segment) {
            $segment = (string)$segment;
            if (!isset($node[$segment]) || !is_array($node[$segment])) $node[$segment] = [];
            $node =& $node[$segment];
        }

        foreach ($keys as $key) {
            if (!isset($node[$key]) || !is_array($node[$key])) $node[$key] = [];
            $node[$key] = array_merge($node[$key], $values);
        }
        unset($node);

        self::save($state);
        return count($keys);
    }
}
