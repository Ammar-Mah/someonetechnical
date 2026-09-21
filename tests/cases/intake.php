<?php

/**
 * The intake: the page a visitor answers, and what IntakeHandler does with it.
 *
 * The questions are a contract with PRODUCT.md's conversion flow and with
 * #41's columns — one question per column, and no eighth thing asked — so a
 * field that drifts has nothing else to notice it. The handler's cases run
 * against the runner's scratch engine, the same Model API the SQL engine
 * answers; the columns themselves are tests/cases/database.php's.
 *
 * What this file will not let slip: a log line carrying an answer, a name or
 * an email address. That is the one thing the audit hook and the handler both
 * had to be taught, and it is why the intake may store personal data at all.
 */

/** The entries Log buffered, newest last. Nothing else can read them. */
function intake_log(): array
{
    $buffer = new ReflectionProperty('Log', 'buffer');
    $buffer->setAccessible(true);

    return (array)$buffer->getValue();
}

/** Drop what earlier cases logged, so a case reads only its own lines. */
function intake_log_reset(): void
{
    $buffer = new ReflectionProperty('Log', 'buffer');
    $buffer->setAccessible(true);
    $buffer->setValue(null, []);
}

/** The lines this call left, as [channel, message, context]. */
function intake_lines(callable $body): array
{
    intake_log_reset();
    $body();

    return array_map(
        fn(array $entry): array => [$entry['ch'], $entry['msg'], $entry['ctx'] ?? []],
        intake_log()
    );
}

/** Post $answers the way the form posts them: one JSON object in `value`. */
function intake_send(array $answers): array
{
    return (new IntakeHandler())->send(new Request(['value' => json_encode($answers)]));
}

/** A complete, valid set of answers. */
function intake_answers(array $override = []): array
{
    return $override + [
        'building'       => 'A booking site for a climbing gym.',
        'ai_tool'        => 'Lovable',
        'stuck_on'       => 'The live site cannot reach its database.',
        'is_live'        => IntakeScreen::IS_LIVE[0],
        'help_wanted'    => IntakeScreen::HELP_WANTED[1],
        'contact_name'   => 'Dana Okonkwo',
        'contact_email'  => 'dana@example.test',
        'preferred_time' => 'Weekday evenings',
    ];
}

group('intake');

test('the form posts, so a submit the framework does not catch cannot leak the answers', function () {
    $html = Template::view('start');

    // xon:submit compiles to an inline onSubmit, and only Baustein.js calls
    // preventDefault() - but every script is moved to just before </body>, so
    // the form is live for a moment before xhandle exists, and forever if the
    // script fails or is blocked. With no method a form submits by GET, and the
    // name and the email address would land in the address bar, the history and
    // the web server's access log. Found in the review of #42.
    preg_match_all('/<form[ >][^>]*>/', $html, $forms);
    same(1, count($forms[0]), 'one form on the page');
    contains('method="post"', $forms[0][0]);

    foreach ($forms[0] as $form) {
        ok(!preg_match('/method="get"/i', $form), 'no form on the intake submits by GET');
    }
});

test('each error slot describes its field and announces itself', function () {
    $html = Template::view('start');

    // refuse() moves focus to the input, so the message has to be attached to
    // it and live, or a screen reader lands on the field and never hears why.
    contains('aria-describedby="' . IntakeScreen::NAME_ERROR_ID . '"', $html);
    contains('aria-describedby="' . IntakeScreen::EMAIL_ERROR_ID . '"', $html);
    same(2, substr_count($html, 'class="intake-error"'));
    same(2, substr_count($html, 'role="alert"'));
});

test('the start page asks the seven questions of the conversion flow, and nothing else', function () {
    $html = Template::view('start');

    // PRODUCT.md's conversion flow, in its order. One per intake_requests
    // column: an eighth named field would be a column nothing stores. The
    // honeypot is the one other name, and nothing stores it either (#16).
    preg_match_all('/<(?:input|textarea|select)\b[^>]*\bname="([^"]+)"/', $html, $found);
    same(1, count(array_keys($found[1], IntakeScreen::TRAP, true)), 'one honeypot');
    $found[1] = array_values(array_diff($found[1], [IntakeScreen::TRAP]));
    same([
        'building',
        'ai_tool',
        'stuck_on',
        'is_live', 'is_live', 'is_live',
        'help_wanted', 'help_wanted', 'help_wanted',
        'contact_name',
        'contact_email',
        'preferred_time',
    ], $found[1]);

    contains('>What are you building?<', $html);
    contains('>Which AI building tool are you using?<', $html);
    contains('>What are you currently stuck on?<', $html);
    contains('>Is the project already live?<', $html);
    contains('>Would you prefer guidance, hands-on help, or are you unsure?<', $html);
    contains('>How do we reach you?<', $html);
    contains('>When would suit you for a session?<', $html);
});

