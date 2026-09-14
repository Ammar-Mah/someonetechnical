<?php

/**
 * The test runner.
 *
 *   php tests/run.php              run everything
 *   php tests/run.php component    run only cases whose file name matches
 *   php tests/run.php --update     accept the current output as the snapshot
 *
 * No dependencies and no framework of its own: cases are plain PHP files in
 * tests/cases that call the helpers defined below. Exits non-zero when
 * anything fails, so it drops straight into a git hook or CI.
 *
 * Two kinds of check:
 *
 *   ASSERTIONS   same(), ok(), contains(), throws() — for behaviour with a
 *                right answer you can name.
 *   SNAPSHOTS    snapshot() — for rendered markup, where the useful question is
 *                "did this change?" rather than "is this exact string right?".
 *                Component ids are randomised and attribute order is not
 *                meaningful, so both are normalised before comparing; see
 *                stabilise() and normalise().
 *
 * A snapshot mismatch prints the first differing line. Read it, decide whether
 * the change was intended, and re-run with --update if it was.
 */

$root = dirname(__DIR__);
require_once $root . '/src/core/inc/initialize.inc.php';
require_once $root . '/src/core/inc/functions.inc.php';
require_once $root . '/src/app/boot.inc.php';

final class T
{
    public static string $group = '';
    public static string $test = '';
    public static int $passed = 0;
    public static array $failures = [];
    public static bool $update = false;
    public static string $root = '';
}

T::$root = $root;
T::$update = in_array('--update', $argv, true);
$filter = '';
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) $filter = $arg;
}

// --- assertion failure -------------------------------------------------------

final class Failed extends Exception {}

function fail(string $message): void
{
    throw new Failed($message);
}

// --- helpers a case file uses ------------------------------------------------

/** Name the section the following tests belong to. */
function group(string $name): void
{
    T::$group = $name;
}

/** One test. Any assertion inside it that fails stops that test only. */
function test(string $name, callable $body): void
{
    T::$test = $name;
    try {
        $body();
        T::$passed++;
        echo "  \u{2713} " . $name . "\n";
    } catch (Failed $e) {
        T::$failures[] = [T::$group, $name, $e->getMessage()];
        echo "  \u{2717} " . $name . "\n      " . str_replace("\n", "\n      ", $e->getMessage()) . "\n";
    } catch (Throwable $e) {
        T::$failures[] = [T::$group, $name, get_class($e) . ': ' . $e->getMessage()
            . "\n      at " . $e->getFile() . ':' . $e->getLine()];
        echo "  \u{2717} " . $name . "\n      " . get_class($e) . ': ' . $e->getMessage()
            . "\n      at " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

function same($expected, $actual, string $why = ''): void
{
    if ($expected === $actual) return;
    fail(($why !== '' ? $why . "\n" : '')
        . 'expected: ' . var_export($expected, true) . "\n"
        . 'actual:   ' . var_export($actual, true));
}

function ok($condition, string $why = 'expected true'): void
{
    if (!$condition) fail($why);
}

function not_ok($condition, string $why = 'expected false'): void
{
    if ($condition) fail($why);
}

function contains(string $needle, string $haystack, string $why = ''): void
{
    if (str_contains($haystack, $needle)) return;
    fail(($why !== '' ? $why . "\n" : '') . 'expected to find: ' . $needle . "\nin: " . excerpt_for_error($haystack));
}

function lacks(string $needle, string $haystack, string $why = ''): void
{
    if (!str_contains($haystack, $needle)) return;
    fail(($why !== '' ? $why . "\n" : '') . 'did NOT expect to find: ' . $needle . "\nin: " . excerpt_for_error($haystack));
}

/** Assert that $body throws, optionally with $message in the message. */
function throws(callable $body, string $message = ''): void
{
    try {
        $body();
    } catch (Throwable $e) {
        if ($message === '' || str_contains($e->getMessage(), $message)) return;
        fail('threw the wrong thing: ' . $e->getMessage() . "\nexpected it to mention: " . $message);
    }
    fail('expected this to throw' . ($message === '' ? '' : ' mentioning: ' . $message));
}

function excerpt_for_error(string $value, int $limit = 400): string
{
    return strlen($value) > $limit ? substr($value, 0, $limit) . ' …' : $value;
}

// --- storage ------------------------------------------------------------------

/**
 * Every test runs against a scratch data directory, wiped at the start of the
 * run.
 *
 * Two reasons. Tests must never touch the application's own data — rendering a
 * screen is enough to seed a table, and a suite that quietly edits your rows is
 * worse than no suite. And starting from empty is what makes results
 * repeatable: ids begin at 1 every time, so the render snapshot is stable.
 */
function test_storage(bool $wipe = false): FileStorage
{
    static $engine = null;

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'baustein-tests';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    if ($wipe) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) @unlink($file);
        $engine = null;                       // drop the in-memory tables too
    }

    if ($engine === null) {
        if (!defined('TEST_DB_PATH')) define('TEST_DB_PATH', $dir);
        $engine = new class extends FileStorage {
            public function directory(): string { return TEST_DB_PATH; }
        };
        Storage::use($engine);
    }

    return $engine;
}

