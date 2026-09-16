<?php

/**
 * Page entry point — the only place a full HTML document is produced.
 *
 * Everything after the first page load goes through updater.php instead, so
 * this file stays short: establish who the visitor is, pick a view, render it.
 *
 * Reach it through the ROOT index.php, which includes this after booting.
 * Requesting /public/index.php directly also renders the page — the guard
 * below boots the framework itself — but every interaction on that page then
 * fails: Baustein.js resolves the endpoint relative to the page's directory,
 * so it posts to /public/updater.php, which does not exist. Never link here.
 */

if (!class_exists('Session')) {
    $root = dirname(__DIR__);
    require_once $root . '/src/core/inc/initialize.inc.php';
    require_once $root . '/src/core/inc/functions.inc.php';
    require_once $root . '/src/app/boot.inc.php';
}

// -----------------------------------------------------------------------------
// Who is this?
// -----------------------------------------------------------------------------
// Nobody signs in: the site has no accounts. updater.php refuses to dispatch
// an interaction without a session user, so a visitor without one is given an
// anonymous identity here. It is random and unrelated to the session id, which
// must never reach the log - the request summary records the session user.
//
// Gaining that identity is the session's one privilege change, so the session
// id is regenerated and the CSRF token rotated at that moment
// (policies/security.md). CSRF still guards every interaction.

if (empty(Session::get('user'))) {
    session_regenerate_id(true);
    $visitor = 'visitor:' . bin2hex(random_bytes(16));
    Session::set('user', $visitor);
    Csrf::rotate();
    Log::info('auth', 'visitor session started', [
        'visitor' => $visitor,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

// -----------------------------------------------------------------------------
// Routing
// -----------------------------------------------------------------------------
// One view per page. Add entries as the app grows; anything more elaborate
// than this (path segments, parameters) is a router, and belongs in its own
// class rather than here.

$page  = (string)($_GET['page'] ?? 'main');
$views = ['main'];

if (!in_array($page, $views, true)) {
    $page = 'main';
}

echo Template::view($page);
