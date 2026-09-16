<?php

/**
 * The page shell: SiteHeader and SiteFooter, the frame every section sits in.
 *
 * Their links are a contract with work that is not on the page yet — the
 * section ids that #11 and #12 take from SiteHeader's constants, the ?page=
 * views #15 and #17 add — so a link that drifts has nothing else to notice it.
 * Links are compared in source order because source order is Tab order.
 */

/** [href, text] for every link in $html, in source order. */
function site_links(string $html): array
{
    preg_match_all('/<a\b[^>]*\bhref="([^"]*)"[^>]*>([^<]*)<\/a>/', $html, $matches, PREG_SET_ORDER);
    return array_map(fn(array $m): array => [$m[1], $m[2]], $matches);
}

group('site');

test('the header names the site and links to both sections and the intake', function () {
    same([
        ['./', Logo::appName()],
        ['#how-it-works', 'How it works'],
        ['#what-we-help-with', 'What we help with'],
        ['?page=start', 'Get someone technical'],
    ], site_links((string)SiteHeader::make('site-header')));
});

test('the footer carries every item PRODUCT.md §10 lists', function () {
    $html = (string)SiteFooter::make('site-footer');

    same([
        ['#how-it-works', 'How it works'],
        ['#what-we-help-with', 'What we help with'],
        ['?page=start', 'Book a session'],
        ['?page=privacy', 'Privacy'],
        ['?page=terms', 'Terms'],
        ['?page=contact', 'Contact'],
    ], site_links($html));

    contains('>' . Logo::appName() . '</p>', $html);
    contains('>someonetechnical.com</p>', $html);
    contains('>Human technical help for people building with AI.</p>', $html);
});

test('the page never names the framework it is built on', function () {
    // Read the way Issue #6 reads it: the whole page with its tags removed.
    $text = preg_replace('/<[^>]*>/', '', Template::view('main'));
    ok(stripos($text, 'baustein') === false,
        'PRODUCT.md allows no stack or implementation detail on the page');
});

test('the how-it-works section carries the anchor every link points at', function () {
    $html = (string)HowItWorksSection::make(SiteHeader::HOW_IT_WORKS);

    // The header and the footer link to '#' . SiteHeader::HOW_IT_WORKS; this
    // is the other end of that contract. Written literally, not through the
    // constant, so renaming the constant cannot make both sides agree on a
    // target no link in the page shell uses.
    contains('id="how-it-works"', $html);
    same('how-it-works', SiteHeader::HOW_IT_WORKS);
});

test('the how-it-works section holds the three steps in PRODUCT order', function () {
    $html = (string)HowItWorksSection::make(SiteHeader::HOW_IT_WORKS);

    $steps = [
        ['Show us where you are stuck', 'Tell us what you are building and what is happening. Plain language is completely fine.'],
        ['Meet someone technical', 'Join a one-to-one session with an experienced engineer who can inspect the situation with you.'],
        ['Leave with progress', 'Resolve the issue during the session where possible, or receive a clear explanation and practical next steps.'],
    ];

    $at = -1;
    foreach ($steps as [$title, $explanation]) {
        $titleAt = strpos($html, '>' . $title . '</h3>');
        ok($titleAt !== false, "step heading missing: $title");
        ok($titleAt > $at, "step out of order: $title");

        $textAt = strpos($html, '>' . $explanation . '</p>');
        ok($textAt !== false, "step explanation missing: $title");
        ok($textAt > $titleAt, "explanation precedes its heading: $title");

        $at = $textAt;
    }
});

test('the how-it-works action leads to the intake', function () {
    same(
        [['?page=start', 'Get someone technical']],
        site_links((string)HowItWorksSection::make(SiteHeader::HOW_IT_WORKS))
    );
});

test('no step text is hidden behind a hover or a click', function () {
    // AC3. The steps are server-rendered and visible from the first paint, so
    // no rule may hide .step-text or .step-title, and the only motion is an
    // entrance the reduced-motion block switches off.
    $css = (string)file_get_contents(ROOT . '/public/css/app.css');

    foreach (['.step-title', '.step-text', '.recognition-situation'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '[^{]*\{[^}]*(display:\s*none|visibility:\s*hidden|opacity:\s*0)\b/', $css),
            "$selector is hidden by a rule");
    }

    contains('@media (prefers-reduced-motion: reduce)', $css);
});

test('the recognition section states its heading and its closing line', function () {
    $html = (string)RecognitionSection::make('recognition');

    contains('>Does this sound familiar?</h2>', $html);
    contains('>You do not need to hire an entire development agency. You may just need someone technical.</p>', $html);
});

test('the recognition section states all six situations from PRODUCT.md §2', function () {
    $html = (string)RecognitionSection::make('recognition');

    // Word for word, including the typographic quotes and apostrophes, because
    // the acceptance criterion on #11 says word for word and the DEV check
    // greps for exactly these bytes.
    $situations = [
        '“It works in preview, but I don’t know how to put it online.”',
        '“The AI changed something and now login is broken.”',
        '“I connected Stripe, but I’m not sure it is safe.”',
        '“It keeps telling me to update an environment variable.”',
        '“I have users coming. Is this actually ready?”',
        '“I don’t even know what question I should be asking.”',
    ];

    foreach ($situations as $situation) {
        contains($situation, $html);
    }

    same(6, substr_count($html, 'recognition-situation"'), 'one list item per situation');
});

test('no page component reads copy from a path the deployment never uploads', function () {
    // THIS IS THE CASE THAT WOULD HAVE CAUGHT #11's DEFECT.
    //
    // The DEV and production packages exclude the repository's own material:
    // docs/, tests/, captures/, the agent folders, LLM.txt and every Markdown
    // file - php-deploy-dev.yml's "never" list, and .deployignore's header.
    // A component that reads page copy from one of those renders perfectly
    // locally and in CI, where the whole repository is on disk, and silently
    // loses that copy on every deployed environment. Nothing else in the suite
    // can see the difference, because the suite always runs with the full
    // repository present.
    //
    // Only string LITERALS are inspected, through token_get_all(), so a
    // docblock or a comment that merely names docs/ does not trip this.
    $excluded = '#^(docs|tests|captures|node_modules|\.agent|\.github|\.claude|\.codex)/|^LLM\.txt$|\.md$#';

    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ROOT . '/src/app', RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        foreach (token_get_all((string)file_get_contents($file->getPathname())) as $token) {
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $value = trim($token[1], "'\"");
            if ($value !== '' && preg_match($excluded, $value)) {
                $offenders[] = $file->getFilename() . ':' . $token[2] . ' → ' . $value;
            }
        }
    }

    same([], $offenders,
        'page copy must live in its component, not in a path the deployment excludes');
});
