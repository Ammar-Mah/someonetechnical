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

test('the hero states the headline, the offer, both actions and the availability note', function () {
    $html = (string)HeroSection::make('hero');

    // PRODUCT.md §1, typographic apostrophes included.
    contains('>Your AI built the app. Now you need someone technical.</h1>', $html);
    contains('>Get one-to-one help from an experienced engineer with deployment, security, databases, payments, integrations and all the important details your AI keeps talking around.</p>', $html);
    contains('>Bring the problem. You don’t need to know what it’s called.</p>', $html);

    same([
        ['?page=start', 'Get someone technical'],
        ['#how-it-works', 'See how it works'],
    ], site_links($html));
});

test('the hero card is hidden from assistive technology and is the settled card', function () {
    $html = (string)HeroSection::make('hero');

    // AC4: one hidden card and no live region, so no message is ever announced.
    same(1, substr_count($html, 'aria-hidden="true"'), 'the card, and only the card, is hidden');
    contains('<div class="hero-card" aria-hidden="true">', $html);
    lacks('aria-live', $html);

    // The markup is the finished card; the motion only leads up to it.
    contains('>Still asking AI…</span>', $html);
    contains('>Someone technical joined</span>', $html);
    same(3, substr_count($html, 'hero-message-ai"'), 'three suggestions');
    same(1, substr_count($html, 'hero-message-human"'), 'one reply');
});

test('the page opens with the hero, which holds its only first-level heading', function () {
    $page = Template::view('main');

    same(1, substr_count($page, '<h1'), 'one <h1> on the page');
    $hero = strpos($page, 'comp="HeroSection"');
    $recognition = strpos($page, 'comp="RecognitionSection"');
    ok($hero !== false && $recognition !== false && $hero < $recognition, 'the hero is not above the recognition section');
    ok(strpos($page, '<main') < $hero, 'the hero is not inside <main>');
});

test('the hero\'s words never move, and reduced motion stops its card', function () {
    $css = (string)file_get_contents(ROOT . '/public/css/app.css');

    // AC1: the words are the first paint, so none of them is animated.
    foreach (['.hero-copy', '.hero-title', '.hero-lede', '.hero-actions', '.hero-more', '.hero-note'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*animation/', $css), "$selector is animated");
    }

    // AC3: the styles are the settled card, so switching every animation in
    // the card off is the whole reduced-motion rule.
    ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.hero-card,\s*\.hero-card \*,\s*'
        . '\.hero-card \*::before\s*\{\s*animation: none;/', $css) === 1,
        'the reduced-motion rule no longer stops every animation in the card');
});

test('the types-of-help section lists the four formats of PRODUCT.md §6, Help Session first', function () {
    $html = (string)HelpTypesSection::make('help-types');

    contains('>Types of help</h2>', $html);

    // Word for word and in page order, as AC1 on #13 asks.
    $formats = [
        ['Help Session', 'Focused one-to-one assistance with one immediate technical problem.'],
        ['Launch Check', 'A structured human review before exposing the application to real customers.'],
        ['Technical Companion', 'Ongoing access to someone who becomes familiar with the project and its previous decisions.'],
        ['Rescue and Implementation', 'Hands-on technical work when the problem cannot reasonably be solved through guidance alone.'],
    ];

    $at = -1;
    foreach ($formats as [$name, $description]) {
        $nameAt = strpos($html, '>' . $name . '</h3>');
        ok($nameAt !== false, "format missing: $name");
        ok($nameAt > $at, "format out of order: $name");

        $textAt = strpos($html, '>' . $description . '</p>');
        ok($textAt !== false, "format description missing: $name");
        ok($textAt > $nameAt, "description precedes its name: $name");

        $at = $textAt;
    }

    same(4, preg_match_all('/<li class="help-type[ "]/', $html), 'one list item per format');
});