test('"I don\'t know" is available on every question but the contact details', function () {
    $html = Template::view('start');

    // The two choice questions carry it as a real option...
    contains('value="I don’t know"', $html);
    contains('value="I’m not sure"', $html);

    // ...and each free-text question says so under the field. Four of them:
    // building, ai_tool, stuck_on, preferred_time. The contact details get no
    // such note — they are the one thing we cannot do without.
    same(4, substr_count($html, '“I don’t know” is a fine answer.'));

    // The contact pair is the only thing the page insists on.
    same(2, substr_count($html, ' required '), 'contact_name and contact_email, and nothing else');
});

test('the shell on the start page leads back to the home page\'s sections', function () {
    $html = Template::view('start');

    // A bare '#how-it-works' here would scroll the intake to nothing
    // (ARCHITECTURE.md → Hazards). Written literally: the point is the href a
    // browser resolves, not the constant it was built from.
    contains('href="./#how-it-works"', $html);
    contains('href="./#what-we-help-with"', $html);
    lacks('href="#how-it-works"', $html);
    lacks('href="#what-we-help-with"', $html);
});

test('a complete request is stored, stamped and confirmed', function () {
    $before = IntakeRequest::query()->count();
    $answers = intake_answers();

    $result = intake_send($answers);

    same('ok', $result['status']);
    same($before + 1, IntakeRequest::query()->count());

    $stored = IntakeRequest::query()->orderBy('id', 'DESC')->first();
    foreach ($answers as $column => $value) {
        same($value, $stored->$column, "$column is stored as it was answered");
    }
    ok($stored->created_at !== null && $stored->created_at === $stored->updated_at,
        'add() stamps both timestamps — the columns are NOT NULL');

    // The confirmation replaces the region's contents and marks the region.
    // It must not carry REGION_ID itself: inner() replaces children, so a
    // wrapper with that id would nest a second element with the same id.
    same(2, count($result['actions']));
    same('#' . IntakeScreen::REGION_ID, $result['actions'][0]['target']);
    contains('Thank you, Dana Okonkwo.', $result['actions'][0]['code']);
    lacks('id="' . IntakeScreen::REGION_ID . '"', $result['actions'][0]['code']);
    same('intake-confirmed', $result['actions'][1]['code']);
});

test('an unanswered question is stored as NULL rather than an empty string', function () {
    intake_send(intake_answers(['building' => '', 'ai_tool' => '', 'preferred_time' => '']));

    $stored = IntakeRequest::query()->orderBy('id', 'DESC')->first();
    same(null, $stored->building);
    same(null, $stored->ai_tool);
    same(null, $stored->preferred_time);
});

test('an address that is not an address is refused, and nothing is stored', function () {
    $before = IntakeRequest::query()->count();

    $lines = intake_lines(function () use (&$result) {
        $result = intake_send(intake_answers(['contact_email' => 'not-an-address']));
    });

    same($before, IntakeRequest::query()->count(), 'a refusal stores nothing');
    contains(IntakeScreen::EMAIL_ERROR_ID, json_encode($result['actions']));
    contains('is-invalid', json_encode($result['actions']));

    $refusals = array_values(array_filter($lines, fn(array $l): bool => $l[1] === 'intake request refused'));
    same(1, count($refusals));
    same('app', $refusals[0][0]);
    same(['field' => 'contact_email', 'reason' => 'invalid'], $refusals[0][2]);
    lacks('not-an-address', json_encode($lines), 'the line names the field, never the value');
});

test('a request with no name is refused the same way', function () {
    $before = IntakeRequest::query()->count();

    $lines = intake_lines(function () use (&$result) {
        $result = intake_send(intake_answers(['contact_name' => '   ']));
    });

    same($before, IntakeRequest::query()->count());
    contains(IntakeScreen::NAME_ERROR_ID, json_encode($result['actions']));

    $refusals = array_values(array_filter($lines, fn(array $l): bool => $l[1] === 'intake request refused'));
    same(['field' => 'contact_name', 'reason' => 'missing'], $refusals[0][2]);
});

