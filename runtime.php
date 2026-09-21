<?php

/**
 * Application configuration.
 *
 * Every key below is defined as a PHP CONSTANT during boot, so it is readable
 * anywhere as APP_NAME, DB_HOST and so on. That is also why the array holds
 * values only — it is loaded before anything else exists. For the same reason
 * the rest runs after it, since no other application file runs this early:
 * each server's own settings are merged in, what a server left open is decided
 * by its environment, and the session cookie gets its flags before the
 * framework starts the session.
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
     *           no setup; the whole table is decoded on each request that
     *           touches it.
     *   'sql'   SQL over PDO, with the schema from the database/ pairs:
     *           SQLite in DB_PATH/database.sqlite, unless a server names a
     *           MySQL/MariaDB database in DB_NAME below.
     *
     * This site is on 'sql' (DECISIONS 2026-09-17), and a server without DB_NAME
     * needs no database set up and no credential. Anything else falls back to
     * 'file' with a warning naming what it read.
     */
    'DB_ENGINE' => 'sql',

    /**
     * Where the SQLite database (and the file engine's tables) live. Relative
     * paths hang off the project root. Deployments never upload or delete it,
     * and .htaccess refuses it over HTTP.
     */
    'DB_PATH' => 'data',

    // --- MySQL/MariaDB, only when a server names DB_NAME -----------------------
    // The connection is opened lazily, on the first query. With DB_NAME empty
    // the rest is never read. Real credentials go in runtime.local.php.

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

    /**
     * Who IntakeHandler tells about each stored request. PRODUCT.md names
     * nobody, so the owner's address belongs in a server's runtime.local.php,
     * never here. Empty skips the notification with a warning; the request is
     * still stored.
     *
     * null lets the transport decide once each server's settings are merged:
     * where MAIL_TRANSPORT is 'log' it is a placeholder at a reserved domain,
     * so DEV shows the whole notification in its log while delivering nothing;
     * anywhere else it is empty until a server names the owner.
     */
    'INTAKE_NOTIFY_TO' => null,

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
     * One "request complete" summary per request, on channel request: method,
     * uri, status, timings, query count, bytes, peak memory. On DEV it is the
     * heartbeat the pipeline and agents read; in production, noise at volume.
     *
     * null lets the environment decide once each server's settings are merged:
     * on where APP_ENV is 'development', off everywhere else. A server file
     * that sets true or false keeps its choice.
     */
    'LOG_METRICS' => null,

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

// What a server left to its environment. A production file that leaves
// LOG_METRICS out still gets it off.
$config['LOG_METRICS'] ??= $config['APP_ENV'] === 'development';

// A transport that delivers nothing may name a recipient that exists nowhere;
// one that delivers never guesses.
$config['INTAKE_NOTIFY_TO'] ??= strtolower(trim((string)$config['MAIL_TRANSPORT'])) === 'log' ?'owner@someonetechnical.invalid' : '';

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
