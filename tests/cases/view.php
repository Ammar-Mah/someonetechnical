<?php

/**
 * The views, rendered the way a page load renders them.
 *
 * These exist because the component snapshot cannot see this layer. A helper
 * that hands a view a pre-rendered markup STRING gets it escaped — correctly,
 * since templates escape strings — and the page fills with &lt;div&gt;. Every
 * component still rendered perfectly on its own, so nothing else noticed.
 */

group('view');

/** A page load needs a session user; updater.php refuses without one. */
Session::set('user', 1);

test('the main view renders a whole document', function () {
    $html = Template::view('main');
    contains('<!DOCTYPE html>', $html);
    contains('<meta name="csrf-token"', $html, 'without this every interaction is refused');
    contains('</body>', $html);
});

test('NO MARKUP IS DOUBLE-ESCAPED ANYWHERE IN THE PAGE', function () {
    $html = Template::view('main');
    foreach (['&lt;div', '&lt;span', '&lt;svg', '&lt;button', '&lt;input', '&lt;table'] as $leak) {
        lacks($leak, $html, 'escaped markup reached the page — something returned an HTML string '
            . 'where a Component or raw() was needed');
    }
});

test('the shell, the sidebar and a screen are all present', function () {
    $html = Template::view('main');
    // Not class="app-header": the component's own class name is always
    // prepended, so the attribute reads "AppHeader app-header".
    contains('app-header', $html);
    contains('id="nav-home"', $html);
    contains('id="nav-items"', $html);
    contains('id="nav-settings"', $html);
    contains('id="app-content"', $html);
});

test('every screen renders through the view without escaping itself', function () {
    foreach (['home', 'items', 'settings'] as $screen) {
        State::set('demo.screen', $screen);
        $html = Template::view('main');
        lacks('&lt;div', $html, $screen . ' screen came out escaped');
        contains('id="' . $screen . '-screen"', $html, $screen . ' screen did not render');
    }
    State::set('demo.screen', 'home');
});

test('the stylesheets and scripts are linked with cache-busting urls', function () {
    $html = Template::view('main');
    contains('css/Baustein.css?v=', $html);
    contains('css/app.css?v=', $html);
    contains('js/Baustein.js?v=', $html);
    contains('js/ui.js?v=', $html);
});

test('scripts are deferred to the end of the body', function () {
    $html = Template::view('main');
    ok(strpos($html, 'js/Baustein.js') > strpos($html, '<body'),
        'Template::process moves every script before </body>');
});

test('the document reflects the current language and theme', function () {
    State::set('language', 'ar');
    State::set('theme', 'dark');
    $html = Template::view('main');
    contains('lang="ar"', $html);
    contains('dir="rtl"', $html);
    contains('data-theme="dark"', $html);

    State::set('language', 'en');
    State::set('theme', 'light');
    $html = Template::view('main');
    contains('dir="ltr"', $html);
    contains('data-theme="light"', $html);
});