test('a choice the page never offered is not stored, and is reported once', function () {
    // Any session holder can call the handler, so the two choice questions
    // accept only what IntakeScreen rendered (ARCHITECTURE.md → Hazards).
    $long = str_repeat('z', 60);

    $lines = intake_lines(function () use ($long) {
        intake_send(intake_answers(['is_live' => $long, 'help_wanted' => 'whatever']));
    });

    $stored = IntakeRequest::query()->orderBy('id', 'DESC')->first();
    same(null, $stored->is_live);
    same(null, $stored->help_wanted);

    // A choice is dropped whole, so an over-long one is not ALSO reported as
    // an answer cut to fit - one refusal, one line.
    same(2, count(array_filter($lines, fn(array $l): bool => $l[1] === 'intake choice not offered')));
    same(0, count(array_filter($lines, fn(array $l): bool => $l[1] === 'intake answer cut to fit')));
});

test('an answer longer than its column is cut to fit, and the cut is logged without it', function () {
    $long = str_repeat('x', 300);

    $lines = intake_lines(function () use ($long) {
        intake_send(intake_answers(['ai_tool' => $long, 'preferred_time' => $long]));
    });

    $stored = IntakeRequest::query()->orderBy('id', 'DESC')->first();
    same(100, mb_strlen($stored->ai_tool), 'ai_tool is VARCHAR(100)');
    same(200, mb_strlen($stored->preferred_time), 'preferred_time is VARCHAR(200)');

    $cuts = array_values(array_filter($lines, fn(array $l): bool => $l[1] === 'intake answer cut to fit'));
    same(2, count($cuts));
    same(['field' => 'ai_tool', 'kept' => 100], $cuts[0][2]);
    lacks($long, json_encode($lines));
});

test('markup given as an answer is stored as typed and never rendered as markup', function () {
    $script = '<script>alert(1)</script>';

    $result = intake_send(intake_answers(['building' => $script, 'contact_name' => $script]));

    $stored = IntakeRequest::query()->orderBy('id', 'DESC')->first();
    same($script, $stored->building, 'stored as the visitor typed it');
    same($script, $stored->contact_name);

    // The name is the one answer that comes back. It comes back escaped, and
    // no answer is rendered at all. Read from the action itself: json_encode
    // would escape the slash and hide what is actually being asserted.
    $confirmation = $result['actions'][0]['code'];
    contains('&lt;script&gt;alert(1)&lt;/script&gt;', $confirmation);
    lacks('<script>', $confirmation);
    lacks('A booking site', $confirmation, 'no answer is rendered at all');
});

test('the audit line for a request names the columns and never their values', function () {
    $lines = intake_lines(function () {
        intake_send(intake_answers(['contact_email' => 'private@example.test']));
    });

    $audit = array_values(array_filter($lines, fn(array $l): bool => $l[0] === 'audit'));
    same(1, count($audit), 'one write, one audit line');
    contains('IntakeRequest', $audit[0][1]);
    same('contact_email', $audit[0][2]['new'][array_search('contact_email', $audit[0][2]['new'], true)],
        'the columns that changed are named');
    lacks('private@example.test', json_encode($lines), 'no log line of this write carries a value');
    lacks('Dana Okonkwo', json_encode($lines));
});

test('every line the intake logs is free of answers and contact details', function () {
    $lines = intake_lines(function () {
        intake_send(intake_answers([
            'building'      => 'a secret project',
            'contact_name'  => 'Ada Lovelace',
            'contact_email' => 'ada@example.test',
        ]));
    });

    $all = json_encode($lines);
    foreach (['a secret project', 'Ada Lovelace', 'ada@example.test'] as $private) {
        lacks($private, $all, 'policies/logging.md: no personal data beyond an id');
    }
    contains('intake request stored', $all, 'the positive outcome is still recorded');
});

/* --- #43: the intake reads as a conversation ------------------------------ */

