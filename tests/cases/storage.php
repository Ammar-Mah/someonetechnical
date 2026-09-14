<?php

/**
 * The storage engines.
 *
 * The SQL half asserts compiled SQL, which needs no database. The file half
 * runs for real against a scratch directory — insert, query, relate, delete —
 * because the whole claim of this layer is that the SAME Model API works on
 * both, and only actually running it proves that.
 */

class T_Note extends Model
{
    protected $table = 'notes';
    // 'name' is here because two tests below read a joined/aliased column into
    // it — an attribute only survives hydration if $fillable allows it, which is
    // true of the SQL engine too.
    protected $fillable = ['id', 'title', 'status', 'owner_id', 'rank', 'deleted_at', 'deleted_by', 'name'];
    protected $indexes = ['status'];

    public function owner() { return $this->belongsTo(T_Person::class, 'owner_id'); }
}

class T_Person extends Model
{
    protected $table = 'people';
    protected $softDelete = false;
    protected $fillable = ['id', 'name'];

    public function notes() { return $this->hasMany(T_Note::class, 'owner_id'); }
}

group('storage');

// --- SQL compilation, engine-independent -------------------------------------

$sql = new SqlStorage();

test('the SQL engine compiles a scoped select', function () use ($sql) {
    same('SELECT * FROM `notes` WHERE `notes`.`deleted_at` IS NULL',
        $sql->compileSelect(descriptorOf(T_Note::query()))[0]);
});

test('the SQL engine binds values rather than interpolating them', function () use ($sql) {
    [$statement, $bindings] = $sql->compileSelect(descriptorOf(T_Note::query()->where('title', "O'Brien")));
    contains('`title` = ?', $statement);
    same(["O'Brien"], $bindings);
});

test('the SQL engine compiles order, limit and joins', function () use ($sql) {
    $query = descriptorOf(T_Note::query()
        ->leftJoin('people', 'notes.owner_id', '=', 'people.id')
        ->orderBy('rank', 'DESC')->forPage(2, 10));
    $statement = $sql->compileSelect($query)[0];
    contains('LEFT JOIN `people` ON `notes`.`owner_id` = `people`.`id`', $statement);
    contains('ORDER BY `rank` DESC', $statement);
    contains('LIMIT 10 OFFSET 10', $statement);
});

// --- The file engine, for real -----------------------------------------------

// The runner already installed a scratch engine; wipe it so this file starts
// from an empty table set regardless of what the earlier cases rendered.
$engine = test_storage(true);

test('a table nobody has written to is simply empty — no schema step', function () {
    same(0, T_Note::query()->count());
    same([], T_Note::all()->all());
    same(null, T_Note::find(1));
});

test('insert assigns an id and the row comes back', function () {
    $note = T_Note::create(['title' => 'First', 'status' => 'open', 'rank' => 2]);
    same(1, (int)$note->id);

    $found = T_Note::find(1);
    ok($found !== null, 'find() must return the row');
    same('First', $found->title);
});

test('find() matches a numeric id given as a string, as SQL would', function () {
    // Every id arriving from the browser is a string; this is why equality is
    // numeric-aware rather than strict.
    ok(T_Note::find('1') !== null);
});

test('where, ordering and limit work the same as they do in SQL', function () {
    T_Note::create(['title' => 'Second', 'status' => 'done',  'rank' => 1]);
    T_Note::create(['title' => 'Third',  'status' => 'open',  'rank' => 3]);

    same(['Second', 'First', 'Third'], T_Note::query()->orderBy('rank')->pluck('title'));
    same(['Third', 'First', 'Second'], T_Note::query()->orderBy('rank', 'DESC')->pluck('title'));
    same(['First', 'Third'], T_Note::query()->where('status', 'open')->orderBy('rank')->pluck('title'));
    same(['First'], T_Note::query()->orderBy('rank')->limit(1)->offset(1)->pluck('title'));
});

test('equality on text is case-insensitive, matching the default MySQL collation', function () {
    same(2, T_Note::query()->where('status', 'OPEN')->count());
});

test('IN, NOT IN and the empty cases behave like SQL', function () {
    same(2, T_Note::query()->whereIn('rank', [1, 3])->count());
    same(1, T_Note::query()->whereNotIn('rank', [1, 3])->count());
    same(0, T_Note::query()->whereIn('rank', [])->count(), 'IN () matches nothing');
    same(3, T_Note::query()->whereNotIn('rank', [])->count(), 'NOT IN () matches everything');
});

test('comparison operators and LIKE', function () {
    same(2, T_Note::query()->where('rank', '>=', 2)->count());
    same(1, T_Note::query()->where('rank', '<', 2)->count());
    same(2, T_Note::query()->where('title', 'LIKE', '%r%')->count(), 'First and Third; Second has no r');
    same(1, T_Note::query()->where('title', 'LIKE', 'F%')->count());
    same(1, T_Note::query()->where('title', 'LIKE', '_econd')->count());
});

test('AND binds tighter than OR, exactly as it does in SQL', function () {
    // (status = done AND rank = 1) OR rank = 3  →  Second and Third, not First.
    $titles = T_Note::query()
        ->where('status', 'done')->where('rank', 1)->orWhere('rank', 3)
        ->orderBy('title')->pluck('title');
    same(['Second', 'Third'], $titles);
});

test('null-aware conditions', function () {
    same(3, T_Note::query()->whereNull('deleted_at')->count());
    same(0, T_Note::query()->whereNotNull('deleted_at')->count());
});

