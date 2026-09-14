<?php

/**
 * Where rows actually live.
 *
 * The Model builds a QUERY DESCRIPTION — a plain array saying which table,
 * which conditions, which order — and hands it to a storage engine. It never
 * writes SQL, and it does not know which engine is running. Two exist:
 *
 *   SqlStorage    compiles the description into SQL and runs it through PDO
 *   FileStorage   keeps each table in a JSON file and answers in PHP
 *
 * Pick one with DB_ENGINE in runtime.php. Nothing above this line changes:
 * where(), orderBy(), get(), save(), relations and soft deletes behave
 * identically either way, which is the whole point of the seam.
 *
 * THE DESCRIPTION, in full:
 *
 *   [
 *     'table'      => 'items',
 *     'primaryKey' => 'id',
 *     'columns'    => ['id', 'title'] | null,        null means everything
 *     'conditions' => [ ['type' => 'basic'|'null'|'notnull'|'in'|'notin'|'raw',
 *                        'col' => …, 'op' => …, 'val' => …, 'bool' => 'AND'|'OR'], … ],
 *     'joins'      => [ ['type' => 'INNER'|'LEFT', 'table' => …,
 *                        'first' => …, 'op' => '=', 'second' => …], … ],
 *     'order'      => [ ['column' => 'title', 'direction' => 'ASC'], … ],
 *     'group'      => 'status' | null,
 *     'limit'      => int | null,
 *     'offset'     => int | null,
 *     'softDelete' => bool,                          add "deleted_at IS NULL"
 *   ]
 *
 * Adding a third engine means implementing the seven methods below. Nothing
 * else in the framework needs to know.
 */
abstract class Storage
{
    private static ?Storage $engine = null;

    /**
     * The configured engine, built once per request.
     *
     * DB_ENGINE is 'sql' or 'file'. Anything else falls back to 'file' with a
     * warning naming what it read — an unrecognised engine should not silently
     * become a connection attempt to a database nobody configured.
     */
    public static function engine(): Storage
    {
        if (self::$engine !== null) {
            return self::$engine;
        }

        $configured = defined('DB_ENGINE') ? strtolower(trim((string)DB_ENGINE)) : 'file';

        if ($configured === 'sql') {
            return self::$engine = new SqlStorage();
        }

        if ($configured !== 'file') {
            Log::warn('db', 'Unknown DB_ENGINE, falling back to file', [
                'configured' => $configured,
                'supported'  => ['sql', 'file'],
            ]);
        }

        return self::$engine = new FileStorage();
    }

    /** Replace the engine — for tests, or to point at a second data directory. */
    public static function use(?Storage $engine): void
    {
        self::$engine = $engine;
    }

    public static function name(): string
    {
        return self::engine() instanceof SqlStorage ? 'sql' : 'file';
    }

    // -------------------------------------------------------------------------
    // What an engine must do
    // -------------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> matching rows */
    abstract public function select(array $query): array;

    /** COUNT, without hydrating anything. '*' counts rows, a column counts non-nulls. */
    abstract public function count(array $query, string $column = '*'): int;

    /** Is there at least one match? Cheaper than count() when that is all you need. */
    abstract public function exists(array $query): bool;

    /** @return string|int the new row's primary key */
    abstract public function insert(string $table, array $data): string|int;

    /** @return int rows affected */
    abstract public function update(string $table, array $data, array $conditions): int;

    /** @return int rows affected */
    abstract public function delete(string $table, array $conditions): int;

    /** Apply $data to everything the query matches. @return int rows affected */
    abstract public function updateWhere(array $query, array $data): int;

    /** Remove everything the query matches. @return int rows affected */
    abstract public function deleteWhere(array $query): int;

    /** Run $work atomically, rolling back on any throw. @return mixed */
    abstract public function transaction(callable $work): mixed;

    /** A human-readable description of what the query would do. */
    abstract public function explain(array $query): string;

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * The bare column name, with any table qualifier removed.
     *
     * A condition may name "items.status" while the row it is tested against
     * has a plain "status" key — that is true for a join result too, since
     * joined columns are merged flat.
     */
    protected static function bare(string $column): string
    {
        $at = strrpos($column, '.');
        return $at === false ? $column : substr($column, $at + 1);
    }

    /** An alias if the expression has one ("x AS y" → "y"), else the column itself. */
    protected static function alias(string $expression): string
    {
        $at = stripos($expression, ' AS ');
        return $at === false ? $expression : trim(substr($expression, $at + 4));
    }
}
