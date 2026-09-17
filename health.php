<?php

/**
 * GET /health - what the production smoke check asks for (policies/production.md):
 * 200, application/json, {"status":"ok"}, nothing else. The root .htaccess
 * routes /health here.
 *
 * It reads the configuration and arms the log, and no more. The full boot
 * starts a session, and a check that runs every few minutes must not leave a
 * session file behind each time.
 */

foreach (require __DIR__ . '/runtime.php' as $key => $value) {
    define($key, $value);
}
define('ROOT', __DIR__);
if (defined('APP_TIMEZONE') && APP_TIMEZONE !== '') {
    date_default_timezone_set(APP_TIMEZONE);
}

require __DIR__ . '/src/core/Classes/Log.php';
Log::boot();
Log::info('health', 'health answered');

header('Content-Type: application/json');
header('Cache-Control: no-store');
echo '{"status":"ok"}';
