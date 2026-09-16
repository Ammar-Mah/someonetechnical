<?php

/**
 * Application configuration.
 *
 * Every key below is defined as a PHP CONSTANT during boot, so it is readable
 * anywhere as APP_NAME, DB_HOST and so on. That is also why the array holds
 * values only — it is loaded before anything else exists. For the same reason
 * two things run after it, since no other application file runs this early:
 * each server's own settings are merged in, and the session cookie gets its
 * flags before the framework starts the session.
 *
 * Keep secrets out of version control. This file is committed and holds the
 * defaults for a local checkout. Each server carries a runtime.local.php —
 * created there by hand, never committed, never deployed — whose keys replace
 * the ones here. See the bottom of this file and runtime.local.example.php.
 */

$config = [
    // -------------------------------------------------------------------------
    // Application
    // -------------------------------------------------------------------------

    /**
     * Product name. Shown by SiteHeader and SiteFooter and used as the page
     * title. PRODUCT.md keeps implementation details off the page, so this is
     * never the framework's name.
     *
     * The render snapshot in tests/snapshots/render.txt includes the Logo, so
     * after changing this run `php tests/run.php --update` and confirm the only
     * lines that moved are the Logo's.
     */
    'APP_NAME' => 'Someone Technical',

    /**
     * Absolute URL of the public/ folder, WITH a trailing slash.
     * asset() and every generated link are built from it, so getting this wrong
     * is the usual reason styles or scripts 404 after a move.
     *
     * The default matches the ATLAS workspace layout under WAMP:
     * C:\wamp64\www\<project>\repo\ served at http://localhost/<project>/repo/.
     * On DEV the deployment sets it in runtime.dev.php; production sets its
     * own in runtime.local.php.
     */
    'APP_URL' => 'http://localhost/someonetechnical/repo/public/',

    /**
     * Which environment this is: 'development' or 'production'.
     *
     * Read by the DEV probe and reported to the deployment pipeline. Nothing in
     * the framework branches on it — DEBUG_MODE does that — but ATLAS validation
     * refuses to trust an environment that does not say what it is.
     */
    'APP_ENV' => 'development',

    /** Default interface language. 'en' needs no translation file. */
    'APP_LOCALE' => 'en',

    /**
     * Set this to the timezone the DATABASE server is in.
     * Left unset, PHP falls back to UTC while MySQL uses the machine's local
     * zone — so a column written by PHP and one filled by CURRENT_TIMESTAMP
     * record different times for the same event.
     */
    'APP_TIMEZONE' => 'UTC',

    /**
     * true  → uncaught exceptions and handler failures return file, line and
     *         trace, and the browser shows them.
     * false → a neutral message, with the detail in the log.
     * Must be false in production. Keep it true on DEV: refused requests then
     * explain themselves, which is what an agent debugging a handler needs.
     */
    'DEBUG_MODE' => true,

    /** Seconds a session survives: cookie lifetime and server-side GC. */
    'SESSION_LIFETIME' => 2592000,   // 30 days

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /**
     * Where rows live. The Model layer is identical either way — where(),
     * relations, soft deletes and transactions all behave the same, so this is
     * genuinely a one-line switch.
     *
     *   'file'  one JSON file per table under DB_PATH. No server, no schema,
     *           no setup. Right for prototypes and small applications; the
     *           whole table is decoded on each request that touches it, which
     *           is comfortable into the low tens of thousands of rows.
     *   'sql'   MySQL/MariaDB over PDO, using the DB_* settings below.
     *
     * Anything else falls back to 'file' with a warning naming what it read.
     */
    'DB_ENGINE' => 'file',

    /** Where the file engine keeps its tables. Relative paths hang off the project root. */
    'DB_PATH' => 'data',

    // --- Only read when DB_ENGINE is 'sql' -----------------------------------
    // The connection is opened lazily, on the first query — so these can stay
    // empty while DB_ENGINE is 'file'. Real credentials go in runtime.local.php.

    'DB_HOST'     => '127.0.0.1',
    'DB_NAME'     => '',
    'DB_USER'     => 'root',
    'DB_PASSWORD' => '',
    'DB_CHARSET'  => 'utf8mb4',

    // -------------------------------------------------------------------------
    // Mail
    // -------------------------------------------------------------------------

    /**
     * 'log'  → format the message, write it to the log, deliver nothing.
     * 'mail' → PHP's mail().
     * Anything else falls back to 'log' with a warning naming what it read, so
     * a typo stops delivery loudly instead of behaving like something else.
     */
    'MAIL_TRANSPORT' => 'log',

    'MAIL_FROM'      => 'noreply@example.com',
    'MAIL_FROM_NAME' => 'Baustein',

    /**
     * When set, EVERY recipient is rewritten to this address. Use it to test
     * real delivery without mailing anyone else. Empty means no redirect.
     */
    'MAIL_REDIRECT_ALL_TO' => '',

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------
    // Four knobs, coarsest first. To quieten the log, reach for them in this
    // order: LOG_METRICS, then LOG_CHANNELS, then LOG_LEVEL, then LOG_ENABLED.

    /** Master switch. false costs one boolean per call and writes nothing. */
    'LOG_ENABLED' => true,

    /** Minimum level recorded: 'debug' | 'info' | 'warn' | 'error'. */
    'LOG_LEVEL' => 'info',

    /** Allowlist of channels, e.g. ['db'] while chasing a query. [] = all. */
    'LOG_CHANNELS' => [],

    /**
     * One "request complete" summary per request: timings, query count, bytes,
     * peak memory. Useful for a day of profiling, noise the rest of the time.
     */
    'LOG_METRICS' => false,

    /** Record individual queries slower than this many ms. 0 disables. */
    'LOG_SLOW_QUERY_MS' => 200,

    /** Rotate the day's log past this size (KB). One .1 backup is kept. */
    'LOG_MAX_FILE_KB' => 4096,

    // -------------------------------------------------------------------------
    // Performance
    // -------------------------------------------------------------------------

    /** Compress responses. Turn off only if a proxy in front already does it. */
    'ENABLE_GZIP' => true,

    /** Seconds shared reference data may be served from cache. See boot.inc.php. */
    'APP_DATA_TTL' => 60,

    // -------------------------------------------------------------------------
    // ATLAS development probe
    // -------------------------------------------------------------------------

    /**
     * Gates the diagnostics and migrator endpoints in the DEV tooling
     * directory. Empty disables them; the probe itself needs no token. On DEV
     * the deployment writes it into runtime.dev.php, derived from the DEV
     * password and the project name - the value `atlas token` prints. The
     * tooling directory is excluded from production packages, so production
     * never reads this key.
     */
    'DEV_PROBE_TOKEN' => '',
];

