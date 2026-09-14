<?php

declare(strict_types=1);

/**
 * DEV schema migrator.
 *
 * ============================================================================
 * THIS DIRECTORY MUST NEVER REACH PRODUCTION. See probe.php.
 * ============================================================================
 *
 * Baustein has no migration system: on the file engine there is no schema at
 * all, and on the SQL engine the CREATE TABLE lives in the model's docblock and
 * is applied by hand. That is fine for a person and useless for a pipeline, so
 * ATLAS adds this small, DEV-only runner:
 *
 *   database/0001_create_items.sql          applied in name order
 *   database/0001_create_items.down.sql     its reverse (required)
 *
 *   POST /__dev/migrate                     apply every pending file
 *   POST /__dev/migrate?action=status       what is applied and pending
 *   POST /__dev/migrate?action=down&name=0001_create_items   reverse one
 *
 * Applied files are recorded in `schema_migrations`. deploy-dev.yml calls this
 * after uploading when the project sets deploy.migrate = true. Production
 * schema changes are applied by a human through the host's database tool,
 * from the same files — see policies/database.md.
 *
 * Statements are split on a ';' at the end of a line. No DELIMITER blocks —
 * triggers and procedures do not belong in a deployment migration anyway.
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
    echo json_encode(['error' => 'The migrator is not configured: no DEV_PROBE_TOKEN. The DEV deployment writes it to runtime.dev.php - redeploy.']);
    exit;
}

$supplied = $_SERVER['HTTP_X_DEV_TOKEN'] ?? ($_GET['token'] ?? '');

if (! is_string($supplied) || ! hash_equals($expected, $supplied)) {
    Log::warn('atlas', 'migrate refused', ['reason' => 'bad token']);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised.']);
    exit;
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'up';

if ($action !== 'status' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Applying or reversing a migration is a POST.']);
    exit;
}

// -------------------------------------------------------------- engine
$engine = defined('DB_ENGINE') ? strtolower(trim((string) DB_ENGINE)) : 'file';

if ($engine !== 'sql') {
    echo json_encode([
        'status' => 'not-applicable',
        'engine' => 'file',
        'note'   => 'The file engine has no schema. Nothing to migrate.',
    ], JSON_PRETTY_PRINT);
    exit;
}

if (! defined('DB_NAME') || DB_NAME === '') {
    http_response_code(503);
    echo json_encode(['error' => 'DB_NAME is empty. Set the database in runtime.local.php.']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `name`       VARCHAR(191) NOT NULL PRIMARY KEY,
            `applied_at` DATETIME     NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'Database unreachable.']);
    exit;
}

// ------------------------------------------------------------- helpers

/** @return string[] name => path, sorted, apply files only */
function migration_files(string $root): array
{
    $files = [];
    foreach (glob($root . '/database/*.sql') ?: [] as $path) {
        $name = basename($path, '.sql');
        if (str_ends_with($name, '.down')) {
            continue;
        }
        $files[$name] = $path;
    }
    ksort($files, SORT_STRING);

    return $files;
}

/** @return string[] */
function applied_names(PDO $pdo): array
{
    return $pdo->query('SELECT name FROM schema_migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/** Split a .sql file into statements: a ';' at the end of a line terminates one. */
function statements(string $sql): array
{
    $out = [];
    foreach (preg_split('/;[ \t]*(\r?\n|$)/', $sql) ?: [] as $chunk) {
        // Drop comment-only and blank chunks.
        $stripped = preg_replace('/^\s*(--[^\n]*|#[^\n]*)\s*$/m', '', $chunk) ?? $chunk;
        $stripped = trim($stripped);
        if ($stripped !== '') {
            $out[] = $stripped;
        }
    }

    return $out;
}

function run_file(PDO $pdo, string $path): int
{
    $count = 0;
    foreach (statements((string) file_get_contents($path)) as $statement) {
        $pdo->exec($statement);
        $count++;
    }

    return $count;
}

// ------------------------------------------------------------- actions
$files   = migration_files($root);
$applied = applied_names($pdo);

try {
    switch ($action) {
        case 'status':
            echo json_encode([
                'status'  => array_diff(array_keys($files), $applied) === [] ? 'current' : 'pending',
                'applied' => $applied,
                'pending' => array_values(array_diff(array_keys($files), $applied)),
            ], JSON_PRETTY_PRINT);
            break;

        case 'up':
            $ran = [];
            foreach ($files as $name => $path) {
                if (in_array($name, $applied, true)) {
                    continue;
                }
                if (! is_file(substr($path, 0, -4) . '.down.sql')) {
                    throw new RuntimeException("{$name}.sql has no {$name}.down.sql. Every migration needs its reverse — see policies/database.md.");
                }

                $statements = run_file($pdo, $path);
                $stmt = $pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)');
                $stmt->execute([$name, gmdate('Y-m-d H:i:s')]);
                Log::info('atlas', 'migration applied', ['name' => $name, 'statements' => $statements]);
                $ran[] = ['name' => $name, 'statements' => $statements];
            }

            echo json_encode([
                'status'  => 'current',
                'applied' => $ran,
                'note'    => $ran === [] ? 'Nothing to do. Already current.' : count($ran) . ' migration(s) applied.',
            ], JSON_PRETTY_PRINT);
            break;

        case 'down':
            $name = isset($_GET['name']) ? (string) $_GET['name'] : '';
            if ($name === '') {
                $name = $applied === [] ? '' : $applied[array_key_last($applied)];
            }
            if ($name === '' || ! in_array($name, $applied, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Nothing to reverse' . ($name !== '' ? ": {$name} is not applied." : '.')]);
                break;
            }

            $down = $root . '/database/' . $name . '.down.sql';
            if (! is_file($down)) {
                http_response_code(400);
                echo json_encode(['error' => "{$name}.down.sql is missing."]);
                break;
            }

            $statements = run_file($pdo, $down);
            $stmt = $pdo->prepare('DELETE FROM schema_migrations WHERE name = ?');
            $stmt->execute([$name]);

            Log::info('atlas', 'migration reversed', ['name' => $name, 'statements' => $statements]);
            echo json_encode(['status' => 'reversed', 'name' => $name, 'statements' => $statements], JSON_PRETTY_PRINT);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action '{$action}'. Use up, down or status."]);
    }
} catch (Throwable $e) {
    Log::exception('atlas', $e, ['action' => $action, 'note' => 'migration failed']);
    http_response_code(500);
    echo json_encode([
        'error' => 'Migration failed: ' . str_replace($root, '.', $e->getMessage()),
        'note'  => 'MySQL DDL auto-commits, so an earlier statement in the same file may have applied. Check ?action=status and the database before retrying.',
    ], JSON_PRETTY_PRINT);
}
