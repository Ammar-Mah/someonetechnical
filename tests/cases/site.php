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

// -----------------------------------------------------------------------------
// The never-deployed guard
// -----------------------------------------------------------------------------
// The DEV and production packages leave out the repository's own material. A
// page that reads its copy from there renders perfectly locally and in CI,
// where the whole repository is on disk, and loses that copy on every server.
// No rendering test can see the difference, because the suite always runs
// with the repository whole - #11 shipped exactly that, and only DEV caught
// it. So the guard reads the application's PHP for a path into that material.
//
// It reads what the code spells, not what it computes: a path whose
// never-deployed part only exists at run time - transformed by a function,
// or taken from a request, the database or the environment - passes it.

/**
 * php-deploy-dev.yml's "never" list, entry for entry: what no deployment
 * uploads. A trailing / marks a folder; * matches within one name.
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
 * Every string a PHP source spells, as the program assembles it: pieces joined
 * across . and .=, the literal parts of interpolated strings and heredocs, and
 * \0 for each part the code computes - a variable, a constant, a call.
 * Comments are not code and are skipped.
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
    $quoted = false;

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

        if ($id === '"' || $id === T_START_HEREDOC || $id === T_END_HEREDOC) {
            $quoted = $id === '"' ? !$quoted : $id === T_START_HEREDOC;
            $piece = '';
        } elseif ($quoted) {
            $piece = $id === T_ENCAPSED_AND_WHITESPACE ? $piece : "\0";
        } elseif ($id === T_CONSTANT_ENCAPSED_STRING) {
            $piece = substr(ltrim($piece, 'bB'), 1, -1);
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
 * template - read as PHP in its own right, with \0 left in its place.
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
        return "\0" . str_repeat("\n", substr_count($block[0][0], "\n"));
    }, $text, -1, $count, PREG_OFFSET_CAPTURE);
    array_unshift($found, [$line, $text]);

    return $found;
}

/**
 * [line, excerpt] for every string in a PHP source that reaches into what no
 * deployment uploads. A URL is somebody else's path, so URLs are left out.
 */
function site_never_deployed_paths(string $source): array
{
    $pattern = site_never_deployed_pattern();
    $paths = [];

    foreach (site_spelled_strings($source) as [$line, $text]) {
        $path = preg_replace('#\b[a-z][a-z0-9+.-]*://[^\s"\'<>\x00]*#i', '', str_replace('\\', '/', $text));
        if (preg_match($pattern, $path, $match, PREG_OFFSET_CAPTURE)) {
            $excerpt = substr($path, max(0, $match[0][1] - 40), 100);
            $paths[] = [
                $line + substr_count($path, "\n", 0, $match[0][1]),
                str_replace("\0", '…', preg_replace('/\s+/', ' ', $excerpt)),
            ];
        }
    }

    return $paths;
}

/** The PHP files the application owns. */
function site_app_php_files(): array
{
    $files = [];
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

test('no application code reads copy from a path the deployment never uploads', function () {
    // THIS IS THE CASE THAT WOULD HAVE CAUGHT #11's DEFECT.
    $offenders = [];
    foreach (site_app_php_files() as $file) {
        foreach (site_never_deployed_paths((string)file_get_contents($file)) as [$line, $path]) {
            $offenders[] = site_relative($file) . ':' . $line . ' → ' . $path;
        }
    }

    same([], $offenders,
        'page copy must live in its component, not in a path the deployment excludes');
});

test('the guard finds a never-deployed path however the code spells it', function () {
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
    ];

    $missed = [];
    foreach ($spellings as $spelling) {
        if (site_never_deployed_paths("<?php\nreturn file_get_contents($spelling);\n") === []) {
            $missed[] = $spelling;
        }
    }

    // Views and component templates run PHP inside {{ }} and {% %}.
    $templates = [
        "<main>{{ file_get_contents(ROOT . '/' . 'docs/x.txt') }}</main>",
        "<?php \$template = '<p>{% echo file_get_contents(\"docs/\" . \$f); %}</p>';",
        '<img src="docs/x.png" alt="">',
    ];
    foreach ($templates as $template) {
        if (site_never_deployed_paths($template) === []) {
            $missed[] = $template;
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
    ];

    $flagged = [];
    foreach ($spellings as $spelling) {
        if (site_never_deployed_paths("<?php\nreturn f($spelling);\n") !== []) {
            $flagged[] = $spelling;
        }
    }

    // Comments are not code, in PHP or in a template.
    foreach ([
        "<?php\n// reads docs/x.txt\n/** see ARCHITECTURE.md */\n",
        "<p>{{-- see ARCHITECTURE.md --}}<!-- docs/x.txt --></p>",
    ] as $source) {
        if (site_never_deployed_paths($source) !== []) {
            $flagged[] = $source;
        }
    }

    same([], $flagged, 'ordinary code the guard refused');
});