test('Help Session alone is set apart, and it holds the action to the intake', function () {
    $html = (string)HelpTypesSection::make('help-types');

    same(1, substr_count($html, 'help-type-first'), 'one format is set apart');
    same(1, substr_count($html, '>Start here</p>'), 'one format says where to start');

    // The label, the name and the section's only link all sit inside the
    // first list item.
    $first = strpos($html, '<li class="help-type help-type-first"');
    $start = strpos($html, '>Start here</p>');
    $name  = strpos($html, '>Help Session</h3>');
    $link  = strpos($html, 'href="?page=start"');
    $end   = strpos($html, '</li>');
    ok($first !== false && $first < $start && $start < $name && $name < $link && $link < $end,
        'the label or the action is not on Help Session');

    same([['?page=start', 'Get someone technical']], site_links($html));
});

test('the continuity section keeps its record with permission and names no credential', function () {
    $html = (string)ContinuitySection::make('continuity');

    contains('>Someone who remembers your project</h2>', $html);
    contains('>With your permission, Someone Technical keeps a concise record of your project', $html);

    // What PRODUCT.md §7 says the record holds, in its order.
    $at = -1;
    foreach (['Tools', 'Hosting', 'Integrations', 'Previous issues', 'Important decisions'] as $entry) {
        $entryAt = strpos($html, '>' . $entry . '</li>');
        ok($entryAt !== false && $entryAt > $at, "record entry missing or out of order: $entry");
        $at = $entryAt;
    }

    // AC3: nothing may suggest that access is kept, so no word for it appears.
    ok(!preg_match('/passw|credential|secret|token|\bkeys?\b|\b(log|sign) ?-?in\b/i', strip_tags($html)),
        'the continuity section names a credential');
});

test('the page names no price', function () {
    // AC2 on #13, applied to the whole page as a visitor reads it: the text
    // with scripts, styles and tags removed.
    $page = Template::view('main');
    $text = preg_replace('/<[^>]*>/', ' ', preg_replace('#<(script|style)\b.*?</\1>#is', '', $page));

    ok(!preg_match('/[$€£¥₹¢]/u', $text), 'the page shows a currency symbol');
    ok(!preg_match('/\b(usd|eur|gbp|dollars?|euros?|pounds?|cents?)\b/i', $text), 'the page names a currency');
    ok(!preg_match('/\bper\s+(hour|session|month|week|day)\b|\bhourly\b|\/\s*(h|hr|hour|mo|month)\b/i', $text),
        'the page states a rate');

    // The sections that describe the formats carry no figure at all, so no
    // amount can appear in them.
    foreach ([HelpTypesSection::make('help-types'), ContinuitySection::make('continuity')] as $section) {
        ok(!preg_match('/\d/', strip_tags((string)$section)), get_class($section) . ' carries a figure');
    }
});

test('the page keeps PRODUCT.md order, all nine sections inside <main>', function () {
    $page = Template::view('main');

    $at = strpos($page, '<main');
    foreach (['HeroSection', 'RecognitionSection', 'HowItWorksSection', 'SupportAreasSection', 'PositioningSection', 'HelpTypesSection', 'ContinuitySection', 'TrustSection', 'FinalCtaSection'] as $comp) {
        $compAt = strpos($page, 'comp="' . $comp . '"');
        ok($compAt !== false && $compAt > $at, "$comp is missing or out of order");
        $at = $compAt;
    }
    ok($at < strpos($page, '</main>'), 'the final call to action is not inside <main>');
});

test('the formats and the record never hide, and reduced motion stops their entrances', function () {
    // Comments removed, so a selector is only ever the text before its brace.
    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));

    // AC4 on #13: the text is the first paint. The only motion is an entrance
    // to that state, and the reduced-motion rules switch it off.
    foreach (['.help-type', '.help-types-lede', '.continuity-lede', '.continuity-text', '.continuity-note', '.continuity-entry'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*(display:\s*none|visibility:\s*hidden|opacity:\s*0)\b/', $css),
            "$selector is hidden by a rule");
    }

    foreach (['.help-type', '.continuity-entry'] as $selector) {
        ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*' . preg_quote($selector, '/') . '\s*\{\s*animation: none;/', $css) === 1,
            "reduced motion no longer stops $selector");
    }

    // No other rule in either section animates.
    preg_match_all('/([^{}]+)\{[^}]*\banimation:/', $css, $rules);
    foreach ($rules[1] as $selectors) {
        if (preg_match('/\.(help-type|continuity)/', $selectors)) {
            ok(in_array(trim($selectors), ['.help-type', '.continuity-entry'], true), 'unexpected animation on ' . trim($selectors));
        }
    }
});

