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
    // column: an eighth named field would be a column nothing stores.
    preg_match_all('/<(?:input|textarea|select)\b[^>]*\bname="([^"]+)"/', $html, $found);
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