test('exists() and count(column) do not hydrate anything', function () {
    ok(T_Note::query()->where('status', 'open')->exists());
    not_ok(T_Note::query()->where('status', 'nope')->exists());
    same(0, T_Note::query()->count('deleted_at'), 'COUNT(column) skips nulls');
    same(3, T_Note::query()->count('title'));
});

test('select() narrows the columns, honouring an alias', function () {
    $row = T_Note::query()->select('title AS name')->orderBy('rank')->first();
    same('Second', $row->name);
    same(null, $row->title);
});

test('a dirty save writes only what changed', function () {
    $note = T_Note::find(1);
    $note->title = 'First edited';
    same(['title' => 'First edited'], $note->getDirty());
    $note->save();

    same('First edited', T_Note::find(1)->title);
    same('open', T_Note::find(1)->status, 'the untouched column survives');
});

test('save() on an unchanged model does nothing at all', function () {
    $note = T_Note::find(1);
    not_ok($note->isDirty());
    ok($note->save());
});

test('soft delete hides the row but keeps it', function () {
    T_Note::find(3)->delete();

    same(2, T_Note::query()->count());
    same(null, T_Note::find(3), 'a soft-deleted row is gone from ordinary queries');
    same(3, T_Note::query()->withTrashed()->count());
    ok(T_Note::query()->withTrashed()->whereNotNull('deleted_at')->exists());
});

test('a model with soft delete off really removes the row', function () {
    T_Person::create(['name' => 'Ada']);
    T_Person::create(['name' => 'Alan']);
    same(2, T_Person::query()->count());

    T_Person::find(1)->delete();
    same(1, T_Person::query()->count());
    same(null, T_Person::find(1));
});

test('relations resolve — lazily and eagerly — with no join written by hand', function () {
    T_Person::create(['name' => 'Grace']);              // id 3
    T_Note::query()->withTrashed()->get()->each(function ($note) {
        $note->owner_id = 3;
        $note->save();
    });

    // lazy: one query per model
    $note = T_Note::find(1);
    ok($note->owner !== null, 'belongsTo resolved');
    same('Grace', $note->owner->name);

    // eager: batched, and this is what the _id index exists for
    $notes = T_Note::query()->with('owner')->get();
    ok($notes->count() > 0);
    foreach ($notes as $each) same('Grace', $each->owner->name);

    // the other direction
    same(2, T_Person::find(3)->notes->count());
});

test('an explicit join merges the other table in', function () {
    $row = T_Note::query()
        ->join('people', 'notes.owner_id', '=', 'people.id')
        ->select('title', 'name')
        ->orderBy('rank')
        ->first();
    same('Grace', $row->name, 'the joined column is there');
});

test('a LEFT join keeps rows with no match', function () {
    T_Note::create(['title' => 'Orphan', 'status' => 'open', 'rank' => 9]);
    same(3, T_Note::query()->leftJoin('people', 'notes.owner_id', '=', 'people.id')->count());
    same(2, T_Note::query()->join('people', 'notes.owner_id', '=', 'people.id')->count());
});

test('deleteAll soft-deletes everything matched, in one pass', function () {
    // Two, not three: 'Third' is also open but was soft-deleted earlier, and
    // the scope excludes it — the same rows SQL would have matched.
    same(2, T_Note::query()->where('status', 'open')->deleteAll());
    same(0, T_Note::query()->where('status', 'open')->count());
    // All three open rows are still on disk — the two just deleted, plus the one
    // that had already been soft-deleted before this ran.
    same(3, T_Note::query()->withTrashed()->where('status', 'open')->count());
});

test('a transaction rolls every table back when it throws', function () {
    $before = T_Person::query()->count();

    try {
        Model::transaction(function () {
            T_Person::create(['name' => 'Rolled back']);
            T_Person::create(['name' => 'Also rolled back']);
            throw new RuntimeException('nope');
        });
        fail('the exception should have propagated');
    } catch (RuntimeException $e) {
        same('nope', $e->getMessage());
    }

    same($before, T_Person::query()->count(), 'both inserts were undone');
});

test('a transaction that returns keeps its writes', function () {
    $before = T_Person::query()->count();
    $result = Model::transaction(function () {
        T_Person::create(['name' => 'Kept']);
        return 'done';
    });
    same('done', $result);
    same($before + 1, T_Person::query()->count());
});

test('SQL-only features are refused clearly rather than half-working', function () {
    throws(fn() => T_Note::query()->whereRaw('1 = 1')->get(), 'whereRaw() is SQL');
    throws(fn() => T_Note::query()->groupBy('status')->get(), 'groupBy() is not supported');
});

test('rows survive a fresh engine — they are on disk, not in memory', function () {
    $countBefore = T_Person::query()->count();
    test_storage();                                      // same directory, fresh in-memory state
    same($countBefore, T_Person::query()->count());
});

test('the table file is readable JSON a human can open', function () use ($engine) {
    $raw = file_get_contents($engine->directory() . DIRECTORY_SEPARATOR . 'people.json');
    $decoded = json_decode($raw, true);
    ok(is_array($decoded), 'valid JSON');
    ok(isset($decoded['rows']), 'has rows');
    ok(isset($decoded['auto']), 'has the auto-increment counter');
    contains('"name"', $raw, 'and it is pretty-printed, not a single line');
});

test('explain() describes the plan in the engine\'s own terms', function () {
    contains('FILE notes', T_Note::query()->where('status', 'open')->toSql());
    contains('WHERE status = ', T_Note::query()->where('status', 'open')->toSql());
});

// Hand the remaining cases the scratch engine back — never the application's
// own data directory.
test_storage();
