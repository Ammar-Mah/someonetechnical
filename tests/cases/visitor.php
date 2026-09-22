<?php

/**
 * The visitor session: nobody signs in, each visitor gets an anonymous
 * identity, and the session cookie carries the flags policies/security.md
 * asks for.
 *
 * Every case runs in a fresh PHP process. This runner booted the framework
 * before its first case, so its own session is already started and its
 * output has begun - and neither a cookie flag nor a session id can change
 * after that.
 */

/**
 * Run $code in a fresh PHP process at the project root and return what it
 * printed, decoded from JSON.
 */
function visitor_run(string $code): array
{
    $script = tempnam(sys_get_temp_dir(), 'visitor');
    file_put_contents($script, "<?php\nchdir(" . var_export(ROOT, true) . ");\n" . $code);

    try {
        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
    } finally {
        @unlink($script);
    }

    $result = json_decode($stdout, true);
    ok(is_array($result), "the child process printed no JSON (exit $exit):\n" . $stdout . $stderr);
    return $result;
}

/** A page request's boot, output held back so the child prints only JSON. */
function visitor_boot(): string
{
    return <<<'PHP'
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        ob_start();
        require 'src/core/inc/initialize.inc.php';
        require 'src/core/inc/functions.inc.php';
        require 'src/app/boot.inc.php';

        PHP;
}

/**
 * The session cookie's flags once runtime.php has run, with its APP_URL on
 * $scheme, $_SERVER['HTTPS'] set to $https (null: absent), and the server's
 * own session.cookie_secure at $secureBefore.
 */
function visitor_cookie_flags(string $scheme, ?string $https, string $secureBefore): array
{
    $source = (string)file_get_contents(ROOT . '/runtime.php');
    $default = "'APP_URL' => 'http://";
    ok(str_contains($source, $default), 'runtime.php no longer has an http:// APP_URL default');

    // A copy in a folder of its own, so no server's settings are merged over it.
    $dir = sys_get_temp_dir() . '/visitor-' . bin2hex(random_bytes(6));
    mkdir($dir);
    file_put_contents($dir . '/runtime.php', str_replace($default, "'APP_URL' => '{$scheme}://", $source));

    $code = 'ini_set(\'session.cookie_secure\', ' . var_export($secureBefore, true) . ");\n"
        . ($https === null ? '' : '$_SERVER[\'HTTPS\'] = ' . var_export($https, true) . ";\n")
        . 'require ' . var_export($dir . '/runtime.php', true) . ";\n"
        . <<<'PHP'
        echo json_encode([
            'httponly' => ini_get('session.cookie_httponly'),
            'samesite' => ini_get('session.cookie_samesite'),
            'secure'   => ini_get('session.cookie_secure'),
        ]);
        PHP;

    try {
        return visitor_run($code);
    } finally {
        @unlink($dir . '/runtime.php');
        @rmdir($dir);
    }
}

group('visitor');

test('the starter no longer signs everyone in as user 1', function () {
    // The exact string the DEV probe and the checks' starter step look for.
    lacks("Session::set('user', 1)", (string)file_get_contents(ROOT . '/public/index.php'));
});

test('a new visitor gets an anonymous identity, a new session id and a new token', function () {
    $newVisitor = visitor_boot() . <<<'PHP'
        $before = ['id' => session_id(), 'token' => Csrf::token()];
        // The files handler keeps a session in sess_<id>. Regenerating with
        // true deletes the old file; with false it stays, and so does the
        // session for anyone holding the old id.
        $parts = explode(';', (string)session_save_path());
        $old = rtrim(end($parts) ?: sys_get_temp_dir(), '/\\') . '/sess_' . $before['id'];
        $oldBefore = is_file($old);
        require 'public/index.php';
        $html = ob_get_clean();
        $buffer = new ReflectionProperty('Log', 'buffer');
        $buffer->setAccessible(true);
        echo json_encode([
            'user'     => Session::get('user'),
            'newId'    => session_id() !== $before['id'],
            'oldGone'  => $oldBefore && !is_file($old),
            'logged'   => array_values(array_filter((array)$buffer->getValue(),
                fn(array $e): bool => $e['msg'] === 'visitor session started')),
            'newToken' => Csrf::token() !== $before['token'],
            'rendered' => str_contains($html, 'Does this sound familiar?'),
            // PHP refuses these once a session is active, so they are what
            // the framework's session_start() and the regeneration used.
            'cookie'   => [ini_get('session.cookie_httponly'), ini_get('session.cookie_samesite')],
        ]);
        session_destroy();
        PHP;

    $first = visitor_run($newVisitor);
    ok(preg_match('/^visitor:[0-9a-f]{32}$/', (string)$first['user']) === 1,
        'identity: ' . json_encode($first['user']));
    ok($first['newId'], 'the session id was not regenerated');
    ok($first['oldGone'], 'the old session was kept when the id was regenerated');
    same(1, count($first['logged']), 'the new visitor was not logged once');
    same(['auth', $first['user']], [$first['logged'][0]['ch'] ?? null, $first['logged'][0]['ctx']['visitor'] ?? null],
        'the log line does not name the visitor on the auth channel');
    ok($first['newToken'], 'the CSRF token was not rotated');
    ok($first['rendered'], 'the page did not render');
    same(['1', 'Lax'], $first['cookie'], 'the cookie flags were not in place when the session started');

    $second = visitor_run($newVisitor);
    ok($second['user'] !== $first['user'], 'two visitors were given the same identity');
});

test('a returning visitor keeps identity, session id and token', function () {
    $r = visitor_run(visitor_boot() . <<<'PHP'
        Session::set('user', 'visitor:0123456789abcdef0123456789abcdef');
        $before = ['id' => session_id(), 'token' => Csrf::token()];
        require 'public/index.php';
        ob_end_clean();
        echo json_encode([
            'user'      => Session::get('user'),
            'sameId'    => session_id() === $before['id'],
            'sameToken' => Csrf::token() === $before['token'],
        ]);
        session_destroy();
        PHP);

    same('visitor:0123456789abcdef0123456789abcdef', $r['user']);
    ok($r['sameId'], 'a returning visitor got a new session id');
    ok($r['sameToken'], 'a returning visitor got a new CSRF token');
});

test('the session cookie is HttpOnly and SameSite=Lax, and Secure wherever the site is https', function () {
    same(['httponly' => '1', 'samesite' => 'Lax', 'secure' => '0'],
        visitor_cookie_flags('http', null, '0'), 'a local http:// checkout keeps its session');
    same('1', visitor_cookie_flags('http', 'on', '0')['secure'], 'a request over HTTPS');
    same('0', visitor_cookie_flags('http', 'off', '0')['secure'], 'HTTPS reported as off');
    same('1', visitor_cookie_flags('https', null, '0')['secure'], 'a site whose APP_URL is https');
    same('1', visitor_cookie_flags('http', null, '1')['secure'], 'a server that already sets Secure');
});
