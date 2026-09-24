<!DOCTYPE html>
<?php
    $theme     = currentTheme();
    $language  = currentLanguage();
    $direction = languageDirection($language);
?>
<html lang="<?= e($language) ?>" dir="<?= e($direction) ?>" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
        // The CSRF token MUST travel on a meta tag, not in a <script>.
        // Template::process() moves every script to just before </body>, so an
        // inline script here would run after Baustein.js has already
        // self-initialised and fired its first requests — and those would go
        // out with no token and be rejected. A meta tag is untouched by that
        // pass and is in the DOM before any script executes.
    ?>
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">

    <?php
        // Each view names its title, description and path in sections. The
        // canonical address is the production site's on every server, so a
        // copy on DEV never competes with it; the share image is each server's
        // own, so DEV shows the one it serves (#72).
        $canonical = 'https://someonetechnical.com/';
    ?>
    <title>@yield('title')</title>
    <meta name="description" content="@yield('description')">
    <link rel="canonical" href="<?= e($canonical) ?>@yield('path')">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(Logo::appName()) ?>">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="<?= e($canonical) ?>@yield('path')">
    <meta property="og:image" content="<?= e(asset('img/og.png')) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">

    <?php // The framework stylesheet first, the application's second, so yours wins. ?>
    @css('css/Baustein.css')
    @css('css/app.css')

    <?php // Baustein.js is the runtime; ui.js is the components' client half. ?>
    @js('js/Baustein.js')
    @js('js/ui.js')
</head>
<body>
    @yield('content')
</body>
</html>
