<?php

/**
 * The file engine: one JSON file per table, no server, no schema, no setup.
 *
 * Point DB_ENGINE at 'file' and the whole Model layer keeps working — where(),
 * orderBy(), relations, soft deletes, transactions. Nothing above this class
 * knows the difference. That is the point: prototype against files, move to
 * MySQL by changing one line, and the application code never learns about it.
 *
 * ON DISK, under DB_PATH (default: <project>/data):
 *
 *     data/items.json          the table
 *     data/items.json.lock     the write lock (never contains anything)
 *
 * A table file looks like:
 *
 *     { "auto": 4,
 *       "rows": { "1": {"id":1,"title":"…"}, "2": {…} } }
 *
 * Rows are keyed by primary key, so find() is a map lookup rather than a scan.
 *
 * WHAT "INDEXING" MEANS HERE, honestly. The table is read and decoded once per
 * request; an index removes the SCAN, not the read. So indexes are built in
 * memory on first use and reused for the rest of the request — which is what
 * makes eager-loading a relation across N parents one lookup instead of N
 * scans. Declare hot columns on the model to have them built up front:
 *
 *     protected $indexes = ['status', 'owner_id'];
 *
 * Columns ending in _id are treated as indexed automatically, because that is
 * what relations join on. Below a few dozen rows nothing is indexed at all —
 * building the map costs more than the scan it saves.
 *
 * WHERE THIS STOPS BEING THE RIGHT TOOL: the whole table is decoded on every
 * request that touches it. That is comfortable into the low tens of thousands
 * of rows and then it is not. There is no partial read, no query planner across
 * tables, and writes take an exclusive lock on the table. When any of those
 * start to hurt, switch DB_ENGINE to 'sql' — that is what the seam is for.
 */
class FileStorage extends Storage
{
    /** Decoded tables for this request: table => ['auto' => int, 'rows' => array]. */
    private array $tables = [];

    /** Built indexes for this request: table => column => value => [keys]. */
    private array $indexes = [];

    /** Below this many rows, scanning beats building a map. */
    private const INDEX_THRESHOLD = 64;

    /** Pre-images for rollback: table => file contents, or null when it did not exist. */
    private array $snapshot = [];
    private bool $inTransaction = false;

    private ?string $dir = null;

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    public function directory(): string
    {
        if ($this->dir === null) {
            $configured = defined('DB_PATH') ? (string)DB_PATH : 'data';
            $root = defined('ROOT') ? ROOT : dirname(__DIR__, 3);

            // An absolute path is taken as given; anything else hangs off the project.
            $this->dir = preg_match('/^([A-Za-z]:[\\\\\/]|\/)/', $configured)
                ? rtrim($configured, '/\\')
                : $root . DIRECTORY_SEPARATOR . trim($configured, '/\\');

            if (!is_dir($this->dir)) {
                @mkdir($this->dir, 0775, true);
                $this->guard();
            }
        }
        return $this->dir;
    }

    /** Data files sit inside the project, so deny them over HTTP as well. */
    private function guard(): void
    {
        $htaccess = $this->dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_file($htaccess)) return;

        @file_put_contents($htaccess,
            "# Database files. Never served.\n"
          . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
          . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }

