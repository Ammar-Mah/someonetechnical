<?php

/**
 * Retention (#78): old intake requests and old log files are deleted, newer
 * ones stay, and each sweep leaves a line saying how many went.
 *
 * The request cases run against the runner's scratch engine. The log cases
 * sweep a folder of their own in the temp directory, never the project's
 * logs/. sweepIfDue() is only exercised on its skip path here: its full run
 * sweeps the real logs/ folder, which DEV proves.
 */

/** What this call logged, as [channel, message, context]. */
function retention_lines(callable $body): array
{
    $buffer = new ReflectionProperty('Log', 'buffer');
    $buffer->setAccessible(true);
    $buffer->setValue(null, []);
    $body();

    return array_map(
        fn(array $entry): array => [$entry['ch'], $entry['msg'], $entry['ctx'] ?? []],
        (array)$buffer->getValue()
    );
}

/** Store a request sent at $sentAt. */
function retention_request(string $sentAt): int
{
    $request = new IntakeRequest([
        'contact_name'  => 'Ada',
        'contact_email' => 'ada@example.com',
        'stuck_on'      => 'The live site cannot reach its database.',
        'created_at'    => $sentAt,
        'updated_at'    => $sentAt,
    ]);
    $request->save();

    return (int)$request->getKey();
}

group('retention');

test('a request older than the period is deleted for good, and a newer one stays', function () {
    $now = strtotime('2026-09-29 12:00:00');
    $days = (int)INTAKE_RETENTION_DAYS;
    $old = retention_request(date('Y-m-d H:i:s', $now - ($days + 1) * 86400));
    $new = retention_request(date('Y-m-d H:i:s', $now - ($days - 1) * 86400));

    $lines = retention_lines(function () use ($now, &$ids) { $ids = Retention::requests($now); });

    same([$old], $ids);
    same(null, IntakeRequest::find($old), 'the old request is still there');
    same(0, IntakeRequest::query()->withTrashed()->where('id', $old)->count(), 'the old row was only stamped');
    same(1, IntakeRequest::query()->where('id', $new)->count(), 'the newer request went too');

    $audit = array_values(array_filter($lines, fn(array $l): bool => $l[0] === 'audit'));
    same('Force-Delete IntakeRequest', $audit[0][1] ?? null, 'the delete was not a hard one');
    $done = array_values(array_filter($lines, fn(array $l): bool => $l[1] === 'retention: intake requests deleted'));
    same(1, count($done), 'the sweep logged no outcome');
    same(1, $done[0][2]['removed']);
    same([$old], $done[0][2]['ids']);
    same($days, $done[0][2]['days']);

    $logged = json_encode($lines);
    foreach (['Ada', 'ada@example.com', 'cannot reach its database'] as $personal) {
        lacks($personal, $logged, 'a deleted request\'s contents reached the log');
    }
});

test('a sweep with nothing old enough still logs its outcome', function () {
    $lines = retention_lines(function () { Retention::requests(strtotime('2000-01-01')); });
    $done = array_values(array_filter($lines, fn(array $l): bool => $l[1] === 'retention: intake requests deleted'));
    same(0, $done[0][2]['removed'] ?? null);
});

test('any delete of a request removes the row, as a visitor\'s request to delete it needs', function () {
    $id = retention_request(date('Y-m-d H:i:s'));
    IntakeRequest::find($id)->delete();
    same(0, IntakeRequest::query()->withTrashed()->where('id', $id)->count());
});

test('a log file dated before the period is deleted, and a newer one stays', function () {
    $dir = sys_get_temp_dir() . '/retention-' . bin2hex(random_bytes(6));
    mkdir($dir);
    $now = strtotime('2026-09-29 12:00:00');
    $days = (int)LOG_RETENTION_DAYS;
    $oldDay = date('Y-m-d', $now - ($days + 1) * 86400);
    $keepDay = date('Y-m-d', $now - $days * 86400);
    $files = [
        "app-$oldDay.log.php"   => false,
        "app-$oldDay.1.log.php" => false,
        "app-$keepDay.log.php"  => true,
        'app-2026-09-29.log.php' => true,
        'notes.txt'              => true,
        'app-2000-01-01.txt'     => true,
    ];
    foreach (array_keys($files) as $name) file_put_contents("$dir/$name", '');

    try {
        $lines = retention_lines(function () use ($dir, $now, &$removed) { $removed = Retention::logs($dir, $now); });
        foreach ($files as $name => $kept) {
            same($kept, is_file("$dir/$name"), $kept ? "$name was deleted" : "$name was kept");
        }
        $done = array_values(array_filter($lines, fn(array $l): bool => $l[1] === 'retention: log files deleted'));
        same(2, $done[0][2]['removed'] ?? null);
        same($removed, $done[0][2]['files']);
    } finally {
        array_map('unlink', glob("$dir/*") ?: []);
        rmdir($dir);
    }
});

test('a sweep inside the interval does nothing', function () {
    $had = Cache::get(Retention::DONE_KEY);
    Cache::set(Retention::DONE_KEY, date('Y-m-d H:i:s'), Retention::INTERVAL);
    try {
        same([], retention_lines(fn() => Retention::sweepIfDue()));
    } finally {
        $had === null ? Cache::forget(Retention::DONE_KEY) : Cache::set(Retention::DONE_KEY, $had, Retention::INTERVAL);
    }
});

test('a period reads as the privacy notice says it', function () {
    same('12 months', Retention::period(365));
    same('2 years', Retention::period(730));
    same('90 days', Retention::period(90));
    same('1 day', Retention::period(1));
});
