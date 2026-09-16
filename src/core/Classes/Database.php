<?php

/**
 * The connection every SQL query goes through: MySQL/MariaDB when DB_NAME names
 * a database, SQLite otherwise (SqliteDatabase). One per request.
 */
class Database {
    private static $instance = null;
    private $pdo;

    protected function __construct() {
        $this->pdo = $this->connect();
    }

    /** Open the connection. SqliteDatabase opens its own. */
    protected function connect(): PDO {
        $host = DB_HOST ?? 'localhost';
        $db   = DB_NAME ?? 'db';
        $user = DB_USER ?? 'root';
        $pass = DB_PASSWORD ?? '';
        $charset = DB_CHARSET ?? 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            // Explicit MySQL credentials mean MySQL; without them, SQLite.
            self::$instance = defined('DB_NAME') && DB_NAME !== '' ? new self() : new SqliteDatabase();
        }
        return self::$instance;
    }

    /** Replace the connection - for tests, as Storage::use() replaces the engine. */
    public static function use(?Database $database): void {
        self::$instance = $database;
    }

    /** A schema statement as this database needs it. MySQL reads it as written. */
    public function schema(string $sql): string {
        return $sql;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // --- Core Execution ---

    public function query($sql, $params = []) {
        // Timing is only taken when logging is on, so the instrumentation costs
        // one static boolean check per query when it is off.
        $logging = Log::enabled();
        $start = $logging ? microtime(true) : 0.0;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($logging) {
                Log::query($sql, (microtime(true) - $start) * 1000, $stmt->rowCount());
            }
            return $stmt;
        } catch (PDOException $e) {
            if ($logging) {
                Log::error('db', 'query failed', [
                    'sql'   => $sql,
                    'error' => $e->getMessage(),
                    'ms'    => round((microtime(true) - $start) * 1000, 1),
                ]);
            }
            // Log error or rethrow
            throw new Exception("Query Failed: " . $e->getMessage() . " [SQL: $sql]");
        }
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn($sql, $params = [], $column = 0) {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    // --- CRUD Helpers ---

    public function find($table, $id, $pk = 'id') {
        return $this->fetch("SELECT * FROM `$table` WHERE `$pk` = ?", [$id]);
    }

    public function select($table, $conditions = [], $columns = '*', $orderBy = null, $limit = null, $dryRun = false) {
        $sql = "SELECT $columns FROM `$table`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $key => $value) {
                // Support simple 'key' => 'value' (equals)
                // Or 'key like' => 'value' logic if needed, but keeping it simple for now
                if (strpos($key, ' ') === false) {
                    if ($value === null) {
                        $clauses[] = "`$key` IS NULL";
                    } else {
                        $clauses[] = "`$key` = ?";
                        $params[] = $value;
                    }
                } else {
                    if (is_array($value) && stripos($key, 'IN') !== false) {
                        if (empty($value)) {
                            // If NOT IN and empty array, condition is always true (1=1)
                            // If IN and empty array, condition is always false (1=0)
                            if (stripos($key, 'NOT IN') !== false) {
                                $clauses[] = "1 = 1";
                            } else {
                                $clauses[] = "1 = 0";
                            }
                        } else {
                            $placeholders = implode(', ', array_fill(0, count($value), '?'));
                            $clauses[] = "$key ($placeholders)";
                            foreach ($value as $v) {
                                $params[] = $v;
                            }
                        }
                    } else {
                        $clauses[] = "$key ?";
                        $params[] = $value;
                    }
                }
            }
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        if ($dryRun) {
            return $sql . ' -- ' . implode(', ', $params);
        }


        return $this->fetchAll($sql, $params);
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = '`' . implode('`, `', $keys) . '`';
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $sql = "INSERT INTO `$table` ($fields) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $conditions) {
        if (empty($conditions)) {
            throw new Exception("Update requires conditions to be safe.");
        }

        $setPart = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setPart[] = "`$key` = ?";
            $params[] = $value;
        }

        $wherePart = [];
        foreach ($conditions as $key => $value) {
            $wherePart[] = "`$key` = ?";
            $params[] = $value;
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $setPart) . " WHERE " . implode(' AND ', $wherePart);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $conditions) {
        if (empty($conditions)) {
            throw new Exception("Delete requires conditions to be safe.");
        }

        $wherePart = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            $wherePart[] = "`$key` = ?";
            $params[] = $value;
        }

        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $wherePart);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // --- Advanced Features ---

    /**
     * Get a nested tree structure from a table with a parent_id column.
     * 
     * @param string $table The table name
     * @param string $parentIdCol The column name for the parent ID (default: parent_id)
     * @param int|null $rootId The root ID to start from (null for all top-level)
     * @param string $orderBy Column to order by
     * @return array Nested array with 'children' key
     */
    public function getTree($table, $parentIdCol = 'parent_id', $rootId = null, $orderBy = 'id ASC') {
        $all = $this->select($table, [], '*', $orderBy);
        return $this->buildTree($all, $rootId, $parentIdCol);
    }

    /**
     * Turn a flat list into a tree.
     *
     * Indexes the rows by parent in ONE pass and then links them. The previous
     * version rescanned the whole list for every branch it built — O(n²), which
     * on a few thousand rows is millions of comparisons for work that is
     * linear.
     */
    private function buildTree(array &$elements, $parentId = 0, $parentIdCol = 'parent_id') {
        // Null and 0 both mean "no parent"; normalise so they group together.
        $key = static function ($value) {
            return ($value === null || $value === '' || $value == 0) ? '' : (string)$value;
        };

        $byParent = [];
        foreach ($elements as $element) {
            $byParent[$key($element[$parentIdCol] ?? null)][] = $element;
        }

        $attach = static function ($parent) use (&$attach, &$byParent, $key) {
            $branch = [];
            foreach ($byParent[$parent] ?? [] as $element) {
                $children = $attach($key($element['id'] ?? null));
                if ($children) $element['children'] = $children;
                $branch[] = $element;
            }
            return $branch;
        };

        return $attach($key($parentId));
    }

    // --- Transactions --------------------------------------------------------

    /**
     * Run $work inside a transaction, committing on success and rolling back on
     * any throw.
     *
     *     Database::getInstance()->transaction(function () use ($id) {
     *         Item::query()->where('list_id', $id)->deleteAll();
     *         List::find($id)->delete();
     *     });
     *
     * Worth reaching for whenever a handler writes more than once: without it
     * each statement commits on its own, so a failure halfway leaves the data
     * in a state no code path expects — and every write pays its own fsync.
     *
     * Nested calls join the outer transaction rather than starting a second one,
     * which MySQL does not support anyway.
     *
     * @return mixed whatever $work returned
     */
    public function transaction(callable $work) {
        if ($this->pdo->inTransaction()) {
            return $work($this);
        }

        $this->pdo->beginTransaction();
        try {
            $result = $work($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            // rollBack() throws if the connection dropped, which would replace
            // the real error with a confusing one.
            try { $this->pdo->rollBack(); } catch (Throwable $ignored) {}
            Log::exception('db', $e, ['note' => 'transaction rolled back']);
            throw $e;
        }
    }

    public function inTransaction(): bool {
        return $this->pdo->inTransaction();
    }

    /**
     * Fetch records with related data from another table.
     * Generic implementation of "Relations based on PK/FK".
     * 
     * @param string $mainTable The main table to select from
     * @param array $relations Array of relations definition:
     *                         ['relation_name' => ['table' => 'other_table', 'fk' => 'fk_col', 'pk' => 'id', 'type' => 'one|many']]
     * @param array $conditions Conditions for the main table
     * @return array
     */
    public function selectWith($mainTable, $relations, $conditions = []) {
        // 1. Fetch main records
        $records = $this->select($mainTable, $conditions);
        
        if (empty($records)) return [];

        // 2. Fetch related data for each relation
        foreach ($relations as $key => $config) {
            $relatedTable = $config['table'];
            $fk = $config['fk']; // Foreign key on the *related* table (for hasMany) OR on *main* table (for belongsTo)
            $pk = $config['pk'] ?? 'id';
            $type = $config['type'] ?? 'many'; // 'one' or 'many'

            // Determine relation direction
            // Case A: HasMany/HasOne (Main.id <- Related.fk)
            // Case B: BelongsTo (Main.fk -> Related.id)
            
            // For simplicity, let's assume standard HasMany/HasOne where related table has the FK pointing to main table's ID
            // OR BelongsTo where main table has the FK.
            
            // We need to know which column in main table maps to which column in related table.
            // Let's refine the config:
            // 'local_col' => 'id', 'foreign_col' => 'user_id'
            
            $localCol = $config['local_col'] ?? 'id';
            $foreignCol = $config['foreign_col'] ?? ($mainTable . '_id');
            
            // Collect all IDs from the main records
            $ids = array_unique(array_column($records, $localCol));
            if (empty($ids)) continue;

            // Fetch related records
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM `$relatedTable` WHERE `$foreignCol` IN ($placeholders)";
            $relatedRecords = $this->fetchAll($sql, $ids);

            // Group related records by foreign key
            $grouped = [];
            foreach ($relatedRecords as $r) {
                $grouped[$r[$foreignCol]][] = $r;
            }

            // Attach to main records
            foreach ($records as &$record) {
                $recordId = $record[$localCol];
                if (isset($grouped[$recordId])) {
                    if ($type === 'one') {
                        $record[$key] = $grouped[$recordId][0];
                    } else {
                        $record[$key] = $grouped[$recordId];
                    }
                } else {
                    $record[$key] = ($type === 'one') ? null : [];
                }
            }
        }

        return $records;
    }
}