    private function path(string $table): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $table);
        return $this->directory() . DIRECTORY_SEPARATOR . $safe . '.json';
    }

    /** The decoded table, read from disk once per request. */
    private function load(string $table): array
    {
        if (isset($this->tables[$table])) {
            return $this->tables[$table];
        }

        $raw = @file_get_contents($this->path($table));
        if ($raw === false || trim($raw) === '') {
            // A table nobody has written to yet is simply empty — no schema step.
            return $this->tables[$table] = ['auto' => 0, 'rows' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            Log::error('db', 'table file is unreadable, treating it as empty', ['table' => $table]);
            return $this->tables[$table] = ['auto' => 0, 'rows' => []];
        }

        return $this->tables[$table] = [
            'auto' => (int)($decoded['auto'] ?? 0),
            'rows' => $decoded['rows'],
        ];
    }

    /**
     * Read-modify-write a table under an exclusive lock.
     *
     * The lock is a separate file so the data file itself has no open handle
     * when it is replaced — renaming over an open file fails on Windows. The
     * table is re-read from disk INSIDE the lock, so a value another process
     * committed since this request started is not silently overwritten.
     *
     * @param callable $change fn(array &$table): int  — returns rows affected
     */
    private function mutate(string $table, callable $change): int
    {
        $path = $this->path($table);
        $lock = @fopen($path . '.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Cannot open the write lock for table "' . $table . '". Is ' . $this->directory() . ' writable?');
        }

        try {
            flock($lock, LOCK_EX);

            if ($this->inTransaction && !array_key_exists($table, $this->snapshot)) {
                $existing = @file_get_contents($path);
                $this->snapshot[$table] = $existing === false ? null : $existing;
            }

            unset($this->tables[$table]);          // force a fresh read under the lock
            $data = $this->load($table);

            $affected = $change($data);

            if ($affected > 0) {
                $this->write($path, $data);
                $this->tables[$table] = $data;
                unset($this->indexes[$table]);     // the rows moved; the maps are stale
            }

            return $affected;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Write to a temporary file and rename, so a reader never sees half a table. */
    private function write(string $path, array $data): void
    {
        $json = json_encode($data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            throw new RuntimeException('Could not encode the table: ' . json_last_error_msg());
        }

        $temp = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temp, $json) === false) {
            throw new RuntimeException('Could not write ' . $temp . '. Is the data directory writable?');
        }
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('Could not replace ' . $path . '.');
        }
    }

    // -------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------

    public function select(array $query): array
    {
        $started = microtime(true);

        $rows = $this->rowsFor($query);
        $rows = $this->applyJoins($rows, $query);
        $rows = $this->applyOrder($rows, $query);
        $rows = $this->applySlice($rows, $query);
        $rows = $this->applyColumns($rows, $query);

        if (Log::enabled()) {
            Log::query($this->explain($query), (microtime(true) - $started) * 1000, count($rows));
        }

        return $rows;
    }

    public function count(array $query, string $column = '*'): int
    {
        $query['order'] = null;
        $query['limit'] = null;
        $query['offset'] = null;

        $rows = $this->applyJoins($this->rowsFor($query), $query);

        if ($column === '*') return count($rows);

        // COUNT(column) counts rows where that column is not null.
        $bare = self::bare($column);
        return count(array_filter($rows, fn(array $row) => ($row[$bare] ?? null) !== null));
    }

    public function exists(array $query): bool
    {
        $query['limit'] = 1;
        return $this->applyJoins($this->rowsFor($query), $query) !== [];
    }

    /** The matching rows of the main table, before joins, ordering or slicing. */
    private function rowsFor(array $query): array
    {
        $this->refuseUnsupported($query);

        $table = $query['table'];
        $data = $this->load($table);
        $groups = $this->conditionGroups($query['conditions'] ?? []);

        $candidates = $this->candidates($query, $data, $groups);

        $matched = [];
        foreach ($candidates as $row) {
            if (!is_array($row)) continue;
            if ($this->matches($row, $groups, $query)) $matched[] = $row;
        }

        return $matched;
    }

    /**
     * Narrow the rows to look at, using the primary key or an index when the
     * query allows it. Falls back to every row, which is always correct.
     */
    private function candidates(array $query, array $data, array $groups): array
    {
        // An OR anywhere means a row can match without satisfying any single
        // indexed condition, so no index is usable.
        if (count($groups) !== 1) return $data['rows'];

        $table = $query['table'];
        $primaryKey = $query['primaryKey'] ?? 'id';
        $best = null;

        foreach ($groups[0] as $condition) {
            $column = self::bare($condition['col'] ?? '');
            if ($column === '') continue;

            $values = null;
            if ($condition['type'] === 'basic' && ($condition['op'] ?? '') === '=') {
                $values = [$condition['val']];
            } elseif ($condition['type'] === 'in') {
                $values = array_values($condition['val'] ?? []);
            }
            if ($values === null) continue;

            // The primary key needs no index: rows are keyed by it.
            if ($column === $primaryKey) {
                $picked = [];
                foreach ($values as $value) {
                    $key = (string)$value;
                    if (isset($data['rows'][$key])) $picked[] = $data['rows'][$key];
                }
                return $picked;
            }

            $index = $this->index($table, $column, $data, $query);
            if ($index === null) continue;

            $picked = [];
            foreach ($values as $value) {
                foreach ($index[$this->indexKey($value)] ?? [] as $key) {
                    if (isset($data['rows'][$key])) $picked[] = $data['rows'][$key];
                }
            }

            if ($best === null || count($picked) < count($best)) $best = $picked;
        }

        return $best ?? $data['rows'];
    }

    /**
     * A value => keys map for one column, built once per request.
     * Returns null when this column should not be indexed.
     */
    private function index(string $table, string $column, array $data, array $query): ?array
    {
        if (isset($this->indexes[$table][$column])) {
            return $this->indexes[$table][$column];
        }

        if (count($data['rows']) < self::INDEX_THRESHOLD) return null;

        $declared = $query['indexes'] ?? [];
        $wanted = in_array($column, $declared, true) || str_ends_with($column, '_id');
        if (!$wanted) return null;

        $map = [];
        foreach ($data['rows'] as $key => $row) {
            if (!is_array($row) || !array_key_exists($column, $row)) continue;
            $map[$this->indexKey($row[$column])][] = $key;
        }

        return $this->indexes[$table][$column] = $map;
    }

    /** Index keys are normalised the way equality compares, so lookups agree with filtering. */
    private function indexKey(mixed $value): string
    {
        if ($value === null) return "\0null";
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_numeric($value)) return 'n:' . (0 + $value);
        return 's:' . mb_strtolower((string)$value, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Conditions
    // -------------------------------------------------------------------------

    /**
     * Split a flat condition list into OR-separated groups of ANDs.
     *
     * SQL binds AND tighter than OR, so "a AND b OR c" means "(a AND b) OR c".
     * Evaluating the list left to right would get that wrong.
     */
    private function conditionGroups(array $conditions): array
    {
        if ($conditions === []) return [[]];

        $groups = [[]];
        foreach ($conditions as $i => $condition) {
            if ($i > 0 && ($condition['bool'] ?? 'AND') === 'OR') $groups[] = [];
            $groups[count($groups) - 1][] = $condition;
        }
        return $groups;
    }

    private function matches(array $row, array $groups, array $query): bool
    {
        // The soft-delete scope is ANDed to the whole condition, not to a group.
        if (!empty($query['softDelete']) && ($row['deleted_at'] ?? null) !== null) {
            return false;
        }

        foreach ($groups as $group) {
            $all = true;
            foreach ($group as $condition) {
                if (!$this->satisfies($row, $condition)) { $all = false; break; }
            }
            if ($all) return true;
        }
        return false;
    }

    private function satisfies(array $row, array $condition): bool
    {
        $value = $row[self::bare($condition['col'] ?? '')] ?? null;

        switch ($condition['type']) {
            case 'null':
                return $value === null;

            case 'notnull':
                return $value !== null;

            case 'in':
            case 'notin':
                $found = false;
                foreach (($condition['val'] ?? []) as $candidate) {
                    if ($this->equal($value, $candidate)) { $found = true; break; }
                }
                return $condition['type'] === 'in' ? $found : !$found;

            default:
                $operator = strtoupper((string)($condition['op'] ?? '='));
                $other = $condition['val'] ?? null;

                if ($operator === 'LIKE' || $operator === 'NOT LIKE') {
                    $like = $value !== null && $this->like((string)$value, (string)$other);
                    return $operator === 'LIKE' ? $like : !$like;
                }

                // Comparing with null never matches in SQL — that is what IS NULL is for.
                if ($value === null || $other === null) return false;

                return match ($operator) {
                    '='        => $this->equal($value, $other),
                    '!=', '<>' => !$this->equal($value, $other),
                    '>'        => $this->compare($value, $other) > 0,
                    '>='       => $this->compare($value, $other) >= 0,
                    '<'        => $this->compare($value, $other) < 0,
                    '<='       => $this->compare($value, $other) <= 0,
                    default    => false,
                };
        }
    }

    /**
     * Equality the way MySQL does it.
     *
     * Numbers compare numerically, so a request value of "7" finds the row whose
     * id is the integer 7 — without this, every id arriving from the browser as
     * a string would miss. Text compares case-insensitively, which is what the
     * default MySQL collation does; matching it here is what keeps the two
     * engines interchangeable.
     */
    private function equal(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) return $a === $b;
        if (is_bool($a) || is_bool($b)) return (bool)$a === (bool)$b;
        if (is_numeric($a) && is_numeric($b)) return (float)$a == (float)$b;
        return mb_strtolower((string)$a, 'UTF-8') === mb_strtolower((string)$b, 'UTF-8');
    }

    private function compare(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) return (float)$a <=> (float)$b;
        return strcasecmp((string)$a, (string)$b);
    }

    /** SQL LIKE: % is any run, _ is any single character. Case-insensitive. */
    private function like(string $value, string $pattern): bool
    {
        $regex = '';
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '%')      $regex .= '.*';
            elseif ($char === '_')  $regex .= '.';
            elseif ($char === '\\' && $i + 1 < $length) { $regex .= preg_quote($pattern[++$i], '~'); }
            else                    $regex .= preg_quote($char, '~');
        }

        return (bool)preg_match('~^' . $regex . '$~iu', $value);
    }

    private function refuseUnsupported(array $query): void
    {
        foreach (($query['conditions'] ?? []) as $condition) {
            if (($condition['type'] ?? '') === 'raw') {
                throw new RuntimeException(
                    'whereRaw() is SQL and the file engine cannot run it. Express the condition with '
                  . 'where()/whereIn()/whereNull(), or set DB_ENGINE to "sql".'
                );
            }
        }

        if (!empty($query['group'])) {
            throw new RuntimeException(
                'groupBy() is not supported by the file engine. Fetch the rows and group them in PHP, '
              . 'or set DB_ENGINE to "sql".'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Joins, ordering, slicing, projection
    // -------------------------------------------------------------------------

    /**
     * Hash join. The related table is indexed by its join column once, then each
     * main row is matched against it — so a join costs one pass over each table
     * rather than one pass per row.
     *
     * Columns are merged flat, with the joined table's values winning on a name
     * clash, which is what SELECT * through PDO produces.
     */
    private function applyJoins(array $rows, array $query): array
    {
        foreach (($query['joins'] ?? []) as $join) {
            if (($join['op'] ?? '=') !== '=') {
                throw new RuntimeException('The file engine can only join on equality, not "' . $join['op'] . '".');
            }

            $related = $this->load($join['table']);
            $rightColumn = self::bare($join['second']);
            $leftColumn  = self::bare($join['first']);

            // If the join is written the other way round, swap the sides.
            if (!$this->looksLikeColumnOf($rows, $leftColumn) && $this->looksLikeColumnOf($rows, $rightColumn)) {
                [$leftColumn, $rightColumn] = [$rightColumn, $leftColumn];
            }

            $bucket = [];
            foreach ($related['rows'] as $row) {
                if (is_array($row)) $bucket[$this->indexKey($row[$rightColumn] ?? null)][] = $row;
            }

            $joined = [];
            foreach ($rows as $row) {
                $matches = $bucket[$this->indexKey($row[$leftColumn] ?? null)] ?? [];

                if ($matches === []) {
                    // LEFT keeps the row with the other side's columns null.
                    if (strtoupper($join['type']) === 'LEFT') $joined[] = $row;
                    continue;
                }
                foreach ($matches as $match) $joined[] = array_merge($row, $match);
            }

            $rows = $joined;
        }

        return $rows;
    }

    private function looksLikeColumnOf(array $rows, string $column): bool
    {
        foreach ($rows as $row) return is_array($row) && array_key_exists($column, $row);
        return false;
    }

    private function applyOrder(array $rows, array $query): array
    {
        $order = $query['order'] ?? null;
        if (!$order) return $rows;

        usort($rows, function (array $a, array $b) use ($order) {
            foreach ($order as $by) {
                $column = self::bare($by['column']);
                $left = $a[$column] ?? null;
                $right = $b[$column] ?? null;
                $descending = strtoupper($by['direction'] ?? 'ASC') === 'DESC';

                if ($left === null && $right === null) continue;
                // MySQL sorts NULL first ascending, last descending.
                if ($left === null)  return $descending ? 1 : -1;
                if ($right === null) return $descending ? -1 : 1;

                $result = $this->compare($left, $right);
                if ($result !== 0) return $descending ? -$result : $result;
            }
            return 0;
        });

        return $rows;
    }

    private function applySlice(array $rows, array $query): array
    {
        $offset = $query['offset'] ?? null;
        $limit  = $query['limit'] ?? null;

        if ($offset === null && $limit === null) return array_values($rows);
        return array_values(array_slice($rows, (int)($offset ?? 0), $limit === null ? null : (int)$limit));
    }

    private function applyColumns(array $rows, array $query): array
    {
        $columns = $query['columns'] ?? null;
        if ($columns === null || !is_array($columns) || $columns === []) return $rows;

        $picked = [];
        foreach ($rows as $row) {
            $out = [];
            foreach ($columns as $column) {
                if ($column === '*') { $out = array_merge($out, $row); continue; }
                $source = self::bare(trim(stripos($column, ' AS ') !== false
                    ? substr($column, 0, stripos($column, ' AS '))
                    : $column));
                $out[self::alias($column)] = $row[$source] ?? null;
            }
            $picked[] = $out;
        }
        return $picked;
    }

    // -------------------------------------------------------------------------
    // Writing
    // -------------------------------------------------------------------------

    public function insert(string $table, array $data): string|int
    {
        $primaryKey = 'id';
        $assigned = null;

        $this->mutate($table, function (array &$file) use ($data, $primaryKey, &$assigned): int {
            $key = $data[$primaryKey] ?? null;

            if ($key === null || $key === '') {
                $key = ++$file['auto'];
                $data[$primaryKey] = $key;
            } elseif (is_numeric($key) && (int)$key > $file['auto']) {
                // Keep the counter ahead of explicitly-supplied ids.
                $file['auto'] = (int)$key;
            }

            $file['rows'][(string)$key] = $data;
            $assigned = $key;
            return 1;
        });

        return $assigned;
    }

    public function update(string $table, array $data, array $conditions): int
    {
        return $this->mutate($table, function (array &$file) use ($data, $conditions): int {
            $affected = 0;
            foreach ($file['rows'] as $key => $row) {
                if (!is_array($row) || !$this->matchesAll($row, $conditions)) continue;
                $file['rows'][$key] = array_merge($row, $data);
                $affected++;
            }
            return $affected;
        });
    }

    public function delete(string $table, array $conditions): int
    {
        return $this->mutate($table, function (array &$file) use ($conditions): int {
            $affected = 0;
            foreach ($file['rows'] as $key => $row) {
                if (!is_array($row) || !$this->matchesAll($row, $conditions)) continue;
                unset($file['rows'][$key]);
                $affected++;
            }
            return $affected;
        });
    }

    public function updateWhere(array $query, array $data): int
    {
        $this->refuseUnsupported($query);
        $groups = $this->conditionGroups($query['conditions'] ?? []);

        return $this->mutate($query['table'], function (array &$file) use ($groups, $query, $data): int {
            $affected = 0;
            foreach ($file['rows'] as $key => $row) {
                if (!is_array($row) || !$this->matches($row, $groups, $query)) continue;
                $file['rows'][$key] = array_merge($row, $data);
                $affected++;
            }
            return $affected;
        });
    }

    public function deleteWhere(array $query): int
    {
        $this->refuseUnsupported($query);
        $groups = $this->conditionGroups($query['conditions'] ?? []);

        return $this->mutate($query['table'], function (array &$file) use ($groups, $query): int {
            $affected = 0;
            foreach ($file['rows'] as $key => $row) {
                if (!is_array($row) || !$this->matches($row, $groups, $query)) continue;
                unset($file['rows'][$key]);
                $affected++;
            }
            return $affected;
        });
    }

    /** A simple [column => value] condition map, as the CRUD helpers take. */
    private function matchesAll(array $row, array $conditions): bool
    {
        foreach ($conditions as $column => $value) {
            if (!$this->equal($row[self::bare((string)$column)] ?? null, $value)) return false;
        }
        return true;
    }

    /**
     * Run $work, undoing every table it touched if it throws.
     *
     * Honest about what this is: the pre-image of each touched file is kept and
     * restored on failure, which gives you all-or-nothing for YOUR writes. It is
     * not isolation — another process reading mid-transaction sees the partial
     * state. If several processes write the same tables at once, use 'sql'.
     */
    public function transaction(callable $work): mixed
    {
        if ($this->inTransaction) {
            return $work($this);                 // join the outer one
        }

        $this->inTransaction = true;
        $this->snapshot = [];

        try {
            $result = $work($this);
            $this->inTransaction = false;
            $this->snapshot = [];
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            $this->inTransaction = false;
            Log::exception('db', $e, ['note' => 'file transaction rolled back']);
            throw $e;
        }
    }

    private function rollback(): void
    {
        foreach ($this->snapshot as $table => $contents) {
            $path = $this->path($table);
            if ($contents === null) @unlink($path);
            else @file_put_contents($path, $contents);

            unset($this->tables[$table], $this->indexes[$table]);
        }
        $this->snapshot = [];
    }

    // -------------------------------------------------------------------------
    // Explaining
    // -------------------------------------------------------------------------

    public function explain(array $query): string
    {
        $parts = ['FILE ' . $query['table']];

        foreach (($query['joins'] ?? []) as $join) {
            $parts[] = $join['type'] . ' JOIN ' . $join['table']
                     . ' ON ' . $join['first'] . ' ' . $join['op'] . ' ' . $join['second'];
        }

        $where = [];
        foreach (($query['conditions'] ?? []) as $i => $c) {
            $joiner = $i === 0 ? '' : ($c['bool'] ?? 'AND') . ' ';
            $where[] = $joiner . match ($c['type']) {
                'null'    => $c['col'] . ' IS NULL',
                'notnull' => $c['col'] . ' IS NOT NULL',
                'in'      => $c['col'] . ' IN (' . count($c['val'] ?? []) . ' values)',
                'notin'   => $c['col'] . ' NOT IN (' . count($c['val'] ?? []) . ' values)',
                'raw'     => 'RAW ' . ($c['sql'] ?? ''),
                default   => $c['col'] . ' ' . ($c['op'] ?? '=') . ' ' . var_export($c['val'] ?? null, true),
            };
        }
        if (!empty($query['softDelete'])) $where[] = ($where ? 'AND ' : '') . 'deleted_at IS NULL';
        if ($where) $parts[] = 'WHERE ' . implode(' ', $where);

        if (!empty($query['order'])) {
            $by = [];
            foreach ($query['order'] as $order) $by[] = $order['column'] . ' ' . $order['direction'];
            $parts[] = 'ORDER BY ' . implode(', ', $by);
        }
        if (($query['limit'] ?? null) !== null)  $parts[] = 'LIMIT ' . (int)$query['limit'];
        if (($query['offset'] ?? null) !== null) $parts[] = 'OFFSET ' . (int)$query['offset'];

        return implode(' ', $parts);
    }
}
