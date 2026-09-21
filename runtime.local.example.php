<?php

/**
 * Per-server configuration — EXAMPLE.
 *
 * runtime.php merges runtime.local.php over its defaults at boot, so list only
 * the keys that differ for the server this file sits on. It is git-ignored and
 * listed in .deployignore, so it is never committed, never uploaded, and never
 * deleted by a deployment. Credentials live here or nowhere. See
 * policies/security.md.
 *
 * DEV needs no local file at all. The DEV deployment writes runtime.dev.php
 * itself - APP_ENV, APP_URL, DEBUG_MODE, LOG_METRICS and DEV_PROBE_TOKEN - and
 * replaces it on every deploy, and with no DB_NAME the SQL engine runs on
 * SQLite in DB_PATH, which needs nothing set up. Create a runtime.local.php on
 * DEV only for what that leaves out, and it wins over the deployment's values.
 *
 * Production needs one, written on that server once.
 */

return [
    // ---- a DEV server: only what the deployment does not write ---------------
    'MAIL_TRANSPORT' => 'log',

    // The log is the primary instrument. info is the floor everywhere.
    'LOG_LEVEL'         => 'info',
    'LOG_SLOW_QUERY_MS' => 200,

    // ---- a PRODUCTION server (use instead of the block above) ----------------
    // 'APP_ENV'        => 'production',
    // 'APP_URL'        => 'https://myproject.example.com/public/',
    // 'DEBUG_MODE'     => false,
    // Leave DB_NAME out to keep SQLite in DB_PATH; name a database to use MySQL.
    // 'DB_HOST'        => 'localhost',
    // 'DB_NAME'        => 'ideals_myproject',
    // 'DB_USER'        => 'ideals_myproject',
    // 'DB_PASSWORD'    => 'put-the-real-password-here',
    // 'MAIL_TRANSPORT' => 'mail',
    // 'INTAKE_NOTIFY_TO' => 'owner@example.com',  // who hears of each intake request; empty skips it
    // 'LOG_LEVEL'      => 'info',     // never warn: that discards every positive event
    // 'LOG_METRICS'    => false,
    // DEV_PROBE_TOKEN is not set in production: the DEV tooling directory does not exist there.
];
