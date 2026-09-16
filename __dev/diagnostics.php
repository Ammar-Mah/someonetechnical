<?php

declare(strict_types=1);

/**
 * DEV diagnostics — detail for an agent debugging a failure.
 *
 * ============================================================================
 * THIS DIRECTORY MUST NEVER REACH PRODUCTION. See probe.php.
 * ============================================================================
 *
 *   GET /__dev/diagnostics                      everything below
 *   GET /__dev/diagnostics?check=db             engine, tables, row counts, columns
 *   GET /__dev/diagnostics?check=log&since=15m      log entries: level (min), ch, rid, limit
 *   GET /__dev/diagnostics?check=errors&since=15m   the same at level=warn
 *   GET /__dev/diagnostics?check=storage        cache, logs, data, compiled templates
 *   GET /__dev/diagnostics?check=php            version, extensions, limits
 *   GET /__dev/diagnostics?check=config         the non-secret configuration
 *
 * Requires DEV_PROBE_TOKEN as the X-Dev-Token header or ?token=.
 *
 * It must NEVER return: passwords, tokens, keys, connection strings, raw
 * environment variables, or customer data. Counts, names, statuses and
 * presence flags only. See policies/security.md.
 *
 * Boots initialize.inc.php and functions.inc.php only — not the application's
 * boot.inc.php — so a broken app boot cannot take the diagnostics down with it.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);

try {
    require_once $root . '/src/core/inc/initialize.inc.php';
    require_once $root . '/src/core/inc/functions.inc.php';
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'The framework did not boot: ' . str_replace($root, '.', $e->getMessage())]);
    exit;
}

// ----------------------------------------------------------------- auth
$expected = defined('DEV_PROBE_TOKEN') ? (string) DEV_PROBE_TOKEN : '';

if ($expected === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Diagnostics are not configured: no DEV_PROBE_TOKEN. The DEV deployment writes it to runtime.dev.php - redeploy.']);
    exit;
}

$supplied = $_SERVER['HTTP_X_DEV_TOKEN'] ?? ($_GET['token'] ?? '');

if (! is_string($supplied) || ! hash_equals($expected, $supplied)) {
    Log::warn('atlas', 'diagnostics refused', ['reason' => 'bad token']);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised.']);
    exit;
}

Log::info('atlas', 'diagnostics served', ['check' => isset($_GET['check']) ? (string) $_GET['check'] : 'all']);

// -------------------------------------------------------------- helpers

function dev_engine(): string
{
    $engine = defined('DB_ENGINE') ? strtolower(trim((string) DB_ENGINE)) : 'file';

    return $engine === 'sql' ? 'sql' : 'file';
}

function dev_data_dir(string $root): string
{
    $path = defined('DB_PATH') ? (string) DB_PATH : 'data';

    return preg_match('#^([a-zA-Z]:)?[\\\\/]#', $path) ? $path : $root . '/' . $path;
}

/** Turn "15m", "2h", "1d" into seconds. */
function dev_duration(string $value): int
{
    if (preg_match('/^(\d+)\s*([smhd])$/i', trim($value), $m) !== 1) {
        return 900;
    }

    return (int) $m[1] * ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400][strtolower($m[2])];
}

