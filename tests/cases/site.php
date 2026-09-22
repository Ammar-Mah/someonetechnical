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

/**
 * Every style rule in app.css, in source order, as ['selectors', 'body', 'at',
 * 'reduced']: 'at' is its offset, 'reduced' says it sits in a
 * prefers-reduced-motion: reduce block. Rules inside @media and @supports are
 * included; the frames of a @keyframes are not.
 */
function site_rules(): array
{
    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));
    preg_match_all('/([^{}]*)([{}])/', $css, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    $rules = [];
    $open = [];
    foreach ($tokens as [, [$text, $at], [$brace]]) {
        if ($brace === '{') {
            $open[] = str_starts_with(trim($text), '@') ? trim($text) : ['selectors' => $text, 'at' => $at];
            continue;
        }
        $block = array_pop($open);
        $atRules = implode(' ', array_filter($open, 'is_string'));
        if (is_array($block) && !str_contains($atRules, '@keyframes')) {
            $rules[] = [
                'selectors' => array_map(fn(string $s): string => preg_replace('/\s+/', ' ', trim($s)), explode(',', $block['selectors'])),
                'body'      => $text,
                'at'        => $block['at'],
                'reduced'   => str_contains($atRules, 'prefers-reduced-motion: reduce'),
            ];
        }
    }
    return $rules;
}

/** [ids, classes, types] of one selector: a later rule wins only at equal or higher. */
function site_specificity(string $selector): array
{
    return [
        preg_match_all('/#[\w-]+/', $selector),
        preg_match_all('/\.[\w-]+|\[[^\]]*\]|(?<!:):(?!:)[\w-]+/', $selector),
        preg_match_all('/::[\w-]+|(?:^|[\s>+~])[a-z][\w-]*/i', $selector),
    ];
}

/**
 * The animated selectors $animated picks out, each with whether reduced motion
 * really switches it off: a later reduced-motion rule sets animation: none on
 * a selector $covers pairs with it, at no lower specificity, and !important
 * where the animation is. A media query adds no specificity, so a block moved
 * above its animation, or outranked by it, switches nothing off.
 */
function site_motion(callable $animated, callable $covers): array
{
    $rules = site_rules();
    $sets = '/\banimation(?:-name)?\s*:\s*(?!none\b)[^;]*/';
    $clears = '/\banimation(?:-name)?\s*:\s*none\b[^;]*/';

    $found = [];
    foreach ($rules as $rule) {
        if ($rule['reduced'] || !preg_match($sets, $rule['body'], $set)) {
            continue;
        }
        foreach (array_filter($rule['selectors'], $animated) as $selector) {
            $found[$selector] = ($found[$selector] ?? true) && (bool)array_filter($rules,
                fn(array $r): bool => $r['reduced'] && $r['at'] > $rule['at']
                    && preg_match($clears, $r['body'], $clear)
                    && (!str_contains($set[0], '!important') || str_contains($clear[0], '!important'))
                    && (bool)array_filter($r['selectors'], fn(string $s): bool => $covers($s, $selector)
                        && site_specificity($s) >= site_specificity($selector)));
        }
    }
    return $found;
}

group('site');

test('the header names the site and links to both sections and the intake', function () {
    same([
        ['./', Logo::appName()],
        ['./#how-it-works', 'How it works'],
        ['./#what-we-help-with', 'What we help with'],
        ['?page=start', 'Get someone technical'],
    ], site_links((string)SiteHeader::make('site-header')));
});