test('every question is a message from Someone Technical, and every answer a reply', function () {
    $html = Template::view('start');

    // AC1. Seven turns, each one a said-bubble and a reply, and both speakers
    // named in the markup - not drawn in CSS alone, where a screen reader, a
    // forced-colours mode and a stylesheet that never arrives all lose them.
    same(7, substr_count($html, 'class="intake-turn"'), 'one turn per question');
    same(7, substr_count($html, 'class="intake-said"'));
    same(7, substr_count($html, 'class="intake-reply"'));
    same(7, substr_count($html, '<span class="intake-from">' . IntakeScreen::THEM . '</span>'));
    same(7, substr_count($html, '<span class="intake-from intake-from-you">' . IntakeScreen::YOU . '</span>'));

    // The three grouped answers are fieldsets, and a <legend> has to be its
    // fieldset's first child - so there the bubble IS the legend.
    preg_match_all('/<fieldset\b[^>]*>\s*<(\w+)/', $html, $first);
    same(['legend', 'legend', 'legend'], $first[1], 'a fieldset that does not open with its legend');
    same(3, substr_count($html, '<legend class="intake-said">'));

    // One turn in full: the speaker, the question, the note, then the reply.
    contains('<div class="intake-said"><span class="intake-from">Someone technical</span>'
        . '<label class="intake-label" for="intake-building">What are you building?</label>'
        . '<span class="intake-note">“I don’t know” is a fine answer.</span></div>'
        . '<div class="intake-reply"><span class="intake-from intake-from-you">You</span>'
        . '<textarea class="intake-input" id="intake-building"', $html);
});

test('"I don\'t know" is said in the question, not filed under the field', function () {
    $html = Template::view('start');

    // AC1 again: it is one step, and it is part of what was said. All four
    // free-text notes sit inside a bubble; none is left loose in the reply.
    same(4, substr_count($html, '<span class="intake-note">“I don’t know” is a fine answer.</span>'));

    foreach (['intake-building', 'intake-ai-tool', 'intake-stuck-on', 'intake-preferred-time'] as $id) {
        $at = strpos($html, 'for="' . $id . '"');
        $note = strpos($html, '“I don’t know” is a fine answer.', $at);
        $reply = strpos($html, '<div class="intake-reply">', $at);
        ok($note !== false && $reply !== false && $note < $reply, "$id's note is not inside the question");
    }
});

test('the confirmation is Someone Technical answering, in the same thread', function () {
    $html = IntakeScreen::confirmation('Dana Okonkwo');

    // AC2. The reply is one more turn, so it reads as an answer rather than a
    // receipt. It still must not carry the region's id: inner() replaces
    // children, and a second element with that id would nest inside the first.
    contains('class="intake-turn"', $html);
    contains('<span class="intake-from">' . IntakeScreen::THEM . '</span>', $html);
    contains('Thank you, Dana Okonkwo.', $html);
    lacks('id="' . IntakeScreen::REGION_ID . '"', $html);
});

test('a submit the client never caught is answered, not silently emptied', function () {
    // #42's re-review, folded into #43: the POST is safe - nothing leaks and
    // nothing is stored - but the visitor used to be handed an empty form and
    // told nothing at all.
    $get = Template::view('start');
    lacks('That did not send', $get, 'the notice is for a POST, not for every visit');
    contains('<noscript><p class="intake-alert">Sending needs JavaScript.', $get);

    $was = $_SERVER['REQUEST_METHOD'] ?? null;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    try {
        $posted = Template::view('start');
    } finally {
        if ($was === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $was;
        }
    }

    contains('That did not send, and nothing you typed was kept.', $posted);
    contains('class="intake-alert"', $posted);

    // It sits inside the region, so the confirmation clears it with everything
    // else, and it is not a live region - the page is new, it is simply read.
    $region = strpos($posted, 'id="' . IntakeScreen::REGION_ID . '"');
    ok($region !== false && strpos($posted, 'That did not send') > $region, 'the notice is outside the region');
    same(2, substr_count($posted, 'role="alert"'), 'the two error slots are still the only live regions');
});

test('the thread arrives in order, hides nothing, and reduced motion stops it', function () {
    // Comments removed, so a selector is only ever the text before its brace.
    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));

    // AC4: the stylesheet IS the settled thread. The entrance only leads up to
    // it, so switching the animation off is the whole reduced-motion rule.
    foreach (['.intake-turn', '.intake-said', '.intake-reply', '.intake-label', '.intake-choices'] as $selector) {
        ok(!preg_match('/' . preg_quote($selector, '/') . '\b[^{]*\{[^}]*(display:\s*none|visibility:\s*hidden|opacity:\s*0)\b/', $css),
            "$selector is hidden by a rule");
    }

    ok(preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.intake-turn\s*\{\s*animation: none;/', $css) === 1,
        'reduced motion no longer stops .intake-turn');

    // Nothing else in the block moves, and the whole thread has settled in
    // well under a second - a question is never waiting on an entrance.
    preg_match_all('/([^{}]+)\{[^}]*\banimation:/', $css, $rules);
    foreach ($rules[1] as $selectors) {
        if (strpos($selectors, '.intake') !== false) {
            same('.intake-turn', trim($selectors), 'unexpected animation in the intake block');
        }
    }

    ok(preg_match('/animation: intake-arrive (\d+)ms/', $css, $duration) === 1
        && preg_match('/animation-delay: calc\(var\(--turn, 0\) \* (\d+)ms\)/', $css, $step) === 1
        && (int)$duration[1] + 6 * (int)$step[1] <= 1000,
        'the last question now waits more than a second to arrive');
});

