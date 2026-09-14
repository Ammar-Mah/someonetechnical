<?php

/**
 * The SQL engine: compiles a query description into SQL and runs it via PDO.
 *
 * This is where every backtick and placeholder in the framework lives. It used
 * to be spread through Model, which meant the Model could only ever talk to a
 * database — moving it here is what let a second engine exist without changing
 * a single line of application code.
 *
 * Values are always bound, never interpolated. Identifiers are quoted, and
 * anything that is not a plain (optionally qualified) name is passed through so
 * that callers can still write COUNT(*) or "col AS alias".
 */
class SqlStorage extends Storage
{
    private function db(): Database
    {
        return Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------

    public function select(array $query): array
    {
        [$sql, $params] = $this->compileSelect($query);
        return $this->db()->fetchAll($sql, $params);
    }

    public function count(array $query, string $column = '*'): int
    {
        $expression = $column === '*' ? 'COUNT(*)' : 'COUNT(' . $this->quote($column) . ')';

        // Order, limit and offset cannot change how many rows match.
        $query['order'] = null;
        $query['limit'] = null;
        $query['offset'] = null;

        [$sql, $params] = $this->compileSelect($query, $expression . ' AS aggregate');
        return (int)$this->db()->fetchColumn($sql, $params);
    }

    public function exists(array $query): bool
    {
        $query['limit'] = 1;
        [$sql, $params] = $this->compileSelect($query, '1');
        return $this->db()->fetchColumn($sql, $params) !== false;
    }

    // -------------------------------------------------------------------------
    // Writing
    // -------------------------------------------------------------------------

    public function insert(string $table, array $data): string|int
    {
        return $this->db()->insert($table, $data);
    }

    public function update(string $table, array $data, array $conditions): int
    {
        return $this->db()->update($table, $data, $conditions);
    }

    public function delete(string $table, array $conditions): int
    {
        return $this->db()->delete($table, $conditions);
    }

    public function updateWhere(array $query, array $data): int
    {
        [$where, $params] = $this->compileWhere($query);

        $set = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $set[] = $this->quote($column) . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->quote($query['table']) . ' SET ' . implode(', ', $set)
             . ($where !== '' ? ' WHERE ' . $where : '');

        return $this->db()->query($sql, array_merge($bindings, $params))->rowCount();
    }

    public function deleteWhere(array $query): int
    {
        [$where, $params] = $this->compileWhere($query);
        $sql = 'DELETE FROM ' . $this->quote($query['table']) . ($where !== '' ? ' WHERE ' . $where : '');
        return $this->db()->query($sql, $params)->rowCount();
    }

    public function transaction(callable $work): mixed
    {
        return $this->db()->transaction($work);
    }

    public function explain(array $query): string
    {
        [$sql, $params] = $this->compileSelect($query);
        if ($params === []) return $sql;
        return $sql . ' -- ' . implode(', ', array_map(fn($p) => var_export($p, true), $params));
    }

    // -------------------------------------------------------------------------
    // Compilation
    // -------------------------------------------------------------------------

    /** Quote an identifier, honouring table.column and leaving expressions alone. */
    public function quote(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '*') return '*';

        // Anything that is not a plain (possibly qualified) name is passed
        // through: callers occasionally need COUNT(*) or "col AS alias".
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier)) {
            return $identifier;
        }
        return '`' . str_replace('.', '`.`', $identifier) . '`';
    }

    private function softDeleteScope(array $query): ?string
    {
        if (empty($query['softDelete'])) return null;
        return $this->quote($query['table'] . '.deleted_at') . ' IS NULL';
    }

    /** @return array{0:string,1:array} the WHERE body (without the keyword) and its bindings */
    public function compileWhere(array $query): array
    {
        $sql = '';
        $params = [];

        foreach (($query['conditions'] ?? []) as $i => $c) {
            $joiner = $i === 0 ? '' : ' ' . (($c['bool'] ?? 'AND') === 'OR' ? 'OR' : 'AND') . ' ';

            switch ($c['type']) {
                case 'null':
                    $sql .= $joiner . $this->quote($c['col']) . ' IS NULL';
                    break;

                case 'notnull':
                    $sql .= $joiner . $this->quote($c['col']) . ' IS NOT NULL';
                    break;

                case 'in':
                case 'notin':
                    $values = array_values($c['val']);
                    if (!$values) {
                        // IN () matches nothing; NOT IN () matches everything.
                        $sql .= $joiner . ($c['type'] === 'in' ? '1 = 0' : '1 = 1');
                        break;
                    }
                    $sql .= $joiner . $this->quote($c['col'])
                          . ($c['type'] === 'in' ? ' IN (' : ' NOT IN (')
                          . implode(', ', array_fill(0, count($values), '?')) . ')';
                    foreach ($values as $v) $params[] = $v;
                    break;

                case 'raw':
                    $sql .= $joiner . '(' . $c['sql'] . ')';
                    foreach ((array)($c['val'] ?? []) as $v) $params[] = $v;
                    break;

                default:
                    $sql .= $joiner . $this->quote($c['col']) . ' ' . $c['op'] . ' ?';
                    $params[] = $c['val'];
            }
        }

        $scope = $this->softDeleteScope($query);
        if ($scope !== null) {
            $sql = $sql === '' ? $scope : '(' . $sql . ') AND ' . $scope;
        }

        return [$sql, $params];
    }

    private function compileColumns(array $query): string
    {
        $columns = $query['columns'] ?? null;
        if ($columns === null) return '*';
        if (!is_array($columns)) return (string)$columns;

        return implode(', ', array_map(function ($col) {
            return (stripos($col, ' AS ') !== false) ? $col : $this->quote($col);
        }, $columns));
    }

    /** @return array{0:string,1:array} */
    public function compileSelect(array $query, ?string $columnsOverride = null): array
    {
        $sql = 'SELECT ' . ($columnsOverride ?? $this->compileColumns($query))
             . ' FROM ' . $this->quote($query['table']);

        foreach (($query['joins'] ?? []) as $j) {
            $sql .= ' ' . $j['type'] . ' JOIN ' . $this->quote($j['table'])
                  . ' ON ' . $this->quote($j['first']) . ' ' . $j['op'] . ' ' . $this->quote($j['second']);
        }

        [$where, $params] = $this->compileWhere($query);
        if ($where !== '') $sql .= ' WHERE ' . $where;

        if (!empty($query['group'])) {
            $sql .= ' GROUP BY ' . $this->quote($query['group']);
        }

        if (!empty($query['order'])) {
            $parts = [];
            foreach ($query['order'] as $order) {
                $parts[] = $this->quote($order['column']) . ' ' . $order['direction'];
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        $limit = $query['limit'] ?? null;
        $offset = $query['offset'] ?? null;

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
            // MySQL requires LIMIT alongside OFFSET.
            if ($offset !== null) $sql .= ' OFFSET ' . (int)$offset;
        } elseif ($offset !== null) {
            $sql .= ' LIMIT 18446744073709551615 OFFSET ' . (int)$offset;
        }

        return [$sql, $params];
    }
}