test('the trust section states its heading and the eight principles of PRODUCT.md §8, in order', function () {
    $html = (string)TrustSection::make('trust');

    contains('>Real technical judgment. No technical theatre.</h2>', $html);

    // Word for word and in §8's order, as AC1 on #14 asks.
    $principles = [
        'Real experienced engineers',
        'Clear explanations in plain language',
        'No judgment about how the project was built',
        'No unnecessary rebuilding',
        'Transparent scope before work begins',
        'Careful treatment of project access and credentials',
        'Honest advice when something requires deeper work',
        'The customer retains ownership and control',
    ];

    $at = -1;
    foreach ($principles as $principle) {
        $principleAt = strpos($html, '>' . $principle . '</li>');
        ok($principleAt !== false && $principleAt > $at, "principle missing or out of order: $principle");
        $at = $principleAt;
    }

    same(8, substr_count($html, '<li class="trust-principle"'), 'one list item per principle');
});

test('the final call to action states PRODUCT.md §9 and leads to the intake', function () {
    $html = (string)FinalCtaSection::make('final-cta');

    // §9's own typography. The Issue quotes these two sentences with a straight
    // apostrophe; the page carries PRODUCT.md's, as the hero does.
    contains('>You’ve asked the AI enough.</h2>', $html);
    contains('>Show the problem to someone who can understand the project, explain what is happening and help you move forward.</p>', $html);
    contains('>You don’t need to diagnose the problem before contacting us.</p>', $html);

    same([['?page=start', 'Get someone technical']], site_links($html));
    ok(strpos($html, 'href="?page=start"') < strpos($html, '>You don’t need to diagnose'), 'the note comes before the action');
});

test('the page shows no testimonial, rating, star, customer count or partner logo', function () {
    // AC3 on #14, for the whole page: PRODUCT.md §8 builds trust from the
    // principles alone. Looked for in the markup, where a logo or a review
    // would be an element, and in the text a visitor reads, its entities
    // decoded so that &#9733; is the star it shows.
    $page = Template::view('main');
    $text = preg_replace('/<[^>]*>/', ' ', preg_replace('#<(script|style)\b.*?</\1>#is', '', $page));
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $css  = (string)file_get_contents(ROOT . '/public/css/app.css');

    foreach (['<img', '<svg', '<picture', '<blockquote', '<cite', '<q>', '<q ', 'itemprop', 'ld+json'] as $markup) {
        lacks($markup, $page, "the page carries $markup");
    }

    ok(!preg_match('/\b(testimonials?|ratings?|rated|stars?|logos?|trusted by|reviews? from|out of \d)\b/i', $text),
        'the page names a testimonial, a rating, a star or a logo');
    ok(!preg_match('/[★☆⭐✩✪✫✬✭✮✯✰]/u', $text . $page . $css) && !preg_match('/\\\\(2605|2606|2b50)\b/i', $css),
        'the page shows a star');
    ok(!preg_match('/\d[\d.,]*\s*[k%]?\+?\s*(happy\s+|satisfied\s+)?(customers|clients|users|founders|builders|teams|companies|projects|sessions)\b/i', $text),
        'the page counts its customers');

    // The two sections that close the page carry no figure at all.
    foreach ([TrustSection::make('trust'), FinalCtaSection::make('final-cta')] as $section) {
        $sectionText = html_entity_decode(strip_tags((string)$section), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        ok(!preg_match('/\d/', $sectionText), get_class($section) . ' carries a figure');
    }
});

test('the principles and the final call never hide, keep the focus outline, and reduced motion stops them', function () {
    // Comments removed, so a selector is only ever the text before its brace.
    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));

    // AC4 on #14: the text is the first paint. The only motion is the
    // principles' entrance and the light's pulse, and reduced motion stops both.
    foreach (['.trust-heading', '.trust-lede', '.trust-principle', '.final-cta-heading', '.final-cta-text', '.final-cta-note'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*(display:\s*none|visibility:\s*hidden|opacity:\s*0)\b/', $css),
            "$selector is hidden by a rule");
    }

    foreach (['.trust-principle', '.final-cta-note::before'] as $selector) {
        ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*' . preg_quote($selector, '/') . '\s*\{\s*animation: none;/', $css) === 1,
            "reduced motion no longer stops $selector");
    }

    // No other rule in either section moves.
    preg_match_all('/([^{}]+)\{[^}]*\b(animation|transition):/', $css, $rules);
    foreach ($rules[1] as $selectors) {
        if (preg_match('/\.(trust|final-cta)/', $selectors)) {
            ok(in_array(trim($selectors), ['.trust-principle', '.final-cta-note::before'], true), 'unexpected motion on ' . trim($selectors));
        }
    }

    // Focus stays visible: no rule here removes the outline, and on the ink
    // band the outline is the accent, as on the footer.
    preg_match_all('/([^{}]+)\{[^}]*\boutline(-style)?:\s*(none|0)\b/', $css, $cleared);
    foreach ($cleared[1] as $selectors) {
        ok(!preg_match('/\.(trust|final-cta)/', $selectors), 'the focus outline is removed on ' . trim($selectors));
    }
    ok(preg_match('/\.trust\s*\{[^}]*--focus:\s*var\(--signal\)/', $css) === 1, 'the ink band no longer sets the focus outline to the accent');
});

