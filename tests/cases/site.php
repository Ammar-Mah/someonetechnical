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

// -----------------------------------------------------------------------------
// The never-deployed guard
// -----------------------------------------------------------------------------
// The DEV and production packages leave out the repository's own material. A
// page that reads its copy from there renders perfectly locally and in CI,
// where the whole repository is on disk, and loses that copy on the server.
// No rendering test can see the difference, because the suite always runs
// with the repository whole - #11 shipped exactly that, and only DEV caught
// it. So the guard reads the application's PHP for a path into that material.
//
// It is a text scan, and it follows the spellings the cases below name:
// pieces joined with . and .=, interpolated strings and heredocs, PHP's string
// escapes, and every {{ }} and {% %} block, read as PHP and as text. It reads
// what the code spells, not what it computes: a path whose never-deployed part
// only exists at run time - transformed by a function, or taken from a
// request, the database or the environment - passes it, and so does one that
// only a browser or a server decodes, such as a percent-escape or a character
// reference in markup.
//
// A string that is nothing but a never-deployed name - 'tests' as an array
// key, '.md' as a suffix - counts as a path, whether the code uses it as one
// or not.

/**
 * What a deployment leaves out, entry for entry: php-deploy-dev.yml's "never"
 * list, then what php-deploy-prod.yml leaves out besides. Copy read from one
 * of the latter is on DEV and missing only in production, where nothing is
 * validated. Two of production's entries are not here. runtime.php merges
 * runtime.dev.php on purpose where it exists. The DEV tooling folder belongs
 * to the checks: their stray-reference step refuses its name anywhere outside
 * it, this file included. A trailing / marks a folder; * matches within one
 * name.
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
 * Every string a PHP source spells, as the program assembles it: pieces joined
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
 * A string as the template engine reads it: {{-- --}} and <!-- --> comments
 * gone, and each {{ }} or {% %} block - PHP, in a view or a component's
 * template - read as PHP in its own right and as text, with \0 left in its
 * place.
 *
 * @return array<int, array{int, string}> [line, string]
 */
function site_template_strings(string $text, int $line): array
{
    // A comment keeps its line breaks, so every later line number holds.
    $text = preg_replace_callback('/\{\{--.*?--\}\}|<!--.*?-->/s',
        fn(array $comment): string => str_repeat("\n", substr_count($comment[0], "\n")), $text);

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
    array_unshift($found, [$line, $text]);

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

    // The two entries site_never_deployed() leaves out, and why. The tooling
    // folder is read from the checks' stray-reference step, since that step
    // refuses its name in this file too.
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

    // Comments are not code, in PHP, in a template, or in a template's block.
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