/** @return array<string, mixed> */
function dev_db(string $root): array
{
    $engine = dev_engine();

    if ($engine === 'file') {
        $dir    = dev_data_dir($root);
        $tables = [];

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            $rows    = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];
            $first   = $rows === [] ? [] : reset($rows);

            $tables[basename($file, '.json')] = [
                'rows'    => count($rows),
                'auto'    => $decoded['auto'] ?? null,
                'columns' => is_array($first) ? array_keys($first) : [],
            ];
        }

        return [
            'engine'   => 'file',
            'status'   => is_dir($dir) && is_writable($dir) ? 'reachable' : 'unreachable',
            'path'     => str_replace($root, '.', $dir),
            'tables'   => $tables,
            'note'     => 'No schema on the file engine: a table is created by its first insert.',
        ];
    }

    try {
        $pdo = Database::getInstance()->getConnection();
        $pdo->query('SELECT 1');
    } catch (Throwable) {
        return ['engine' => 'sql', 'status' => 'unreachable'];
    }

    // No DB_NAME means SQLite, in DB_PATH.
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $result = [
        'engine'   => 'sql',
        'driver'   => $sqlite ? 'sqlite' : 'mysql',
        'version'  => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'status'   => 'reachable',
        'database' => $sqlite ? str_replace($root, '.', dev_data_dir($root)) . '/database.sqlite' : (string) DB_NAME,
    ];

    try {
        // Row counts and column names only. Never row contents.
        $names = $pdo->query($sqlite
            ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            : 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $tables = [];
        foreach ($names as $name) {
            $name = (string) $name;
            if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
                continue;
            }

            if ($sqlite) {
                $columns = array_column($pdo->query("PRAGMA table_info(`{$name}`)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            } else {
                $stmt = $pdo->prepare(
                    'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position'
                );
                $stmt->execute([$name]);
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }

            $tables[$name] = [
                'rows'    => (int) $pdo->query("SELECT COUNT(*) FROM `{$name}`")->fetchColumn(),
                'columns' => $columns,
            ];
        }
        $result['tables'] = $tables;

        $applied = [];
        try {
            $applied = $pdo->query('SELECT name, applied_at FROM schema_migrations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            // No table yet.
        }

        $files = array_values(array_filter(
            array_map(static fn (string $f): string => basename($f, '.sql'), glob($root . '/database/*.sql') ?: []),
            static fn (string $n): bool => ! str_ends_with($n, '.down')
        ));
        $appliedNames = array_column($applied, 'name');

        $result['schema'] = [
            'applied' => $applied,
            'pending' => array_values(array_diff($files, $appliedNames)),
        ];
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}

/**
 * Read the JSONL log with filters: since (window), level (minimum), ch, rid,
 * limit. A rid search ignores the window — a request id is unique enough.
 *
 * @param array<string, mixed> $filter
 * @return array<string, mixed>
 */
function dev_log(string $root, array $filter): array
{
    $levels  = ['debug' => 10, 'info' => 20, 'warn' => 30, 'error' => 40];
    $since   = (string) ($filter['since'] ?? '15m');
    $minName = strtolower((string) ($filter['level'] ?? 'info'));
    $min     = $levels[$minName] ?? 20;
    $channel = (string) ($filter['ch'] ?? '');
    $rid     = (string) ($filter['rid'] ?? '');
    $limit   = max(1, min(500, (int) ($filter['limit'] ?? 100)));

    $dir = $root . '/logs';

    if (! is_dir($dir)) {
        return ['status' => 'no-log-directory'];
    }

    $seconds = dev_duration($since);
    $cutoff  = $rid !== '' ? 0 : time() - $seconds;

    // One JSONL file per day behind a "<?php exit;" guard line. Read today and
    // every day the window reaches back into; a rid search reads a week.
    $days = [];
    $from = $rid !== '' ? time() - 7 * 86400 : $cutoff;
    for ($t = $from; $t <= time(); $t += 86400) {
        $days[date('Y-m-d', $t)] = true;
    }
    $days[date('Y-m-d')] = true;

    $entries = [];
    $scanned = 0;
    $errors  = 0;
    $warns   = 0;

    foreach (array_keys($days) as $day) {
        foreach ([$dir . '/app-' . $day . '.1.log.php', $dir . '/app-' . $day . '.log.php'] as $file) {
            if (! is_readable($file)) {
                continue;
            }
            $scanned++;

            foreach (explode("\n", (string) @file_get_contents($file)) as $line) {
                if ($line === '' || $line[0] !== '{') {
                    continue; // the guard line, or a blank
                }

                $entry = json_decode($line, true);
                if (! is_array($entry)) {
                    continue;
                }

                $level = strtolower((string) ($entry['lvl'] ?? 'info'));
                if (($levels[$level] ?? 20) < $min) {
                    continue;
                }
                if ($channel !== '' && (string) ($entry['ch'] ?? '') !== $channel) {
                    continue;
                }
                if ($rid !== '' && (string) ($entry['rid'] ?? '') !== $rid) {
                    continue;
                }

                $at = strtotime((string) ($entry['ts'] ?? ''));
                if ($at !== false && $at < $cutoff) {
                    continue;
                }

                if ($level === 'error') {
                    $errors++;
                } elseif ($level === 'warn') {
                    $warns++;
                }

                $entries[] = [
                    'ts'  => $entry['ts'] ?? null,
                    'ms'  => $entry['ms'] ?? null,
                    'lvl' => $level,
                    'ch'  => $entry['ch'] ?? null,
                    'rid' => $entry['rid'] ?? null,
                    'msg' => $entry['msg'] ?? null,
                    'ctx' => $entry['ctx'] ?? null,
                ];
            }
        }
    }

    return [
        'status'   => $errors > 0 ? 'errors-present' : ($warns > 0 ? 'warnings-present' : 'clean'),
        'since'    => $rid !== '' ? '7d' : $since,
        'level'    => $minName,
        'ch'       => $channel === '' ? null : $channel,
        'rid'      => $rid === '' ? null : $rid,
        'files'    => $scanned,
        'count'    => count($entries),
        'errors'   => $errors,
        'warnings' => $warns,
        'entries'  => array_slice($entries, -$limit),
    ];
}

/** The negative view: warn and error only. @return array<string, mixed> */
function dev_errors(string $root, string $since): array
{
    return dev_log($root, ['since' => $since, 'level' => 'warn']);
}

/** @return array<string, mixed> */
function dev_storage(string $root): array
{
    $result = [];

    foreach (['cache', 'cache/templates', 'logs'] as $relative) {
        $path = $root . '/' . $relative;
        $result[$relative] = [
            'exists'   => is_dir($path),
            'writable' => is_dir($path) && is_writable($path),
            'files'    => is_dir($path) ? count(glob($path . '/*') ?: []) : 0,
        ];
    }

    if (dev_engine() === 'file') {
        $dir = dev_data_dir($root);
        $result['data'] = [
            'path'     => str_replace($root, '.', $dir),
            'exists'   => is_dir($dir),
            'writable' => is_dir($dir) && is_writable($dir),
            'tables'   => is_dir($dir) ? count(glob($dir . '/*.json') ?: []) : 0,
        ];
    }

    $free = @disk_free_space($root);
    $result['disk_free_mb'] = $free === false ? null : (int) round($free / 1048576);

    return $result;
}

/** @return array<string, mixed> */
function dev_php(): array
{
    $extensions = [];
    foreach (['json', 'mbstring', 'zlib', 'pdo', 'pdo_mysql', 'pdo_sqlite', 'openssl', 'curl', 'gd', 'intl', 'fileinfo'] as $ext) {
        $extensions[$ext] = extension_loaded($ext) ? 'loaded' : 'absent';
    }

    return [
        'version'    => PHP_VERSION,
        'sapi'       => PHP_SAPI,
        'extensions' => $extensions,
        'limits'     => [
            'memory_limit'        => ini_get('memory_limit'),
            'max_execution_time'  => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
        ],
        'settings' => [
            // initialize.inc.php forces this on; production safety rests on
            // DEBUG_MODE being false, which the config check reports.
            'display_errors'   => (bool) ini_get('display_errors'),
            'opcache'          => function_exists('opcache_get_status') && (bool) ini_get('opcache.enable'),
            'zlib_compression' => (bool) ini_get('zlib.output_compression'),
        ],
    ];
}

/** @return array<string, mixed> */
function dev_config(string $root): array
{
    $safe = [];
    foreach (['APP_ENV', 'APP_NAME', 'APP_URL', 'APP_LOCALE', 'APP_TIMEZONE', 'DEBUG_MODE', 'SESSION_LIFETIME',
              'DB_ENGINE', 'DB_PATH', 'DB_CHARSET', 'MAIL_TRANSPORT', 'MAIL_FROM', 'MAIL_REDIRECT_ALL_TO',
              'LOG_ENABLED', 'LOG_LEVEL', 'LOG_CHANNELS', 'LOG_METRICS', 'LOG_SLOW_QUERY_MS',
              'ENABLE_GZIP', 'APP_DATA_TTL'] as $key) {
        $safe[$key] = defined($key) ? constant($key) : null;
    }

    // Presence only for anything secret-shaped.
    $safe['DB_NAME']              = defined('DB_NAME') && DB_NAME !== '' ? '(set)' : '(empty)';
    $safe['DB_PASSWORD']          = defined('DB_PASSWORD') && DB_PASSWORD !== '' ? '(set)' : '(empty)';
    $safe['DEV_PROBE_TOKEN']      = '(set)';
    $safe['runtime_dev_present']   = is_file($root . '/runtime.dev.php');
    $safe['runtime_local_present'] = is_file($root . '/runtime.local.php');
    $safe['starter_auto_login']   = str_contains((string) @file_get_contents($root . '/public/index.php'), "Session::set('user', 1)");

    return $safe;
}

// --------------------------------------------------------------- dispatch
$check = isset($_GET['check']) ? (string) $_GET['check'] : null;
$since = isset($_GET['since']) ? (string) $_GET['since'] : '15m';

$response = match ($check) {
    'db'      => dev_db($root),
    'log'     => dev_log($root, $_GET),
    'errors'  => dev_errors($root, $since),
    'storage' => dev_storage($root),
    'php'     => dev_php(),
    'config'  => dev_config($root),
    null      => [
        'database' => dev_db($root),
        'errors'   => dev_errors($root, $since),
        'storage'  => dev_storage($root),
        'php'      => dev_php(),
        'config'   => dev_config($root),
    ],
    default   => ['error' => "Unknown check '{$check}'. Use db, log, errors, storage, php or config."],
};

if (isset($response['error']) && count($response) === 1) {
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