test_storage(true);

// --- reaching into a builder -------------------------------------------------

/** The query description a builder would hand its storage engine. */
function descriptorOf(Model $model): array
{
    $method = new ReflectionMethod($model, 'queryDescriptor');
    $method->setAccessible(true);
    return $method->invoke($model);
}

/**
 * That description compiled to SQL — regardless of which engine is configured.
 *
 * Model::toSql() now answers in the CONFIGURED engine's terms, so asserting SQL
 * through it would only pass while DB_ENGINE happened to be 'sql'. Compiling
 * explicitly keeps these assertions about the compiler, which is what they are
 * really testing.
 */
function sqlOf(Model $model): string
{
    return (new SqlStorage())->compileSelect(descriptorOf($model))[0];
}

// --- snapshots ---------------------------------------------------------------

/** Random component ids would make every run differ. */
function stabilise(string $html): string
{
    return preg_replace('/(?<=")[a-z]+-\d{7}(?=")/', 'RANDOM-ID', $html);
}

/** Sort the attributes inside every tag, so attribute ORDER stops mattering. */
function normalise(string $html): string
{
    return preg_replace_callback(
        '/<([a-zA-Z][\w:-]*)((?:\s+[^\s=>\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)*)\s*(\/?)>/s',
        function ($m) {
            preg_match_all('/([^\s=]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?/', trim($m[2]), $a, PREG_SET_ORDER);
            $attrs = [];
            foreach ($a as $one) {
                if (trim($one[0]) === '') continue;
                $attrs[] = isset($one[2]) ? $one[1] . '=' . $one[2] : $one[1];
            }
            sort($attrs);
            return '<' . $m[1] . ($attrs ? ' ' . implode(' ', $attrs) : '') . $m[3] . '>';
        },
        $html
    );
}

/**
 * Compare rendered output against tests/snapshots/<name>.txt.
 * Run with --update to accept the current output.
 */
function snapshot(string $name, string $actual): void
{
    $actual = normalise(stabilise($actual));
    $path = T::$root . '/tests/snapshots/' . $name . '.txt';

    if (T::$update || !is_file($path)) {
        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $actual);
        return;                       // test() reports the pass
    }

    $expected = (string)file_get_contents($path);
    if ($expected === $actual) return;

    // Report the first line that differs — a whole-file dump helps nobody.
    $e = explode("\n", $expected);
    $a = explode("\n", $actual);
    $at = 0;
    while ($at < max(count($e), count($a)) && ($e[$at] ?? null) === ($a[$at] ?? null)) $at++;

    fail("snapshot '{$name}' differs at line " . ($at + 1) . "\n"
       . 'expected: ' . excerpt_for_error(trim($e[$at] ?? '(nothing)'), 220) . "\n"
       . 'actual:   ' . excerpt_for_error(trim($a[$at] ?? '(nothing)'), 220) . "\n"
       . '(' . count($e) . ' lines expected, ' . count($a) . " actual)\n"
       . 'If the change was intended: php tests/run.php --update');
}

// --- run ---------------------------------------------------------------------

$files = glob($root . '/tests/cases/*.php') ?: [];
sort($files);

$started = microtime(true);
echo "\n";

foreach ($files as $file) {
    if ($filter !== '' && !str_contains(basename($file), $filter)) continue;
    T::$group = basename($file, '.php');
    echo strtoupper(T::$group) . "\n";
    require $file;
    echo "\n";
}

$ms = round((microtime(true) - $started) * 1000);
$failed = count(T::$failures);

if ($failed === 0) {
    echo T::$passed . " passed in {$ms}ms\n\n";
    exit(0);
}

echo T::$passed . " passed, {$failed} FAILED in {$ms}ms\n";
foreach (T::$failures as [$g, $n, $m]) {
    echo "  - {$g}: {$n}\n";
}
echo "\n";
exit(1);