test('every "Get someone technical" action sits in a flex row, where it lifts and presses in', function () {
    // #51. The lift and press of .site-cta are transforms, and a transform does
    // not apply to an inline box: printed straight into a block paragraph, the
    // how-it-works and types-of-help actions stayed put. In a flex row an
    // action is laid out as a box.
    $css  = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));
    $page = Template::view('main');

    $rows = ['site-header-actions', 'hero-actions', 'how-it-works-action', 'support-areas-action', 'help-type-action', 'final-cta-action'];
    $rule = fn(string $row): string => preg_match('/(?:^|\})\s*\.' . preg_quote($row, '/') . '\s*\{([^}]*)\}/', $css, $m) ? $m[1] : '';

    foreach ($rows as $row) {
        ok(preg_match('/<(div|p) class="' . preg_quote($row, '/') . '"[^>]*>(.*?)<\/\1>/s', $page, $m) === 1, "the page has no .$row");
        same(1, substr_count($m[2] ?? '', 'class="site-cta"'), ".$row does not hold its action");
        ok(preg_match('/\bdisplay:\s*flex\b/', $rule($row)) === 1, ".$row is not a flex row");
    }
    same(count($rows), substr_count($page, 'class="site-cta"'), 'an action sits outside the rows');

    // The two rows that took over from an inline link keep the paragraph's own
    // line, and the action overflows it evenly, as the link did: nothing around
    // them moves (AC3 on #51).
    foreach (['how-it-works-action', 'help-type-action'] as $row) {
        ok(preg_match('/(?<![-\w])height:\s*1lh\b/', $rule($row)) === 1 && preg_match('/\balign-items:\s*center\b/', $rule($row)) === 1,
            ".$row no longer keeps its one-line height with the action centred");
    }
});

test('the support areas section carries the anchor every "What we help with" link points at', function () {
    $html = (string)SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH);

    // The other end of the header's and footer's './#' . SiteHeader::WHAT_WE_HELP_WITH,
    // written literally, as for how it works. Until #12 no element had it.
    contains('id="what-we-help-with"', $html);
    same('what-we-help-with', SiteHeader::WHAT_WE_HELP_WITH);
    same(1, substr_count(Template::view('main'), 'id="what-we-help-with"'), 'one target on the page');
});

