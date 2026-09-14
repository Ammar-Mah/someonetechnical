<?php

/**
 * Session-bound CSRF token.
 *
 * updater.php accepts state-changing POSTs from anywhere with no proof the
 * request came from the application, so any page a signed-in user visited could
 * drive the app on their behalf. Every request now has to carry the token that
 * was issued with the page.
 *
 * No dependencies: the token lives in $_SESSION and travels in the POST body.
 */
final class Csrf
{
    private const KEY = 'csrf_token';

    /** Issue the token for this session, creating it on first use. */
    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            // random_bytes should not fail; fall back rather than break the page.
            $token = hash('sha256', uniqid('', true) . microtime(true));
        }

        Session::set(self::KEY, $token);
        return $token;
    }

    /** True when $candidate matches the session token. Timing-safe. */
    public static function check(mixed $candidate): bool
    {
        $expected = Session::get(self::KEY);
        if (!is_string($expected) || $expected === '') return false;
        if (!is_string($candidate) || $candidate === '') return false;
        return hash_equals($expected, $candidate);
    }

    /** Rotate the token — call after a privilege change such as login. */
    public static function rotate(): void
    {
        Session::set(self::KEY, '');
        self::token();
    }
}