/* --- #16: the owner is told, and a bot is not let in ---------------------- */

/** The lines on one channel, from intake_lines(). */
function intake_on(array $lines, string $channel): array
{
    return array_values(array_filter($lines, fn(array $l): bool => $l[0] === $channel));
}

/** Run $body as a request from $ip, then forget the address's count. */
function intake_from(string $ip, callable $body): void
{
    $_SERVER['REMOTE_ADDR'] = $ip;
    try {
        $body();
    } finally {
        unset($_SERVER['REMOTE_ADDR']);
        Cache::forget(IntakeHandler::limitKey($ip));
    }
}

test('a stored request is mailed to the owner once, under a subject that carries nothing typed', function () {
    IntakeHandler::setRecipient('owner@example.test');
    $sent = [];
    Mailer::setHandler(function (array $message) use (&$sent): bool {
        $sent[] = $message;
        return true;
    });

    try {
        $lines = intake_lines(function () {
            intake_send(intake_answers(['building' => 'a <b>secret</b> project', 'contact_email' => 'ada@example.test']));
        });
    } finally {
        Mailer::setHandler(null);
        IntakeHandler::setRecipient(null);
    }

    $id = IntakeRequest::query()->orderBy('id', 'DESC')->first()->getKey();

    // AC1: one message, one mail line, and the subject is only the id.
    same(1, count($sent));
    same(1, count(intake_on($lines, 'mail')));
    same('New intake request #' . $id, $sent[0]['subject']);
    same('New intake request #' . $id, intake_on($lines, 'mail')[0][2]['subject']);
    same('owner@example.test', $sent[0]['to'][0]['email']);

    // The owner gets the answers, escaped, and can reply straight to the visitor.
    same('ada@example.test', $sent[0]['reply_to']);
    contains('a &lt;b&gt;secret&lt;/b&gt; project', $sent[0]['html']);
    lacks('ada@example.test', json_encode($lines), 'no line carries the address - reply_to is not logged');
    lacks('secret', json_encode($lines));
});

test('with no recipient the request is still stored and confirmed, and the skip is a warning', function () {
    IntakeHandler::setRecipient('');
    $before = IntakeRequest::query()->count();

    try {
        $lines = intake_lines(function () use (&$result) {
            $result = intake_send(intake_answers());
        });
    } finally {
        IntakeHandler::setRecipient(null);
    }

    // AC2.
    same($before + 1, IntakeRequest::query()->count());
    contains('Thank you, Dana Okonkwo.', $result['actions'][0]['code']);
    same([], intake_on($lines, 'mail'), 'nothing was mailed');

    $skipped = array_values(array_filter($lines, fn(array $l): bool => $l[1] === 'intake notification skipped'));
    same(1, count($skipped));
    same('app', $skipped[0][0]);
    same(0, count(array_filter(intake_log(), fn(array $e): bool => $e['lvl'] === 'error')), 'no error is logged');
});

test('a fourth request from one address inside the hour is refused, and nothing is stored or mailed', function () {
    intake_from('203.0.113.' . random_int(1, 254), function () {
        for ($i = 0; $i < IntakeHandler::LIMIT; $i++) {
            same('ok', intake_send(intake_answers())['status']);
        }
        $before = IntakeRequest::query()->count();

        $lines = intake_lines(function () use (&$result) {
            $result = intake_send(intake_answers());
        });

        // AC3.
        same($before, IntakeRequest::query()->count(), 'the fourth is not stored');
        same([], intake_on($lines, 'mail'), 'the fourth is not mailed');
        same(1, count($result['actions']));
        contains('id="' . IntakeHandler::LIMIT_ID . '"', $result['actions'][0]['code']);
        contains('three requests from you this hour', $result['actions'][0]['code']);

        $security = intake_on($lines, 'security');
        same(1, count($security));
        same('intake request refused: rate limit', $security[0][1]);
        same(IntakeHandler::LIMIT, $security[0][2]['stored']);
    });

    // Another address is not affected.
    intake_from('198.51.100.' . random_int(1, 254), function () {
        contains('Thank you', intake_send(intake_answers())['actions'][0]['code']);
    });
});