// -----------------------------------------------------------------------------
// Per-server overrides
// -----------------------------------------------------------------------------
// The framework reads only this file, so a server's own files are merged here
// rather than by the boot code, in this order - the later one wins:
//
//   runtime.dev.php    written by the ATLAS DEV deployment on every deploy:
//                      APP_ENV, APP_URL, DEBUG_MODE, DEV_PROBE_TOKEN. Never
//                      edited by hand, never present in production.
//   runtime.local.php  written by a person on one server, for what that
//                      server needs beyond it: database credentials, mail,
//                      and on production everything. Deploy-ignored, so it
//                      survives every deployment and never leaves its server.
//
// Both are git-ignored. This file is required at global scope, so the loop's
// variables are removed once it is done.

foreach (['runtime.dev.php', 'runtime.local.php'] as $runtimeOverrideFile) {
    $runtimeOverridePath = __DIR__ . '/' . $runtimeOverrideFile;

    if (is_file($runtimeOverridePath)) {
        $runtimeOverrides = require $runtimeOverridePath;

        if (is_array($runtimeOverrides)) {
            $config = array_replace($config, $runtimeOverrides);
        }
    }
}
unset($runtimeOverrideFile, $runtimeOverridePath, $runtimeOverrides);

// -----------------------------------------------------------------------------
// The session cookie
// -----------------------------------------------------------------------------
// initialize.inc.php starts the session as soon as this file returns, and no
// file in src/app/ runs before that, so the cookie's flags are set here.
// policies/security.md asks for HttpOnly, Secure and SameSite=Lax at least.
// Secure is only ever turned on: when the request came over HTTPS or the site's
// own URL is https, so a local http:// checkout keeps its session and a server
// that already sets it keeps it. Once a session exists, PHP refuses these.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $runtimeHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || str_starts_with(strtolower((string)$config['APP_URL']), 'https://');
    if ($runtimeHttps) {
        ini_set('session.cookie_secure', '1');
    }
    unset($runtimeHttps);
}

return $config;
