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

    <title><?= e(Logo::appName()) ?></title>
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
