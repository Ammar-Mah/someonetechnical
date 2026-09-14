<?php
//displaying all errors
ini_set('display_errors', '1');
error_reporting(E_ALL ^ E_DEPRECATED);

// debug settings for var_dump()
ini_set("xdebug.var_display_max_children", '-1');
ini_set("xdebug.var_display_max_data", '-1');
ini_set("xdebug.var_display_max_depth", '-1');

// main environment variables
define('ROOT', dirname(__FILE__,4));

const SRC = ROOT . '/src';

$runtime = require ROOT . '/runtime.php';
foreach ($runtime as $key => $value) {
    define($key, $value);
}

// Before anything records a timestamp. PHP had no timezone configured, so it
// fell back to UTC while MySQL used the machine's local zone — see APP_TIMEZONE
// in runtime.php for what that broke.
if (defined('APP_TIMEZONE') && APP_TIMEZONE !== '') {
    date_default_timezone_set(APP_TIMEZONE);
}

// Compress responses. Task-table payloads are highly repetitive HTML and
// compress roughly 17x, which is the cheapest win available on the wire.
// Must run before any output. Disable via ENABLE_GZIP if a fronting proxy
// already compresses.
if (defined('ENABLE_GZIP') && ENABLE_GZIP && !headers_sent() && !ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', '1');
    @ini_set('zlib.output_compression_level', '5');
}

// Loaded explicitly rather than via the autoloader below, because the
// autoloader's miss branch logs — resolving Log lazily from inside it would
// re-enter the autoloader.
require_once SRC . '/core/Classes/Log.php';
Log::boot();

// Auto-loading. One class per file, filename identical to the class name, no
// namespaces — the whole framework lives in the global namespace.
//
// Order matters twice over:
//   * core/Classes is first, so nothing in an application folder can shadow a
//     framework class by accident (an app component called Cache or Log).
//   * app/Components comes before core/Components, so dropping a file of the
//     same name into src/app/Components REPLACES that core component outright.
//     To extend one instead, subclass it under a different name —
//     class PrimaryButton extends Button — which needs no registration at all.
// Cache backs the classmap below, so it cannot be resolved BY the autoloader —
// asking for it from inside the autoloader would re-enter the autoloader.
require_once SRC . '/core/Classes/Cache.php';

/**
 * A classmap, built once and cached, instead of up to five stat() calls per
 * class. A page that touches sixty classes was paying nearly three hundred
 * filesystem probes before any of its own code ran.
 *
 * It fills itself in: a class that is not in the map is looked up the slow way
 * and then remembered. Adding a file therefore needs no cache clearing, and a
 * file that moves or is deleted falls back to a scan on its next use.
 */
$__classmap = Cache::get('autoload.map');
if (!is_array($__classmap)) $__classmap = [];
$__classmapDirty = false;

spl_autoload_register(function ($class) use (&$__classmap, &$__classmapDirty) {
    static $folders = [
        'core/Classes',
        'app/Components',
        'app/Events',
        'app/Models',
        'core/Components',
    ];

    if (isset($__classmap[$class])) {
        if (is_file($__classmap[$class])) {
            require_once $__classmap[$class];
            return;
        }
        unset($__classmap[$class]);            // moved or deleted
        $__classmapDirty = true;
    }

    foreach ($folders as $folder) {
        $path = SRC . '/' . $folder . '/' . $class . '.php';
        if (is_file($path)) {
            $__classmap[$class] = $path;
            $__classmapDirty = true;
            require_once $path;
            return;
        }
    }

    // Not fatal on its own: class_exists() legitimately probes for missing
    // classes. Recorded as a warning so a genuine dangling reference is still
    // visible in the log.
    Log::warn('autoload', 'class not found', ['class' => $class]);
});

// One write per request that discovered something new; none once warm.
register_shutdown_function(function () use (&$__classmap, &$__classmapDirty) {
    if ($__classmapDirty) Cache::set('autoload.map', $__classmap, 0);
});

function isDebugMode()
{
    return defined('DEBUG_MODE') && DEBUG_MODE;
}

function isAjaxRequest()
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'));
}

set_error_handler(function($severity, $message, $file, $line) {
    
    if (!(error_reporting() & $severity)) {return false;}       
    throw new \ErrorException($message, $severity, $severity, $file, $line);
});

register_shutdown_function(function () {

    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Backstop: Log::flush() already captures the fatal when it runs, so
        // both calls here are idempotent and exist only in case this handler
        // somehow runs first.
        Log::captureFatal();
        Log::flush();
        throw new \ErrorException("FATAL ERROR ".$error['message'],$error['severity'], $error['type'], $error['file'], $error['line']);
    }
});


set_exception_handler(function (\Throwable $e) {
    // Always recorded, in both debug and production mode — previously an
    // uncaught exception in debug mode was printed to the browser and never
    // written down anywhere.
    Log::exception('uncaught', $e);
    Log::flush();

    if (!isDebugMode()) {
        if (isAjaxRequest()) {
            //http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unexpected error has occurred, kindly try later']);
        } else {
            //http_response_code(500);
            echo '<div style="margin:2rem auto;max-width:640px;border:1px solid #e0e0e0;border-radius:8px;padding:24px;font-family:system-ui,Segoe UI,Arial;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,.06)"><h2 style="margin:0 0 12px;font-size:20px;color:#b00020">Unexpected error</h2><p style="margin:0;color:#333">An unexpected error has occurred. Please try again later.</p></div>';
        }
        //exit();
    } else {
        //http_response_code(500);
        $trace = nl2br(htmlentities($e->getTraceAsString()));
        echo '<div style="margin:2rem auto;max-width:900px;border:2px solid #ffcc80;border-radius:8px;padding:24px;background:#fff7e6;font-family:system-ui,Segoe UI,Arial"><h2 style="margin:0 0 8px;color:#e65100">Debug Exception</h2><div style="margin:6px 0;color:#333"><strong>' . htmlentities(get_class($e)) . ':</strong> ' . htmlentities($e->getMessage()) . '</div><div style="margin:6px 0;color:#555">File: ' . htmlentities($e->getFile()) . ' Line: ' . htmlentities((string)$e->getLine()) . '</div><pre style="white-space:pre-wrap;background:#fff;border:1px solid #ffe0b2;padding:12px;border-radius:6px;overflow:auto;max-height:420px">' . $trace . '</pre></div>';
        //exit();
    }
});



// How long a session lasts. This read $env["SESSION_LIFETIME"], and $env does not
// exist anywhere - the runtime array is $runtime - so the ?? fallback always won and
// the setting in runtime.php was silently ignored. It went unnoticed because the
// configured value is 2592000, which is exactly the 30 days the fallback hardcodes.
$sessionLifetime = defined('SESSION_LIFETIME') ? (int)SESSION_LIFETIME : 30 * 24 * 60 * 60;

// Set session cookie lifetime (how long the session cookie lasts in the browser)
ini_set('session.cookie_lifetime', $sessionLifetime);

// Set session garbage collection lifetime (how long session data is kept on server)
ini_set('session.gc_maxlifetime', $sessionLifetime);

// Set session cache expire (for session cache limiter)
ini_set('session.cache_expire', $sessionLifetime / 60); // in minutes


session_start();