test('the support areas section lists the twelve areas of PRODUCT.md §4, each with a one- or two-sentence explanation', function () {
    $html = (string)SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH);

    contains('>What we help with</h2>', $html);

    // AC1 on #12: §4's names word for word and in order.
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

    preg_match_all('/<li class="support-area"[^>]*><h3 class="support-area-name">([^<]*)<\/h3><p class="support-area-text">([^<]*)<\/p><\/li>/', $html, $entries, PREG_SET_ORDER);
    same($areas, array_map(fn(array $e): string => html_entity_decode($e[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $entries));

    foreach ($entries as [, $area, $explanation]) {
        $sentences = preg_match_all('/[.!?](?=\s|$)/', html_entity_decode($explanation, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        ok($sentences >= 1 && $sentences <= 2 && preg_match('/[.!?]$/', $explanation) === 1,
            "the explanation of $area is not one or two sentences");
    }
});

test('the support areas end with a note and the action to the intake', function () {
    $html = (string)SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH);

    contains('>Not on the list? Bring it anyway.</span>', $html);
    same([['?page=start', 'Get someone technical']], site_links($html));
});

test('the positioning section states PRODUCT.md §5: the statement, its explanation and the five differentiators', function () {
    $html = (string)PositioningSection::make('positioning');

    // AC2 on #12, in §5's typography: the Issue quotes the statement with a
    // straight apostrophe, and the page carries PRODUCT.md's.
    contains('>We don’t take your project away from you. We help you keep building it.</h2>', $html);
    ok(preg_match('/<p class="positioning-text">Someone Technical is for people who want to stay involved in their project[^<]*<\/p>/', $html) === 1,
        'the explanation is missing');

    // Each item's text, tags removed, is §5's line word for word, in order.
    preg_match_all('/<li class="positioning-point"[^>]*>(.*?)<\/li>/s', $html, $points);
    same([
        'More immediate than searching for a freelancer',
        'More personal than automated support',
        'More practical than watching another tutorial',
        'More accessible than hiring a fractional CTO',
        'More focused than handing the project to an agency',
    ], array_map(fn(string $p): string => html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $points[1]));

    same([], site_links($html), 'the positioning section holds no link');
});

test('neither the support areas nor the positioning section uses the words PRODUCT.md rules out', function () {
    // AC3 on #12, case-insensitive, on the text a visitor reads.
    foreach ([SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH), PositioningSection::make('positioning')] as $section) {
        $text = html_entity_decode(strip_tags((string)$section), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        ok(!preg_match('/revolutionary|cutting[- ]?edge|empower|unlock|seamless/i', $text), get_class($section) . ' uses a word PRODUCT.md rules out');
    }
});

test('the index and the band never hide their text, draw no boxes, and reduced motion stops their entrances', function () {
    // Comments removed, so a selector is only ever the text before its brace.
    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));
    $rule = fn(string $selector): string => preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m) ? $m[1] : '';

    // AC5 on #12: the text is the first paint.
    foreach (['.support-areas-lede', '.support-area', '.support-area-name', '.support-area-text', '.support-areas-note',
              '.positioning-heading', '.positioning-text', '.positioning-point'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*(display:\s*none|visibility:\s*hidden|opacity:\s*0)\b/', $css),
            "$selector is hidden by a rule");
    }

    // AC4: an index in columns, not a grid of cards. An entry has no box of
    // its own, and its hairline and tab stay inside it (placed above it, the
    // tab also showed at the foot of the previous column).
    ok(preg_match('/\bcolumns:/', $rule('.support-areas-list')) === 1 && !preg_match('/\bdisplay:/', $rule('.support-areas-list')),
        'the areas are no longer set in columns');
    ok(!preg_match('/\b(background|border|box-shadow|outline)(-[a-z]+)*:/', $rule('.support-area')), 'an area is drawn as a box');
    preg_match_all('/([^{}]*\.support-area::(?:before|after)[^{}]*)\{([^}]*)\}/', $css, $marks);
    ok(count($marks[0]) > 0 && !preg_match('/\binset[a-z-]*:\s*-|\b(top|bottom|left|right|margin[a-z-]*):\s*-/', implode("\n", $marks[2])),
        'the tab or the hairline sits outside its entry');

    // Reduced motion stops both entrances, and nothing else in either block moves.
    foreach (['.support-area', '.positioning-point'] as $selector) {
        ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*' . preg_quote($selector, '/') . '\s*\{\s*animation: none;/', $css) === 1,
            "reduced motion no longer stops $selector");
    }
    preg_match_all('/([^{}]+)\{[^}]*\b(animation|transition):/', $css, $rules);
    foreach ($rules[1] as $selectors) {
        if (preg_match('/\.(support-area|positioning)/', $selectors)) {
            ok(in_array(trim($selectors), ['.support-area', '.positioning-point'], true), 'unexpected motion on ' . trim($selectors));
        }
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
