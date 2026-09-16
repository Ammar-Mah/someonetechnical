<?php

/**
 * What runtime.php resolves once a server's own files are merged over it.
 *
 * Each case runs a copy of runtime.php in a child process, in a folder of its
 * own beside the runtime.dev.php and runtime.local.php a server would hold -
 * the merge reads its own folder. This runner has already booted from the real
 * runtime.php, and its constants cannot be defined twice.
 */

/**
 * LOG_METRICS as runtime.php resolves it beside a runtime.dev.php holding
 * $dev and a runtime.local.php holding $local (null: the file is absent).
 */
function config_metrics(?array $dev, ?array $local): bool
{
    $dir = sys_get_temp_dir() . '/config-' . bin2hex(random_bytes(6));
    mkdir($dir);
    copy(ROOT . '/runtime.php', $dir . '/runtime.php');
    foreach (['runtime.dev.php' => $dev, 'runtime.local.php' => $local] as $name => $settings) {
        if ($settings !== null) {
            file_put_contents("$dir/$name", '<?php return ' . var_export($settings, true) . ';');
        }
    }
    file_put_contents("$dir/child.php",
        '<?php $settings = require __DIR__ . "/runtime.php"; echo json_encode($settings["LOG_METRICS"]);');

    try {
        // The framework's runner skips unmatched case files on a filtered
        // run, so this file cannot borrow another case file's helper.
        $process = proc_open([PHP_BINARY, "$dir/child.php"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    } finally {
        array_map('unlink', glob("$dir/*.php") ?: []);
        rmdir($dir);
    }

    ok(in_array($stdout, ['true', 'false'], true), "LOG_METRICS did not resolve to a boolean:\n" . $stdout . $stderr);
    return $stdout === 'true';
}

group('config');

test('the request summary is on for development and off everywhere else', function () {
    same(true, config_metrics(null, null), 'a checkout with no server files');
    same(true, config_metrics(
        ['APP_ENV' => 'development', 'APP_URL' => 'https://dev.example/public/', 'DEBUG_MODE' => true],
        null
    ), 'DEV, whose deployment writes runtime.dev.php without the key');
    same(false, config_metrics(null, ['APP_ENV' => 'production', 'DEBUG_MODE' => false]),
        'production, from a runtime.local.php that leaves the key out');
    same(false, config_metrics(null, ['APP_ENV' => 'staging']), 'an environment that is neither');
    same(false, config_metrics(null, ['APP_ENV' => '']), 'an environment left empty');
});

test('a server that sets the request summary itself keeps its choice', function () {
    same(false, config_metrics(['APP_ENV' => 'development'], ['LOG_METRICS' => false]),
        'development, turned off by the server');
    same(true, config_metrics(null, ['APP_ENV' => 'production', 'LOG_METRICS' => true]),
        'production, turned on for a day of profiling');
});