test('a refused request does not count toward the limit', function () {
    intake_from('203.0.113.' . random_int(1, 254), function () {
        // A visitor fixing a typo is not locked out by the attempts that failed.
        for ($i = 0; $i < IntakeHandler::LIMIT + 2; $i++) {
            intake_send(intake_answers(['contact_email' => 'not-an-address']));
        }
        contains('Thank you', intake_send(intake_answers())['actions'][0]['code']);
    });
});

test('a filled honeypot is thanked like anyone else, and nothing is stored or mailed', function () {
    $before = IntakeRequest::query()->count();

    $lines = intake_lines(function () use (&$result) {
        $result = intake_send(intake_answers([IntakeScreen::TRAP => 'https://spam.example']));
    });

    // AC4.
    same($before, IntakeRequest::query()->count());
    same([], intake_on($lines, 'mail'));
    contains('Thank you, Dana Okonkwo.', $result['actions'][0]['code']);
    same('intake-confirmed', $result['actions'][1]['code']);

    $security = intake_on($lines, 'security');
    same(1, count($security));
    same('intake honeypot filled', $security[0][1]);
    lacks('spam.example', json_encode($lines));
});

test('the honeypot is off the screen, out of the Tab order and hidden from assistive technology', function () {
    $html = Template::view('start');

    contains('<div class="intake-trap" aria-hidden="true">', $html);
    ok(preg_match('/<input[^>]*name="' . IntakeScreen::TRAP . '"[^>]*tabindex="-1"/', $html) === 1, 'the trap is reachable by Tab');
    lacks('required', substr($html, strpos($html, 'intake-trap'), 400), 'the trap is never required');

    $css = preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(ROOT . '/public/css/app.css'));
    ok(preg_match('/\.intake-trap\s*\{[^}]*position:\s*absolute/', $css) === 1, 'the trap is not taken out of the flow');
});

test('a request waits for the count another request is holding, so a burst cannot slip past the limit', function () {
    // #16's review: the count is read, the request stored and the count
    // written back as one step. A second process holds the lock for a moment;
    // a request from an address must wait for it rather than read the count
    // underneath it.
    $child = sys_get_temp_dir() . '/intake-lock-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($child, '<?php $h = fopen($argv[1], "c"); flock($h, LOCK_EX); echo "locked\n"; usleep(1200000);');
    $process = proc_open([PHP_BINARY, $child, ROOT . '/cache/intake-limit.lock'], [1 => ['pipe', 'w']], $pipes);

    try {
        same("locked\n", fgets($pipes[1]), 'the child took the lock');
        intake_from('203.0.113.' . random_int(1, 254), function () use (&$waited) {
            $started = microtime(true);
            intake_send(intake_answers());
            $waited = microtime(true) - $started;
        });
    } finally {
        fclose($pipes[1]);
        proc_close($process);
        unlink($child);
    }

    ok($waited >= 0.8, sprintf('send() did not wait for the lock (%.2fs)', $waited));
});

test('an IPv6 host is counted by its /64, so rotating addresses inside it does not reset the count', function () {
    same(IntakeHandler::limitKey('2001:db8:0:1::a'), IntakeHandler::limitKey('2001:db8:0:1:ffff::b'));
    ok(IntakeHandler::limitKey('2001:db8:0:1::a') !== IntakeHandler::limitKey('2001:db8:0:2::a'), 'another /64 is another host');
    ok(IntakeHandler::limitKey('203.0.113.1') !== IntakeHandler::limitKey('203.0.113.2'), 'IPv4 is counted by the address');

    intake_from('2001:db8:0:1::a', function () {
        for ($i = 0; $i < IntakeHandler::LIMIT; $i++) {
            intake_send(intake_answers());
        }
        $_SERVER['REMOTE_ADDR'] = '2001:db8:0:1::b';
        contains('id="' . IntakeHandler::LIMIT_ID . '"', intake_send(intake_answers())['actions'][0]['code']);
    });
});
