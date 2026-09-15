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

test('the header, the main landmark and the footer are all present', function () {
    $html = Template::view('main');
    // By id, not class="site-header": the component's own class name is
    // always prepended, so the attribute reads "SiteHeader site-header".
    contains('<header id="site-header"', $html);
    contains('<main id="main"', $html);
    contains('<footer id="site-footer"', $html);
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
