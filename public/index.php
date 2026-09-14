<?php

/**
 * Page entry point — the only place a full HTML document is produced.
 *
 * Everything after the first page load goes through updater.php instead, so
 * this file stays short: establish who the user is, pick a view, render it.
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
// updater.php refuses to dispatch ANY interaction without a session user, so
// something has to establish one before the app is usable.
//
// !! THE STARTER SIGNS EVERYONE IN AS USER 1. That is deliberate — it is what
// !! lets a fresh checkout be clicked through — and it is the first thing to
// !! replace. A real application authenticates here and renders its login view
// !! when nobody is signed in:
// !!
// !!     $userId = Session::get('user');
// !!     if (empty($userId)) { echo Template::view('login'); return; }
// !!
// !! On a successful login: Session::set('user', $id); Csrf::rotate();

if (empty(Session::get('user'))) {
    Session::set('user', 1);
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
