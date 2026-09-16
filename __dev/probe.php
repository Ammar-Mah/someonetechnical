<?php

declare(strict_types=1);

/**
 * The DEV probe — what the running application reports about itself.
 *
 * ============================================================================
 * THIS DIRECTORY MUST NEVER REACH PRODUCTION.
 *
 * deploy-prod.yml leaves __dev/ out of the production package and fails the
 * build if it or any reference to __dev survives.
 * The production smoke check then asserts these URLs return 404.
 * See policies/production.md.
 * ============================================================================
 *
 * GET /__dev/probe          (or /__dev/probe.php where mod_rewrite is off)
 *
 * No token: it reveals nothing sensitive, and the deployment workflow queries
 * it before any secret is available to it. Compared by every deployment and by
 * validate-dev against the commit the pipeline believes it deployed.
 *
 * It boots the framework's initialize.inc.php for real — constants, autoload,
 * error handlers — because "can it boot?" is the first thing worth knowing. If
 * that throws, the probe still answers, as degraded, with the reason.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);

// ---------------------------------------------------------------- boot
$boot      = 'ok';
$bootError = null;

try {
    require_once $root . '/src/core/inc/initialize.inc.php';
    require_once $root . '/src/core/inc/functions.inc.php';
} catch (Throwable $e) {
    $boot      = 'failed';
    $bootError = str_replace($root, '.', $e->getMessage());
}

// ---------------------------------------------------- deployment state
// What the pipeline wrote. Compared with what this application reports.
$commit = 'unknown';
$branch = 'unknown';
$deployedAt = null;
$workflowRun = null;

$statePath = $root . '/.dev-state.json';

if (is_readable($statePath)) {
    $state = json_decode((string) @file_get_contents($statePath), true);

    if (is_array($state)) {
        $commit      = (string) ($state['commit'] ?? 'unknown');
        $branch      = (string) ($state['branch'] ?? 'unknown');
        $deployedAt  = isset($state['deployed_at']) ? (string) $state['deployed_at'] : null;
        $workflowRun = isset($state['workflow_run']) ? (string) $state['workflow_run'] : null;
    }
} elseif (is_readable($root . '/.dev-commit')) {
    $commit = trim((string) @file_get_contents($root . '/.dev-commit'));
    $stamp  = @filemtime($root . '/.dev-commit');
    if ($stamp !== false) {
        $deployedAt = gmdate('c', $stamp);
    }
}

// ------------------------------------------------------------ database
$engine = defined('DB_ENGINE') ? strtolower(trim((string) DB_ENGINE)) : 'file';
if ($engine !== 'sql') {
    $engine = 'file';
}

$database = 'not-configured';
$driver = null;
$migrationStatus = 'not-applicable';

if ($boot === 'ok') {
    // DB_PATH holds the file engine's tables, and the SQLite database when no
    // MySQL database is named.
    $dbPath = defined('DB_PATH') ? (string) DB_PATH : 'data';
    if (! preg_match('#^([a-zA-Z]:)?[\\\\/]#', $dbPath)) {
        $dbPath = $root . '/' . $dbPath;
    }

    if ($engine === 'sql') {
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->query('SELECT 1');
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            // SQLite writes its journal beside the database file.
            $database = $driver !== 'sqlite' || is_writable($dbPath) ? 'reachable' : 'unreachable';

            // Schema changes live in database/*.sql and are recorded in
            // schema_migrations by __dev/migrate.php. Pending means a file
            // exists that has not been applied here.
            $files = glob($root . '/database/*.sql') ?: [];
            $files = array_values(array_filter($files, static fn (string $f): bool => ! str_ends_with($f, '.down.sql')));

            if ($files !== []) {
                $applied = [];
                try {
                    $applied = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                } catch (Throwable) {
                    // No table yet: nothing has ever been applied.
                }
                $names = array_map(static fn (string $f): string => basename($f, '.sql'), $files);
                $migrationStatus = array_diff($names, $applied) === [] ? 'current' : 'pending';
            }
        } catch (Throwable) {
            // Never surface the exception: the DSN is in it.
            $database = 'unreachable';
        }
    } else {
        // The file engine: rows live as JSON under DB_PATH. "Reachable" means
        // the directory exists (or can be created) and is writable.
        if (! is_dir($dbPath)) {
            @mkdir($dbPath, 0775, true);
        }
        $database = is_dir($dbPath) && is_writable($dbPath) ? 'reachable' : 'unreachable';
    }
}

// ------------------------------------------------------------- storage
$writable = true;
foreach (['cache', 'logs'] as $dir) {
    $path = $root . '/' . $dir;
    if (! is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    if (! is_dir($path) || ! is_writable($path)) {
        $writable = false;
    }
}
$storage = $writable ? 'writable' : 'not-writable';

// ------------------------------------------------------------------ log
// The logger's own state, so one probe call says whether DEV can be observed
// and whether anything went wrong recently. Counts and the last error's
// phrase only — never a context.
$logState = [
    'enabled'         => defined('LOG_ENABLED') && LOG_ENABLED,
    'level'           => defined('LOG_LEVEL') ? strtolower((string) LOG_LEVEL) : null,
    'metrics'         => defined('LOG_METRICS') && LOG_METRICS,
    'writable'        => is_dir($root . '/logs') && is_writable($root . '/logs'),
    'file'            => 'logs/app-' . date('Y-m-d') . '.log.php',
    'last_entry_at'   => null,
    'window_minutes'  => 15,
    'errors_recent'   => 0,
    'warnings_recent' => 0,
    'last_error'      => null,
];

$logFile = $root . '/' . $logState['file'];

if (is_readable($logFile)) {
    // The tail is enough: fifteen minutes of a DEV log is far under 256 KB.
    $handle = @fopen($logFile, 'r');
    if ($handle !== false) {
        fseek($handle, 0, SEEK_END);
        $size = (int) ftell($handle);
        fseek($handle, max(0, $size - 262144));
        $tail = (string) fread($handle, 262144);
        fclose($handle);

        $cutoff = time() - $logState['window_minutes'] * 60;

        foreach (explode("\n", $tail) as $line) {
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $entry = json_decode($line, true);
            if (! is_array($entry)) {
                continue;
            }
            $logState['last_entry_at'] = $entry['ts'] ?? $logState['last_entry_at'];

            $at = strtotime((string) ($entry['ts'] ?? ''));
            if ($at === false || $at < $cutoff) {
                continue;
            }
            $level = strtolower((string) ($entry['lvl'] ?? 'info'));
            if ($level === 'error') {
                $logState['errors_recent']++;
                $logState['last_error'] = [
                    'ts'  => $entry['ts'] ?? null,
                    'ch'  => $entry['ch'] ?? null,
                    'msg' => $entry['msg'] ?? null,
                    'rid' => $entry['rid'] ?? null,
                ];
            } elseif ($level === 'warn') {
                $logState['warnings_recent']++;
            }
        }
    }
}

// ---------------------------------------------------------------- misc
$environment = defined('APP_ENV') && APP_ENV !== ''
    ? (string) APP_ENV
    : ((defined('DEBUG_MODE') && DEBUG_MODE) ? 'development' : 'unknown');

// The starter signs everyone in as user 1. That line must be gone before the
// application is reachable by anyone but the team — reviewers check this flag.
$starterAutoLogin = str_contains(
    (string) @file_get_contents($root . '/public/index.php'),
    "Session::set('user', 1)"
);

$healthy = $boot === 'ok' && $database !== 'unreachable' && $storage === 'writable';

// The probe's own answer is a positive event in the application's log: the
// deployment pipeline and every validation read it there, with the commit.
if (class_exists('Log', false) && Log::enabled()) {
    Log::write($healthy ? Log::INFO : Log::WARN, 'atlas', 'probe answered', [
        'health'           => $healthy ? 'ok' : 'degraded',
        'commit'           => $commit,
        'database'         => $database,
        'storage'          => $storage,
        'migration_status' => $migrationStatus,
    ]);
}

http_response_code($healthy ? 200 : 503);

echo json_encode([
    'environment'        => $environment,
    'health'             => $healthy ? 'ok' : 'degraded',
    'boot'               => $boot,
    'boot_error'         => $bootError,
    'git_commit'         => $commit,
    'branch'             => $branch,
    'deployed_at'        => $deployedAt,
    'workflow_run'       => $workflowRun,
    'php_version'        => PHP_VERSION,
    'database'           => $database,
    'db_engine'          => $engine,
    'db_driver'          => $driver,
    'storage'            => $storage,
    'migration_status'   => $migrationStatus,
    'log'                => $logState,
    'debug_mode'         => defined('DEBUG_MODE') ? (bool) DEBUG_MODE : null,
    'starter_auto_login' => $starterAutoLogin,
    'checked_at'         => gmdate('c'),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
