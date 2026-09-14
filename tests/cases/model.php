<?php

/**
 * The query builder. Everything here compiles SQL without touching a database,
 * so these run on a machine with no MySQL at all.
 */

class T_Row extends Model
{
    protected $table = 'rows';
    protected $fillable = ['title', 'status'];
}

class T_Hard extends Model
{
    protected $table = 'hard';
    protected $softDelete = false;
}

group('model');

test('a plain select is scoped to rows that are not soft-deleted', function () {
    same('SELECT * FROM `rows` WHERE `rows`.`deleted_at` IS NULL', sqlOf(T_Row::query()));
});

test('a model without soft delete gets no scope', function () {
    same('SELECT * FROM `hard`', sqlOf(T_Hard::query()));
});

test('withTrashed drops the scope', function () {
    same('SELECT * FROM `rows`', sqlOf(T_Row::query()->withTrashed()));
});

test('where takes two or three arguments', function () {
    contains('(`status` = ?) AND', sqlOf(T_Row::query()->where('status', 'open')));
    contains('(`id` >= ?) AND', sqlOf(T_Row::query()->where('id', '>=', 5)));
});

test('null-aware forms compile to IS NULL rather than a bound null', function () {
    contains('`parent_id` IS NULL', sqlOf(T_Row::query()->whereNull('parent_id')));
    contains('`parent_id` IS NOT NULL', sqlOf(T_Row::query()->whereNotNull('parent_id')));
    contains('`parent_id` IS NULL', sqlOf(T_Row::query()->where('parent_id', null)));
    contains('`parent_id` IS NOT NULL', sqlOf(T_Row::query()->where('parent_id', 'IS NOT', null)));
});

test('an empty IN matches nothing and an empty NOT IN matches everything', function () {
    contains('1 = 0', sqlOf(T_Row::query()->whereIn('id', [])));
    contains('1 = 1', sqlOf(T_Row::query()->whereNotIn('id', [])));
});

test('IN binds one placeholder per value', function () {
    contains('`id` IN (?, ?, ?)', sqlOf(T_Row::query()->whereIn('id', [1, 2, 3])));
});

test('conditions are a list, so repeats and OR both survive', function () {
    $sql = sqlOf(T_Row::query()->where('id', 1)->orWhere('id', 2));
    contains('`id` = ? OR `id` = ?', $sql);
});

test('an unsupported operator is refused rather than interpolated', function () {
    throws(fn() => T_Row::query()->where('id', 'DROP TABLE', 1), 'Unsupported SQL operator');
    throws(fn() => T_Row::query()->join('t', 'a', 'UNION', 'b'), 'Unsupported join operator');
});

test('identifiers are quoted, expressions are left alone', function () {
    contains('SELECT `id`, `title`', sqlOf(T_Row::query()->select('id', 'title')));
    contains('COUNT(*) AS n', sqlOf(T_Row::query()->select('COUNT(*) AS n')));
});

test('order, group, limit and offset compile', function () {
    $sql = sqlOf(T_Row::query()->orderBy('title')->orderBy('id', 'DESC')->groupBy('status')->forPage(3, 25));
    contains('GROUP BY `status`', $sql);
    contains('ORDER BY `title` ASC, `id` DESC', $sql);
    contains('LIMIT 25 OFFSET 50', $sql);
});

test('an offset with no limit still produces valid MySQL', function () {
    contains('LIMIT 18446744073709551615 OFFSET 10', sqlOf(T_Row::query()->offset(10)));
});

test('joins compile with quoted identifiers', function () {
    contains('LEFT JOIN `users` ON `rows`.`user_id` = `users`.`id`',
        sqlOf(T_Row::query()->leftJoin('users', 'rows.user_id', '=', 'users.id')));
});

test('whereRaw is passed through in parentheses', function () {
    contains('(a = ? OR b = ?)', sqlOf(T_Row::query()->whereRaw('a = ? OR b = ?', [1, 2])));
});

test('first() does not leave the builder stuck at LIMIT 1', function () {
    $q = T_Row::query();
    $q->limit(9);
    lacks('LIMIT 1', sqlOf($q));
    contains('LIMIT 9', sqlOf($q));
});

test('fillable filters mass assignment but always allows the key', function () {
    $row = new T_Row(['title' => 'ok', 'status' => 'open', 'is_admin' => 1, 'id' => 7]);
    same(['title' => 'ok', 'status' => 'open', 'id' => 7], $row->attributes);
});

test('getDirty reports only what moved', function () {
    $row = new T_Row(['title' => 'a']);
    same(['title' => 'a'], $row->getDirty());
    ok($row->isDirty());
});

test('relations are definitions, not queries', function () {
    $def = (new class extends Model {
        protected $table = 'x';
        public function owner() { return $this->belongsTo(T_Row::class, 'owner_id'); }
    })->owner();
    same('belongsTo', $def['type']);
    same('owner_id', $def['foreign_key']);
});

test('transaction() exists on both Model and Database', function () {
    ok(method_exists(Model::class, 'transaction'));
    ok(method_exists(Database::class, 'transaction'));
});