test('the footer carries every item PRODUCT.md §10 lists', function () {
    $html = (string)SiteFooter::make('site-footer');

    same([
        ['./#how-it-works', 'How it works'],
        ['./#what-we-help-with', 'What we help with'],
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

    // The header and the footer link to './#' . SiteHeader::HOW_IT_WORKS; this
    // is the other end of that contract. Written literally, not through the
    // constant, so renaming the constant cannot make both sides agree on a
    // target no link in the page shell uses.
    contains('id="how-it-works"', $html);
    same('how-it-works', SiteHeader::HOW_IT_WORKS);
});

test('the how-it-works section holds the three steps in PRODUCT order, each a drawing, a title and one short line', function () {
    $html = (string)HowItWorksSection::make(SiteHeader::HOW_IT_WORKS);

    // #67: PRODUCT.md §3's titles, a drawing each, and a line a visitor takes
    // in at a glance instead of §3's longer explanations.
    preg_match_all('/<li class="step"><span class="step-picture" aria-hidden="true"><svg class="pictogram"[^>]*>.*?<\/svg>'
        . '<span class="step-index">(\d)<\/span><\/span><h3 class="step-title">([^<]*)<\/h3><p class="step-text">([^<]*)<\/p><\/li>/s',
        $html, $steps, PREG_SET_ORDER);

    same(['1', '2', '3'], array_column($steps, 1));
    same(['Show us where you are stuck', 'Meet someone technical', 'Leave with progress'], array_column($steps, 2));
    foreach ($steps as [, , $title, $line]) {
        $words = str_word_count(html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        ok($words >= 3 && $words <= 12, "the line under \"$title\" is $words words, not one short line");
    }
    same([], site_links($html), 'the steps hold no link: the actions are above and below them');
});

test('no step text is hidden behind a hover or a click', function () {
    // The steps are server-rendered and visible from the first paint, so no
    // rule may hide .step-text or .step-title, and the only motion is an
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

test('the hero states the headline, one line, both actions and the availability note', function () {
    $html = (string)HeroSection::make('hero');

    // PRODUCT.md §1's headline, its turn set apart; typographic apostrophes.
    contains('>Your AI built the app. <span class="hero-turn">Now you need someone technical.</span></h1>', $html);
    contains('>One-to-one help from an experienced engineer, for the parts your AI keeps talking around.</p>', $html);
    contains('>Bring the problem. You don’t need to know what it’s called.</p>', $html);

    same([
        ['?page=start', 'Get someone technical'],
        ['#how-it-works', 'See how it works'],
    ], site_links($html));
});

test('the hero drawing is hidden from assistive technology and is the settled drawing', function () {
    $html = (string)HeroSection::make('hero');

    // One hidden card and no live region, so nothing is ever announced.
    same(1, substr_count($html, 'aria-hidden="true"'), 'the card, and only the card, is hidden');
    contains('<div class="hero-card" aria-hidden="true">', $html);
    lacks('aria-live', $html);

    // #67: the tangle and the straight line, each measured as 1 so a dash of
    // 1 draws it; the markup is the finished drawing, the motion leads to it.
    contains('>Still asking AI…</span>', $html);
    contains('>Someone technical joined</span>', $html);
    ok(preg_match('/<path class="hero-tangle" pathLength="1" d="[^"]+"\/>/', $html) === 1, 'the tangle is missing');
    ok(preg_match('/<path class="hero-line" pathLength="1" d="M40 100H360"\/>/', $html) === 1, 'the line does not run straight');
    same(1, substr_count($html, 'class="hero-reply"'), 'one reply');
});

test('the page opens with the hero, which holds its only first-level heading', function () {
    $page = Template::view('main');

    same(1, substr_count($page, '<h1'), 'one <h1> on the page');
    $hero = strpos($page, 'comp="HeroSection"');
    $recognition = strpos($page, 'comp="RecognitionSection"');
    ok($hero !== false && $recognition !== false && $hero < $recognition, 'the hero is not above the recognition section');
    ok(strpos($page, '<main') < $hero, 'the hero is not inside <main>');
});

test('the hero\'s words never move, and reduced motion stops its drawing', function () {
    $css = (string)file_get_contents(ROOT . '/public/css/app.css');

    // The words are the first paint, so none of them is animated.
    foreach (['.hero-copy', '.hero-title', '.hero-turn', '.hero-lede', '.hero-actions', '.hero-more', '.hero-note'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*animation/', $css), "$selector is animated");
    }

    // The styles are the settled drawing, so switching every animation in the
    // card off is the whole reduced-motion rule.
    ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.hero-card,\s*\.hero-card \*,\s*'
        . '\.hero-card \*::before\s*\{\s*animation: none;/', $css) === 1,
        'the reduced-motion rule no longer stops every animation in the card');

    // That holds only while each keyframe runs FROM its first frame TO the
    // styles: written `to`, the styles become the start, and the card
    // settles somewhere else.
    foreach (['hero-in', 'hero-draw', 'hero-fade', 'hero-pop'] as $name) {
        ok(preg_match('/@keyframes ' . $name . '\s*\{\s*from\s*\{[^{}]*\}\s*\}/', $css) === 1,
            "@keyframes $name no longer runs from a frame to the styles");
    }

    // And only while the rule wins: every animation in the card is switched off
    // by a later reduced-motion rule of at least its specificity.
    $motion = site_motion(fn(string $s): bool => str_contains($s, '.hero-'),
        fn(string $r, string $a): bool => $r === $a
            || $r === '.hero-card *' . (preg_match('/::[\w-]+$/', $a, $m) ? $m[0] : ''));
    ok(count($motion) >= 7, 'the card\'s animations were not found: ' . implode(', ', array_keys($motion)));
    same([], array_keys(array_filter($motion, fn(bool $off): bool => !$off)), 'reduced motion does not stop these');
});

test('the page names no price', function () {
    // The whole page as a visitor reads it: the text with scripts, styles and
    // tags removed.
    $page = Template::view('main');
    $text = preg_replace('/<[^>]*>/', ' ', preg_replace('#<(script|style)\b.*?</\1>#is', '', $page));

    ok(!preg_match('/[$€£¥₹¢]/u', $text), 'the page shows a currency symbol');
    ok(!preg_match('/\b(usd|eur|gbp|dollars?|euros?|pounds?|cents?)\b/i', $text), 'the page names a currency');
    ok(!preg_match('/\bper\s+(hour|session|month|week|day)\b|\bhourly\b|\/\s*(h|hr|hour|mo|month)\b/i', $text),
        'the page states a rate');
});

test('the page is five sections inside <main>, in PRODUCT.md order, and short', function () {
    $page = Template::view('main');

    $at = strpos($page, '<main');
    foreach (['HeroSection', 'RecognitionSection', 'HowItWorksSection', 'SupportAreasSection', 'FinalCtaSection'] as $comp) {
        $compAt = strpos($page, 'comp="' . $comp . '"');
        ok($compAt !== false && $compAt > $at, "$comp is missing or out of order");
        $at = $compAt;
    }
    ok($at < strpos($page, '</main>'), 'the final call to action is not inside <main>');
    same(5, substr_count($page, '<section '), 'five sections');

    // #67 AC1: what a visitor reads in <main> is 300 words at most (797 before).
    ok(preg_match('#<main\b[^>]*>(.*)</main>#s', $page, $main) === 1, 'no <main>');
    $text  = html_entity_decode(preg_replace('/<[^>]*>/', ' ', $main[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    ok($words <= 300, "<main> reads $words words");
});

test('the final call to action states PRODUCT.md §9 and leads to the intake', function () {
    $html = (string)FinalCtaSection::make('final-cta');

    // §9's own typography. The Issue quotes these two sentences with a straight
    // apostrophe; the page carries PRODUCT.md's, as the hero does.
    contains('>You’ve asked the AI enough.</h2>', $html);
    contains('>Show the problem to someone who can understand the project, explain what is happening and help you move forward.</p>', $html);
    contains('>You don’t need to diagnose the problem before contacting us.</p>', $html);
    contains('>Real engineers · Plain language · You stay in control</p>', $html);

    same([['?page=start', 'Get someone technical']], site_links($html));
    ok(strpos($html, 'href="?page=start"') < strpos($html, '>You don’t need to diagnose'), 'the note comes before the action');
});

test('the page shows no testimonial, rating, star, customer count or partner logo', function () {
    // PRODUCT.md §8 builds trust from principles alone. Looked for in the
    // markup, where a logo or a review would be an element, and in the text a
    // visitor reads, its entities decoded so that &#9733; is the star it shows.
    $page = Template::view('main');
    $text = preg_replace('/<[^>]*>/', ' ', preg_replace('#<(script|style)\b.*?</\1>#is', '', $page));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $css  = (string)file_get_contents(ROOT . '/public/css/app.css');

    foreach (['<img', '<picture', '<image', '<use', '<blockquote', '<cite', '<q>', '<q ', 'itemprop', 'ld+json'] as $markup) {
        lacks($markup, $page, "the page carries $markup");
    }

    // #67 brings drawings, and each is the page's own: a pictogram or the
    // hero's, never a picture fetched from anywhere.
    preg_match_all('/<svg\b[^>]*>/', $page, $svgs);
    ok(count($svgs[0]) > 0, 'the page has no drawings');
    foreach ($svgs[0] as $svg) {
        ok(strpos($svg, 'class="pictogram"') !== false || strpos($svg, 'class="hero-drawing"') !== false, "a drawing that is not the page's own: $svg");
    }
    lacks('href=', implode('', $svgs[0]), 'a drawing links out');

    ok(!preg_match('/\b(testimonials?|ratings?|rated|stars?|logos?|trusted by|reviews? from|out of \d)\b/i', $text),
        'the page names a testimonial, a rating, a star or a logo');
    ok(!preg_match('/[★☆⭐✩✪✫✬✭✮✯✰]/u', $text . $page . $css) && !preg_match('/\\\\(2605|2606|2b50)\b/i', $css),
        'the page shows a star');
    ok(!preg_match('/\d[\d.,]*\s*[k%]?\+?\s*(happy\s+|satisfied\s+)?(customers|clients|users|founders|builders|teams|companies|projects|sessions)\b/i', $text),
        'the page counts its customers');

    $final = html_entity_decode(strip_tags((string)FinalCtaSection::make('final-cta')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    ok(!preg_match('/\d/', $final), 'the final call carries a figure');
});

test('the final call never hides, keeps the focus outline, and reduced motion stops its light', function () {
    // Comments removed, so a selector is only ever the text before its brace.
    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));

    foreach (['.final-cta-heading', '.final-cta-text', '.final-cta-note', '.final-cta-principles'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*(display:\s*none|visibility:\s*hidden|opacity:\s*0)\b/', $css),
            "$selector is hidden by a rule");
    }

    ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.final-cta-note::before\s*\{\s*animation: none;/', $css) === 1,
        'reduced motion no longer stops the light');

    // Focus stays visible: no rule on the page removes the outline, and on the
    // ink band the outline is the accent, as on the footer.
    preg_match_all('/([^{}]+)\{[^}]*\boutline(-style)?:\s*(none|0)\b/', $css, $cleared);
    foreach ($cleared[1] as $selectors) {
        ok(!preg_match('/\.(hero|final-cta|support-area|step|recognition)/', $selectors), 'the focus outline is removed on ' . trim($selectors));
    }
    ok(preg_match('/(?:^|\})\s*\.hero\s*\{[^}]*--focus:\s*var\(--signal\)/', $css) === 1, 'the ink hero no longer sets the focus outline to the accent');
});

test('every "Get someone technical" action sits in a flex row, where it lifts and presses in', function () {
    // #51. The lift and press of .site-cta are transforms, and a transform does
    // not apply to an inline box. In a flex row an action is laid out as a box.
    // #53: the action must be the row's own child - wrapped in a <span>, the
    // span is the box and the link is inline again - and no rule for the row,
    // later in the file or inside a media query, may set another display.
    $rules = site_rules();
    $doc = new DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . Template::view('main'));
    libxml_clear_errors();
    libxml_use_internal_errors($errors);
    $find = new DOMXPath($doc);
    $class = fn(string $name): string => "contains(concat(' ', normalize-space(@class), ' '), ' $name ')";

    $rows = ['site-header-actions', 'hero-actions', 'support-areas-action', 'final-cta-action'];
    foreach ($rows as $row) {
        same(1, $find->query("//*[{$class($row)}]")->length, "the page has not one .$row");
        same(1, $find->query("//*[{$class($row)}]/a[{$class('site-cta')}]")->length, "the action is not .$row's own child");

        $displays = [];
        foreach ($rules as $rule) {
            $forRow = array_filter($rule['selectors'], fn(string $s): bool => preg_match('/\.' . preg_quote($row, '/') . '(?![\w-])[^\s>+~]*$/', $s) === 1);
            if ($forRow && preg_match('/\bdisplay:\s*([\w-]+)/', $rule['body'], $m)) {
                $displays[] = $m[1];
            }
        }
        ok($displays !== [] && array_unique($displays) === ['flex'], ".$row is not a flex row everywhere: " . implode(', ', $displays));
    }
    same(count($rows), $find->query("//a[{$class('site-cta')}]")->length, 'an action sits outside the rows');
});

test('the support areas section carries the anchor every "What we help with" link points at', function () {
    $html = (string)SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH);

    // The other end of the header's and footer's './#' . SiteHeader::WHAT_WE_HELP_WITH,
    // written literally, as for how it works.
    contains('id="what-we-help-with"', $html);
    same('what-we-help-with', SiteHeader::WHAT_WE_HELP_WITH);
    same(1, substr_count(Template::view('main'), 'id="what-we-help-with"'), 'one target on the page');
});

test('the support areas section shows the twelve areas of PRODUCT.md §4, each a drawing and its name', function () {
    $html = (string)SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH);

    contains('>What we help with</h2>', $html);

    // §4's names word for word and in order; #67: a drawing each, and no
    // explanation beneath.
    $areas = [
        'Deployment and hosting',
        'Domains and email',
        'Databases and storage',
        'Authentication and permissions',
        'Payments and subscriptions',
        'APIs and integrations',
        'Security and secrets',
        'Backups and monitoring',
        'Broken builds and unexpected errors',
        'Production and launch readiness',
        'Architecture and platform decisions',
        'Understanding what the AI actually created',
    ];

    preg_match_all('/<li class="support-area"><svg class="pictogram"[^>]*>(.*?)<\/svg><span class="support-area-name">([^<]*)<\/span><\/li>/s', $html, $entries, PREG_SET_ORDER);
    same($areas, array_map(fn(array $e): string => html_entity_decode($e[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $entries));
    same(12, count(array_unique(array_column($entries, 1))), 'two areas share a drawing');
    lacks('support-area-text', $html);
});

test('the support areas end with a note and the action to the intake', function () {
    $html = (string)SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH);

    contains('>Not on the list? Bring it anyway.</span>', $html);
    same([['?page=start', 'Get someone technical']], site_links($html));
});

test('the page uses none of the words PRODUCT.md rules out', function () {
    $text = html_entity_decode(strip_tags(Template::view('main')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    ok(!preg_match('/revolutionary|cutting[- ]?edge|empower|unlock|seamless/i', $text), 'the page uses a word PRODUCT.md rules out');
});

test('the sections rise into view on the scroll, never hide their text, and reduced motion stops them', function () {
    // Comments removed, so a selector is only ever the text before its brace.
    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));
    $group = '\.recognition-situation,\s*\.step,\s*\.support-area,\s*\.final-cta-panel\s*\{';

    // #67 AC4: tied to the scroll inside @supports, so a browser without scroll
    // timelines shows the settled page; the keyframe runs FROM an offset.
    ok(preg_match('/@supports \(animation-timeline: view\(\)\)\s*\{\s*' . $group . '\s*animation: reveal linear both;\s*animation-timeline: view\(\);/', $css) === 1,
        'the reveal is not tied to the scroll inside @supports');
    ok(preg_match('/@keyframes reveal\s*\{\s*from\s*\{/', $css) === 1, 'the reveal does not run from an offset to the styled page');
    ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*' . $group . '\s*animation: none;/', $css) === 1,
        'reduced motion no longer stops the reveal');
    ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.support-area\s*\{\s*transition: none;/', $css) === 1,
        'reduced motion no longer stops the tags\' hover');

    // Present is not enough: each section's animation is switched off by a
    // later reduced-motion rule of at least its specificity.
    $motion = site_motion(fn(string $s): bool => preg_match('/\.(recognition|step|support-area|final-cta)/', $s) === 1,
        fn(string $r, string $a): bool => $r === $a);
    ok(count($motion) >= 5, 'the sections\' animations were not found: ' . implode(', ', array_keys($motion)));
    same([], array_keys(array_filter($motion, fn(bool $off): bool => !$off)), 'reduced motion does not stop these');

    foreach (['.support-area', '.support-area-name', '.support-areas-note', '.step', '.final-cta-panel'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*(display:\s*none|visibility:\s*hidden|opacity:\s*0)\b/', $css),
            "$selector is hidden by a rule");
    }
});

// -----------------------------------------------------------------------------
// The never-deployed guard
// -----------------------------------------------------------------------------
// The DEV and production packages leave out the repository's own material. A
// page that reads its copy from there renders perfectly locally and in CI,
// where the whole repository is on disk, and loses that copy on the server.
// No rendering test can see the difference, because the suite always runs
// with the repository whole - #11 shipped exactly that, and only DEV caught
// it.
//
// So the guard scans the application's PHP for a path into that material. It
// is a best-effort check for the common spellings, not a proof: DEV
// validation is what shows the copy is on the page. A string that is nothing
// but a never-deployed name - 'tests' as an array key, '.md' as a suffix -
// counts as a path, whether the code uses it as one or not.

/**
 * What a deployment leaves out, entry for entry: php-deploy-dev.yml's "never"
 * list, then what php-deploy-prod.yml leaves out besides. Copy read from one
 * of the latter is on DEV and missing only in production, where nothing is
 * validated. Two of production's entries are not here. runtime.php merges
 * runtime.dev.php on purpose where it exists. The DEV tooling folder is left
 * to the checks, whose stray-reference step refuses its name in every file
 * this guard reads. A trailing / marks a folder; * matches within one name.
 */
function site_never_deployed(): array
{
    return [
        '.git/', '.github/', '.gitignore', '.gitattributes',
        '.agent/', '.claude/', '.codex/',
        '*.md', '/docs/', 'LLM.txt', '/captures/',
        'tests/', 'node_modules/', '.deployignore', '.deployignore.production',
        '.editorconfig', 'phpunit.xml', 'phpunit.xml.dist', 'phpstan.neon',
        'phpstan.neon.dist', '.php-cs-fixer.php', '.php-cs-fixer.dist.php',
        // Production only
        'Dev/', '.dev-state.json', '.dev-commit',
        'seeds/', 'fixtures/', 'phpcs.xml', 'phpcs.xml.dist',
        'composer.lock', 'package.json', 'package-lock.json',
    ];
}

/**
 * One pattern for a path into any never-deployed entry, where \0 stands for a
 * part the code computes. A folder matches when a / follows it, or when it
 * ends a path: "/docs" is a path, "Read the docs" is not. A leading / in the
 * list anchors an entry at the project root, but where a PHP path starts
 * cannot be read from its text, so here every entry matches at any depth.
 */
function site_never_deployed_pattern(): string
{
    $alternatives = [];
    foreach (site_never_deployed() as $entry) {
        $name = str_replace('\*', '[\w.-]*', preg_quote(trim($entry, '/'), '#'));
        $alternatives[] = str_ends_with($entry, '/')
            ? '(?<![\w.-])' . $name . '/|(?:^|[/\x00])' . $name . '(?:\x00|\z)'
            : '(?<![\w.-])' . $name . '(?![\w.-])';
    }
    return '#' . implode('|', $alternatives) . '#';
}

/**
 * A literal's text as PHP reads it, given what opened the string: ' or ", or
 * a heredoc's or a nowdoc's opening line. A single-quoted string reads \\ and
 * \', a nowdoc reads nothing, and a double-quoted string and a heredoc read
 * PHP's escapes - \" only between double quotes. A component's template is a
 * single-quoted string, so a literal inside one of its blocks is written \'.
 */
function site_unescaped(string $text, string $opening): string
{
    if ($opening === "'") {
        return strtr($text, ['\\\\' => '\\', "\\'" => "'"]);
    }
    if (str_contains($opening, "'")) {
        return $text;
    }

    return preg_replace_callback('/\\\\(?:x([0-9A-Fa-f]{1,2})|([0-7]{1,3})|u\{([0-9A-Fa-f]+)\}|(.))/s',
        function (array $escape) use ($opening): string {
            [, $hex, $octal, $codepoint, $char] = $escape + ['', '', '', '', ''];
            if ($hex !== '') {
                return chr(hexdec($hex));
            }
            if ($octal !== '') {
                return chr(octdec($octal) & 0xFF);
            }
            if ($codepoint !== '') {
                $number = hexdec($codepoint);
                return is_int($number) && $number <= 0x10FFFF ? (string)mb_chr($number, 'UTF-8') : '';
            }
            $simple = ['n' => "\n", 't' => "\t", 'r' => "\r", 'v' => "\v", 'e' => "\e", 'f' => "\f",
                '\\' => '\\', '$' => '$'];
            if ($char === '"' && $opening === '"') {
                return '"';
            }
            return $simple[$char] ?? '\\' . $char;
        }, $text);
}

/**
 * The strings a PHP source spells, joined as the program joins them: pieces
 * across . and .=, the literal parts of interpolated strings and heredocs, and
 * \0 for each part the code computes - a variable, a constant, a call. Each
 * literal is read with its escapes undone. Comments are not code and are
 * skipped.
 *
 * @return array<int, array{int, string}> [line, string]
 */
function site_spelled_strings(string $source, int $firstLine = 1): array
{
    // What a computed operand is made of. Any other token ends the string.
    $operand = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE,
        T_VARIABLE, T_DIR, T_FILE, T_CLASS, T_STATIC, T_DOUBLE_COLON, T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR, T_LNUMBER, T_DNUMBER, ')', ']'];

    $found = [];
    $text = null;
    $start = $line = $firstLine;
    // What opened the string with parts being read: " or a heredoc's line.
    $opening = null;

    $close = function () use (&$found, &$text, &$start): void {
        if ($text !== null && trim($text, "\0") !== '') {
            array_push($found, ...site_template_strings($text, $start));
        }
        $text = null;
    };

    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            [$id, $piece, $line] = $token;
            $line += $firstLine - 1;
        } else {
            $id = $piece = $token;
        }

        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        if ($id === '"') {
            $opening = $opening === null ? '"' : null;
            $piece = '';
        } elseif ($id === T_START_HEREDOC || $id === T_END_HEREDOC) {
            $opening = $id === T_START_HEREDOC ? $piece : null;
            $piece = '';
        } elseif ($opening !== null) {
            $piece = $id === T_ENCAPSED_AND_WHITESPACE ? site_unescaped($piece, $opening) : "\0";
        } elseif ($id === T_CONSTANT_ENCAPSED_STRING) {
            $literal = ltrim($piece, 'bB');
            $piece = site_unescaped(substr($literal, 1, -1), $literal[0]);
        } elseif ($id === '.' || $id === T_CONCAT_EQUAL) {
            $piece = '';
        } elseif (in_array($id, $operand, true)) {
            $piece = "\0";
        } elseif ($id === T_INLINE_HTML) {
            $close();
            array_push($found, ...site_template_strings($piece, $line));
            continue;
        } else {
            $close();
            continue;
        }

        if ($text === null) {
            [$text, $start] = ['', $line];
        }
        if ($piece !== "\0" || !str_ends_with($text, "\0")) {
            $text .= $piece;
        }
    }
    $close();

    return $found;
}

/**
 * A template string, split as the engine runs it: {{-- --}} comments gone, and
 * each {{ }} or {% %} block - PHP, in a view or a component's template, inside
 * an HTML comment too - read as PHP in its own right and as text, with \0 left
 * in its place. The engine drops only {{-- --}}; <!-- --> is removed from the
 * markup between the blocks, where it is text no browser requests.
 *
 * @return array<int, array{int, string}> [line, string]
 */
function site_template_strings(string $text, int $line): array
{
    // A comment keeps its line breaks, so every later line number holds.
    $lines = fn(array $comment): string => str_repeat("\n", substr_count($comment[0], "\n"));
    $text = preg_replace_callback('/\{\{--.*?--\}\}/s', $lines, $text);

    $found = [];
    $text = preg_replace_callback('/\{\{(.*?)\}\}|\{%(.*?)%\}/s', function (array $block) use (&$found, $line, $text): string {
        $code = html_entity_decode(($block[2][0] ?? '') !== '' ? $block[2][0] : $block[1][0]);
        $at = $line + substr_count($text, "\n", 0, $block[0][1]);
        array_push($found, ...site_spelled_strings("<?php $code;", $at));
        // Read as the markup around it is read, too: a block the tokenizer
        // cannot follow still has the path in it found.
        $found[] = [$at, site_code_text($code)];
        return "\0" . str_repeat("\n", substr_count($block[0][0], "\n"));
    }, $text, -1, $count, PREG_OFFSET_CAPTURE);
    array_unshift($found, [$line, preg_replace_callback('/<!--.*?-->/s', $lines, $text)]);

    return $found;
}

/** PHP source as plain text, each comment reduced to its line breaks. */
function site_code_text(string $code): string
{
    $text = '';
    foreach (array_slice(token_get_all("<?php $code"), 1) as $token) {
        if (!is_array($token)) {
            $text .= $token;
        } elseif (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $text .= str_repeat("\n", substr_count($token[1], "\n"));
        } else {
            $text .= $token[1];
        }
    }

    return $text;
}

/**
 * [line, excerpt] for every string in a PHP source that reaches into what a
 * deployment leaves out. A URL is somebody else's path, so URLs are left out.
 */
function site_never_deployed_paths(string $source): array
{
    $pattern = site_never_deployed_pattern();
    $paths = [];

    foreach (site_spelled_strings($source) as [$line, $text]) {
        $path = preg_replace('#\b[a-z][a-z0-9+.-]*://[^\s"\'<>\x00]*#i', '', str_replace('\\', '/', $text));
        if (preg_match($pattern, $path, $match, PREG_OFFSET_CAPTURE)) {
            $at = $line + substr_count($path, "\n", 0, $match[0][1]);
            $excerpt = substr($path, max(0, $match[0][1] - 40), 100);
            // A block is read twice, as PHP and as text: one entry a line.
            $paths[$at] ??= [$at, str_replace("\0", '…', preg_replace('/\s+/', ' ', $excerpt))];
        }
    }

    return array_values($paths);
}

/**
 * The PHP files the application owns: src/app/, and the three at the root
 * that are not framework - the page entry point, the page allowlist and the
 * configuration.
 */
function site_app_php_files(): array
{
    $files = [ROOT . '/index.php', ROOT . '/public/index.php', ROOT . '/runtime.php'];
    $tree = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ROOT . '/src/app', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($tree as $file) {
        if (strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** A path under the project root, as the repository writes it. */
function site_relative(string $path): string
{
    return str_replace('\\', '/', substr($path, strlen(ROOT) + 1));
}

test('no application code reads copy from a path a deployment leaves out', function () {
    // THIS IS THE CASE THAT WOULD HAVE CAUGHT #11's DEFECT.
    $offenders = [];
    foreach (site_app_php_files() as $file) {
        foreach (site_never_deployed_paths((string)file_get_contents($file)) as [$line, $path]) {
            $offenders[] = site_relative($file) . ':' . $line . ' → ' . $path;
        }
    }

    same([], $offenders,
        'page copy must live in its component, not in a path a deployment leaves out'
        . " - and a string that is only such a name, like 'tests', counts as a path");
});

test('the guard reads every PHP file the application owns', function () {
    // A page-copy read placed outside src/app/ is still on the page.
    $read = array_map('site_relative', site_app_php_files());
    $expected = [
        'index.php', 'public/index.php', 'runtime.php', 'src/app/boot.inc.php',
        'src/app/Views/main.php', 'src/app/Components/RecognitionSection.php',
    ];

    same([], array_values(array_diff($expected, $read)), 'files the guard does not read');
});

test("the guard's list is what the deployments leave out", function () {
    // site_never_deployed() is a copy, and an atlas sync can change what it
    // copies. Both workflows are read here, so the two cannot drift apart.
    $listed = [];
    foreach (['php-deploy-dev.yml', 'php-deploy-prod.yml'] as $workflow) {
        $yaml = (string)file_get_contents(ROOT . '/.github/workflows/' . $workflow);
        ok(preg_match('/<<\'LIST\'\R(.*?)\R\h*LIST\R/s', $yaml, $block) === 1, "no never list in $workflow");
        foreach (preg_split('/\R/', $block[1]) as $entry) {
            $entry = trim($entry);
            if ($entry !== '' && !str_starts_with($entry, '#')) {
                $listed[$entry] = $entry;
            }
        }
    }

    // The two entries site_never_deployed() leaves out, as its docblock says.
    // The tooling folder's name is taken from the checks' stray-reference
    // step, which is where that folder is guarded.
    $checks = (string)file_get_contents(ROOT . '/.github/workflows/php-checks.yml');
    ok(preg_match('/git grep -nI "([^"]+)"/', $checks, $stray) === 1, 'no stray-reference step in php-checks.yml');
    $elsewhere = ['runtime.dev.php', $stray[1] . '/'];

    same([], array_values(array_diff($listed, site_never_deployed(), $elsewhere)),
        'left out by a deployment, missing from the guard');
    same([], array_values(array_diff(site_never_deployed(), $listed)),
        'in the guard, left out by neither deployment');
});

test('the guard finds a never-deployed path in each spelling it follows', function () {
    // The first four each passed the guard #11 first shipped with, which read
    // one literal at a time and only from its first character (review of
    // 2026-09-16 07:40).
    $spellings = [
        "ROOT . '/docs/copy/x.txt'",
        "'docs' . '/copy/x.txt'",
        '"docs/copy/{$n}.txt"',
        "'../docs/copy/x.txt'",
        "'docs/copy/recognition-situations.txt'",
        "dirname(__DIR__, 2) . '/docs/copy/x.txt'",
        "'docs' . DIRECTORY_SEPARATOR . \$name",
        "ROOT . '\\\\docs\\\\x.txt'",
        '"{$root}/tests/fixtures/x.txt"',
        "<<<PATH\n{\$root}/captures/x.png\nPATH",
        "ROOT . '/' . 'README' . '.md'",
        "ROOT . '/LLM.txt'",
        "ROOT . '/.agent/x.json'",
        'ROOT . "/\\x64ocs/x.txt"',
        // Uploaded to DEV, left out of production.
        "ROOT . '/fixtures/intake.json'",
        "__DIR__ . '/../../seeds/x.sql'",
        "ROOT . '/composer.lock'",
    ];

    $missed = [];
    foreach ($spellings as $spelling) {
        if (site_never_deployed_paths("<?php\nreturn file_get_contents($spelling);\n") === []) {
            $missed[] = $spelling;
        }
    }

    // Views and component templates run PHP inside {{ }} and {% %}.
    $sources = [
        "<main>{{ file_get_contents(ROOT . '/' . 'docs/x.txt') }}</main>",
        "<?php \$template = '<p>{% echo file_get_contents(\"docs/\" . \$f); %}</p>';",
        '<img src="docs/x.png" alt="">',
        // A component's template is a single-quoted string, so a literal in
        // one of its blocks is written \'. Each of these passed the guard until
        // repair attempt 3 (review of 2026-09-16 09:16).
        <<<'PHP'
        <?php
        class ProbeSection extends Component
        {
            protected string $template = '
                <section class="probe">
                    {{ raw(file_get_contents(ROOT . \'/docs/copy/probe.txt\')) }}
                </section>';
        }
        PHP,
        <<<'PHP'
        <?php $template = '<p>{{ file_get_contents(\'docs/x.txt\') }}</p>';
        PHP,
        <<<'PHP'
        <?php $template = '{% echo file_get_contents(ROOT . \'/docs/x.txt\'); %}';
        PHP,
        <<<'PHP'
        <?php $template = '{{ file_get_contents(ROOT . \'/\' . \'docs\' . \'/x.txt\') }}';
        PHP,
        <<<'PHP'
        <?php $template = '{{ raw(file_get_contents(ROOT . \'/LLM.txt\')) }}';
        PHP,
        // A name split across the pieces is only found once the escapes are
        // undone: as text, the block holds no never-deployed name.
        <<<'PHP'
        <?php $template = '{{ file_get_contents(ROOT . \'/do\' . \'cs/x.txt\') }}';
        PHP,
        // The same between double quotes, and in a heredoc.
        <<<'PHP'
        <?php $template = "<p>{{ file_get_contents(\"docs/x.txt\") }}</p>";
        PHP,
        <<<'PHP'
        <?php $template = "<p>{{ file_get_contents(ROOT . \"/do\" . \"cs/x.txt\") }}</p>";
        PHP,
        <<<'PHP'
        <?php $template = <<<HTML
            <p>{{ file_get_contents("docs/x.txt") }}</p>
            HTML;
        PHP,
        // A block the tokenizer cannot follow is still read as text: in a
        // view nothing undoes the \', so this one never lexes into a string.
        <<<'PHP'
        <main>{{ raw(file_get_contents(ROOT . \'/docs/x.txt\')) }}</main>
        PHP,
        // The engine drops only {{-- --}}, so a block inside an HTML comment
        // still runs. Each of these passed the guard until repair attempt 4
        // (review of 2026-09-16 11:23).
        <<<'PHP'
        <?php $template = '<section><!-- {{ raw(file_get_contents(ROOT . \'/docs/copy/probe.txt\')) }} --></section>';
        PHP,
        "<main><!-- {% echo file_get_contents(ROOT . '/docs/x.txt'); %} --></main>",
        "<p>{{ '<!--' }}{{ file_get_contents(ROOT . '/docs/x.txt') }}{{ '-->' }}</p>",
        // A string that is only a never-deployed name counts, used as a path
        // or not.
        "<?php return ['tests' => 3];",
        "<?php return str_ends_with(\$file, '.md');",
    ];
    foreach ($sources as $source) {
        if (site_never_deployed_paths($source) === []) {
            $missed[] = $source;
        }
    }

    same([], $missed, 'spellings the guard walked past');
});

test('the guard leaves ordinary page code alone', function () {
    $spellings = [
        "'#how-it-works'",
        "'?page=start'",
        "'Read the docs'",
        "ROOT . '/src/app/boot.inc.php'",
        "__DIR__ . '/' . \$file",
        "'runtime.local.php'",
        "'data'",
        "'my-docs/x.txt'",
        "'https://example.com/docs/payments'",
        "'.github-actions'",
        "'notes.mdx'",
        // runtime.php merges it where the DEV deployment wrote it.
        "__DIR__ . '/' . 'runtime.dev.php'",
    ];

    $flagged = [];
    foreach ($spellings as $spelling) {
        if (site_never_deployed_paths("<?php\nreturn f($spelling);\n") !== []) {
            $flagged[] = $spelling;
        }
    }

    // A comment's text is not code, in PHP, in a template, or in a template's
    // block.
    foreach ([
        "<?php\n// reads docs/x.txt\n/** see ARCHITECTURE.md */\n",
        "<p>{{-- see ARCHITECTURE.md --}}<!-- docs/x.txt --></p>",
        "<p>{% /* see docs/x.txt */ echo \$intro; %}</p>",
    ] as $source) {
        if (site_never_deployed_paths($source) !== []) {
            $flagged[] = $source;
        }
    }

    same([], $flagged, 'ordinary code the guard refused');
});
