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
 * itself - APP_ENV, APP_URL, DEBUG_MODE and DEV_PROBE_TOKEN - and replaces it
 * on every deploy. Create a runtime.local.php on DEV only for what that leaves
 * out, and it wins over the deployment's values.
 *
 * Production needs one, written on that server once.
 */

return [
    // ---- a DEV server: only what the deployment does not write ---------------
    // Uncomment once the site uses the SQL engine.
    // 'DB_ENGINE'   => 'sql',
    // 'DB_HOST'     => 'localhost',
    // 'DB_NAME'     => 'exceedlimits_myproject',
    // 'DB_USER'     => 'exceedlimits_myproject',
    // 'DB_PASSWORD' => 'put-the-real-password-here',

    'MAIL_TRANSPORT' => 'log',

    // The log is the primary instrument. info is the floor everywhere; on DEV
    // the per-request summary line is the heartbeat the pipeline reads.
    'LOG_LEVEL'         => 'info',
    'LOG_METRICS'       => true,
    'LOG_SLOW_QUERY_MS' => 200,

    // ---- a PRODUCTION server (use instead of the block above) ----------------
    // 'APP_ENV'        => 'production',
    // 'APP_URL'        => 'https://myproject.example.com/public/',
    // 'DEBUG_MODE'     => false,
    // 'DB_ENGINE'      => 'sql',
    // 'DB_HOST'        => 'localhost',
    // 'DB_NAME'        => 'ideals_myproject',
    // 'DB_USER'        => 'ideals_myproject',
    // 'DB_PASSWORD'    => 'put-the-real-password-here',
    // 'MAIL_TRANSPORT' => 'mail',
    // 'LOG_LEVEL'      => 'info',     // never warn: that discards every positive event
    // 'LOG_METRICS'    => false,
    // DEV_PROBE_TOKEN is not set in production: the DEV tooling directory does not exist there.
];
