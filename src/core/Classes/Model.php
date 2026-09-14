<?php



abstract class Model {
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $withDeleted = false;

    protected $softDelete = true;

    /**
     * Columns worth indexing, for engines that can use the hint.
     *
     * Ignored by the SQL engine — indexes there belong in the schema, not in
     * PHP. The file engine builds a lookup map for these columns so that a
     * repeated filter is not a repeated scan. Columns ending in _id are treated
     * as indexed anyway, since that is what relations join on.
     */
    protected $indexes = [];

    // Holds the attributes of the model
    public $attributes = [];

    // Snapshot taken at hydration, used to work out what actually changed
    protected $original = [];

    // Holds loaded relationships
    public $relations = [];

    /**
     * Per-request cache for find(). Keyed by "Class:id" and dropped for a class
     * whenever anything in that class is written, so a read after a write in the
     * same request still sees the new row.
     */
    private static array $identityMap = [];

    /** Operators the builder will emit. Anything else is a programming error. */
    private const OPERATORS = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'];

    /** Marks "no value argument was passed", so null can be a real value. */
    private const NO_VALUE = "\0__baustein_no_value__\0";

    /**
     * Optional write observer, called after every successful insert, update and
     * soft delete as ($model, $type, $old, $new).
     *
     * The core has no business knowing what an audit trail is, so it only offers
     * the hook; the app decides what to do with it. Register a listener in
     * src/app/boot.inc.php — an activity log, a cache invalidation, an outgoing
     * webhook. Left null, this costs one null check per write.
     */
    public static $onWrite = null;

    /**
     * Set for the duration of delete(). A soft delete reaches the database as an
     * update that sets deleted_at, but an observer should see a deletion — and
     * should see the whole row that was deleted, not just the two columns that
     * moved, or it cannot say *what* was deleted.
     */
    private ?string $writeTypeOverride = null;
    private ?array $writeOldOverride = null;

    /** Report a write to the observer. Never lets a listener break the write. */
    private function notifyWrite(string $type, ?array $old, ?array $new): void {
        if (self::$onWrite === null) return;

        try {
            (self::$onWrite)(
                $this,
                $this->writeTypeOverride ?? $type,
                $this->writeOldOverride ?? $old,
                $new
            );
        } catch (Throwable $e) {
            if (class_exists('Log', false)) {
                Log::exception('model', $e, ['table' => $this->getTable(), 'write' => $type]);
            }
        }
    }

    public function __construct(array $attributes = []) {
        $this->fill($attributes);
    }

    // --- Static Accessors ---

    public static function query() {
        return new static();
    }

    public static function all() {
        return (new static)->newQuery()->get();
    }

    public static function find($id) {
        if ($id === null || $id === '') return null;

        $key = static::class . ':' . $id;
        if (array_key_exists($key, self::$identityMap)) {
            return self::$identityMap[$key];
        }

        $model = (new static)->newQuery()->whereKey($id)->first();
        self::$identityMap[$key] = $model;
        return $model;
    }

    public static function create(array $attributes) {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Run several writes as one unit — all of them, or none.
     *
     *     Model::transaction(function () use ($listId) {
     *         Item::query()->where('list_id', $listId)->deleteAll();
     *         ItemList::find($listId)->delete();
     *     });
     *
     * Reach for it in any handler that writes more than once. Without it every
     * statement commits on its own, so a failure halfway through leaves the data
     * in a state no code path expects.
     *
     * @return mixed whatever $work returned
     */
    public static function transaction(callable $work) {
        return Storage::engine()->transaction($work);
    }

    /** Drop cached find() results — for a single class, or all of them. */
    public static function forgetCached(?string $class = null): void {
        if ($class === null) {
            self::$identityMap = [];
            return;
        }
        foreach (array_keys(self::$identityMap) as $k) {
            if (str_starts_with($k, $class . ':')) unset(self::$identityMap[$k]);
        }
    }

    // --- Query Builder (Simplified) ---

    /**
     * Conditions are a list, not a map keyed by column.
     *
     * As a map, "where('id',1)->where('id',2)" silently kept only the last one,
     * and there was no room for a boolean joiner, so OR could not be expressed
     * at all.
     */
    protected $queryConditions = [];
    protected $queryLimit = null;
    protected $queryOffset = null;
    protected $queryOrder = null;
    protected $queryColumns = null;
    protected $queryJoins = [];
    protected $queryGroup = null;
    protected $with = [];

    protected function newQuery() {
        return $this;
    }

    /** Clear builder state so an instance can be reused safely. */
    public function resetQuery() {
        $this->queryConditions = [];
        $this->queryLimit = null;
        $this->queryOffset = null;
        $this->queryOrder = null;
        $this->queryColumns = null;
        $this->queryJoins = [];
        $this->queryGroup = null;
        $this->with = [];
        return $this;
    }

    private function pushCondition(array $condition) {
        $this->queryConditions[] = $condition;
        return $this;
    }

    /**
     * where('col', $value)                  -> col = value  (or IS NULL)
     * where('col', '>=', $value)            -> col >= value
     * where('col', 'IS NOT', null)          -> col IS NOT NULL
     *
     * The old signature could not tell where('col', null) from a two-argument
     * call, so whereNotNull('x') collapsed to "x = 'IS NOT'" and quietly matched
     * nothing. func_num_args() removes the ambiguity.
     */
    public function where($column, $operatorOrValue = null, $value = self::NO_VALUE, string $boolean = 'AND') {
        // A sentinel rather than func_num_args(): internal callers such as
        // orWhere() pass the boolean as a fourth argument, which would make an
        // argument count lie about whether a value was actually supplied.
        if ($value === self::NO_VALUE) {
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            $operator = strtoupper(trim((string)$operatorOrValue));
        }

        // Null-aware forms, however they were spelled
        if ($value === null) {
            if (in_array($operator, ['=', 'IS', 'IS NULL'], true)) {
                return $this->pushCondition(['type' => 'null', 'col' => $column, 'bool' => $boolean]);
            }
            if (in_array($operator, ['!=', '<>', 'IS NOT', 'IS NOT NULL'], true)) {
                return $this->pushCondition(['type' => 'notnull', 'col' => $column, 'bool' => $boolean]);
            }
        }

        if (in_array($operator, ['IN', 'NOT IN'], true)) {
            return $this->pushCondition([
                'type' => $operator === 'IN' ? 'in' : 'notin',
                'col'  => $column,
                'val'  => is_array($value) ? $value : [$value],
                'bool' => $boolean,
            ]);
        }

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported SQL operator: {$operator}");
        }

        return $this->pushCondition([
            'type' => 'basic', 'col' => $column, 'op' => $operator, 'val' => $value, 'bool' => $boolean,
        ]);
    }

    public function orWhere($column, $operatorOrValue = null, $value = self::NO_VALUE) {
        return $this->where($column, $operatorOrValue, $value, 'OR');
    }

    public function whereNull($column) {
        return $this->pushCondition(['type' => 'null', 'col' => $column, 'bool' => 'AND']);
    }

    public function whereNotNull($column) {
        return $this->pushCondition(['type' => 'notnull', 'col' => $column, 'bool' => 'AND']);
    }

    public function whereIn($column, array $values) {
        return $this->pushCondition(['type' => 'in', 'col' => $column, 'val' => $values, 'bool' => 'AND']);
    }

    public function whereNotIn($column, array $values) {
        return $this->pushCondition(['type' => 'notin', 'col' => $column, 'val' => $values, 'bool' => 'AND']);
    }

    public function whereKey($id) {
        return $this->where($this->primaryKey, $id);
    }

    /** Escape hatch for expressions the builder cannot model. Bind with ?. */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND') {
        return $this->pushCondition(['type' => 'raw', 'sql' => $sql, 'val' => $bindings, 'bool' => $boolean]);
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER') {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException("Unsupported join type: {$type}");
        }
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported join operator: {$operator}");
        }
        $this->queryJoins[] = [
            'type' => $type, 'table' => $table,
            'first' => $first, 'op' => $operator, 'second' => $second,
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second) {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy($column, $direction = 'ASC') {
        // Kept as column + direction rather than a compiled fragment: the
        // storage engine decides what that means, and only one of them speaks SQL.
        $this->queryOrder[] = [
            'column'    => $column,
            'direction' => strtoupper(trim($direction)) === 'DESC' ? 'DESC' : 'ASC',
        ];
        return $this;
    }

    public function groupBy($column) {
        $this->queryGroup = $column;
        return $this;
    }

    public function limit($limit) {
        $this->queryLimit = max(0, (int)$limit);
        return $this;
    }

    /** Absent before, which made pagination impossible to express. */
    public function offset($offset) {
        $this->queryOffset = max(0, (int)$offset);
        return $this;
    }

    public function forPage(int $page, int $perPage) {
        return $this->limit($perPage)->offset(max(0, ($page - 1) * $perPage));
    }

    public function withTrashed() {
        $this->withDeleted = true;
        return $this;
    }

    public function select(...$columns) {
        if (isset($columns[0]) && is_array($columns[0])) {
            $columns = $columns[0];
        }
        $this->queryColumns = $columns;
        return $this;
    }

    public function with(...$relations) {
        if (isset($relations[0]) && is_array($relations[0])) {
            $relations = $relations[0];
        }
        $this->with = array_merge($this->with, $relations);
        return $this;
    }

    /** Clearer alias — Component::with() means something entirely different. */
    public function withRelations(...$relations) {
        return $this->with(...$relations);
    }

    // --- Handing the query to a storage engine ---

    /**
     * Everything this builder knows, as a plain array.
     *
     * THIS IS THE SEAM. The Model describes what it wants; the engine named by
     * DB_ENGINE decides what that means — SqlStorage compiles it into SQL,
     * FileStorage answers it from JSON files. No SQL is written above this line
     * any more, which is the only reason a second engine could exist without
     * touching a single line of application code.
     *
     * See the Storage class for the full shape of the description.
     */
    protected function queryDescriptor(): array {
        return [
            'table'      => $this->getTable(),
            'primaryKey' => $this->primaryKey,
            'columns'    => $this->queryColumns,
            'conditions' => $this->queryConditions,
            'joins'      => $this->queryJoins,
            'order'      => $this->queryOrder,
            'group'      => $this->queryGroup,
            'limit'      => $this->queryLimit,
            'offset'     => $this->queryOffset,
            // Keyed off $softDelete, not $withDeleted. Keying off the latter
            // meant every model got the scope whether or not its table had the
            // column, and every query against such a table failed outright.
            'softDelete' => $this->softDelete && !$this->withDeleted,
            'indexes'    => $this->indexes,
        ];
    }

    /**
     * What this query would do, described in the engine's own terms — SQL for
     * the SQL engine, a readable plan for the file engine.
     */
    public function toSql(): string {
        return Storage::engine()->explain($this->queryDescriptor());
    }

    public function get($dryRun = false) {
        $query = $this->queryDescriptor();

        if ($dryRun) {
            return Storage::engine()->explain($query);
        }

        $rows = Storage::engine()->select($query);

        $models = array_map(function($row) {
            return (new static($row))->syncOriginal();
        }, $rows);

        $collection = new Collection($models);

        if (!empty($this->with)) {
            $this->eagerLoadRelations($collection);
        }

        return $collection;
    }

    public function first() {
        $previousLimit = $this->queryLimit;
        $this->limit(1);
        $results = $this->get();
        // Restore, so a builder held in a variable is not silently stuck at
        // LIMIT 1 for whatever it is asked next.
        $this->queryLimit = $previousLimit;
        return $results->first();
    }

    /** Counted by the engine, instead of hydrating every row to call count() on it. */
    public function count(string $column = '*'): int {
        return Storage::engine()->count($this->queryDescriptor(), $column);
    }

    public function exists(): bool {
        return Storage::engine()->exists($this->queryDescriptor());
    }

    public function pluck($column, $key = null) {
        $valueKey = (stripos($column, ' AS ') !== false)
            ? trim(substr($column, stripos($column, ' AS ') + 4))
            : $column;
        $keyColumn = $key !== null
            ? ((stripos($key, ' AS ') !== false) ? trim(substr($key, stripos($key, ' AS ') + 4)) : $key)
            : null;

        // Fetch only the columns being plucked. Without this, pluck('title') on
        // a wide table selected every column of every row, hydrated a full model
        // for each, and then read one property off it.
        $previousColumns = $this->queryColumns;
        if ($previousColumns === null) {
            $wanted = [$column];
            if ($key !== null) $wanted[] = $key;
            $this->select($wanted);
        }

        $results = $this->get();
        $this->queryColumns = $previousColumns;
        $arr = [];
        foreach ($results as $model) {
            $value = $model->attributes[$valueKey] ?? null;
            if ($keyColumn !== null) {
                $arr[$model->attributes[$keyColumn] ?? null] = $value;
            } else {
                $arr[] = $value;
            }
        }
        return $arr;
    }

    // --- Persistence ---

    public function fill(array $attributes) {
        foreach ($attributes as $key => $value) {
            if (!empty($this->fillable) && !in_array($key, $this->fillable) && $key !== $this->primaryKey) {
                continue;
            }
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    protected function syncOriginal() {
        $this->original = $this->attributes;
        return $this;
    }

    /** Columns whose value differs from the row we loaded. */
    public function getDirty(): array {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if ($key === $this->primaryKey) continue;
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(): bool {
        return $this->getDirty() !== [];
    }

    public function save() {
        $storage = Storage::engine();

        if (isset($this->attributes[$this->primaryKey])) {
            // Only the changed columns. Every save used to rewrite every column,
            // so renaming a task also rewrote its whole description.
            $dirty = $this->getDirty();
            if ($dirty === []) {
                return true;
            }
            $storage->update(
                $this->getTable(),
                $dirty,
                [$this->primaryKey => $this->attributes[$this->primaryKey]]
            );

            // The previous values of exactly the columns that moved.
            $before = array_intersect_key($this->original, $dirty);
            $this->syncOriginal();
            self::forgetCached(static::class);
            $this->notifyWrite('Update', $before, $dirty);
            return true;
        }

        $id = $storage->insert($this->getTable(), $this->attributes);
        $this->attributes[$this->primaryKey] = $id;

        $this->syncOriginal();
        self::forgetCached(static::class);
        $this->notifyWrite('Insert', null, $this->attributes);
        return true;
    }

    public function delete() {
        if (!isset($this->attributes[$this->primaryKey])) return false;

        if ($this->softDelete) {
            $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
            $this->attributes['deleted_by'] = self::currentUserId();

            $this->writeTypeOverride = 'Soft-Delete';
            $this->writeOldOverride = $this->original;
            try {
                $this->save();
            } finally {
                $this->writeTypeOverride = null;
                $this->writeOldOverride = null;
            }
            return true;
        }

        $before = $this->attributes;
        self::forgetCached(static::class);
        $deleted = Storage::engine()->delete(
            $this->getTable(),
            [$this->primaryKey => $this->attributes[$this->primaryKey]]
        );
        $this->notifyWrite('Force-Delete', $before, null);
        return $deleted;
    }

    /**
     * Soft-delete everything the current query matches, in one statement.
     *
     * Deleting a project used to fetch every child row and call delete() on each
     * — two queries per row, because delete() also called the uncached User().
     * @return int rows affected
     */
    public function deleteAll(): int {
        $query = $this->queryDescriptor();
        $storage = Storage::engine();

        if (!$this->softDelete) {
            $affected = $storage->deleteWhere($query);
        } else {
            $affected = $storage->updateWhere($query, [
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => self::currentUserId(),
            ]);
        }

        self::forgetCached(static::class);
        return $affected;
    }

    // --- Attributes Access ---

    public function __get($key) {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        // Lazy Load Relation
        if (method_exists($this, $key)) {
            if (!array_key_exists($key, $this->relations)) {
                $relation = $this->$key();
                // If it returns a Relation definition (array), resolve it
                if (is_array($relation)) {
                    $this->relations[$key] = $this->resolveLazyRelation($relation);
                } else {
                    $this->relations[$key] = $relation; // Fallback for old style if any
                }
            }
            return $this->relations[$key];
        }
        return null;
    }

    public function __set($key, $value) {
        $this->attributes[$key] = $value;
    }

    public function toArray() {
        return array_merge($this->attributes, array_map(function($rel) {
            if ($rel instanceof Model || $rel instanceof Collection) {
                return $rel->toArray();
            }
            if (is_array($rel)) {
                 // Check if array of models
                 return array_map(fn($m) => $m instanceof Model ? $m->toArray() : $m, $rel);
            }
            return $rel;
        }, $this->relations));
    }

    // --- Eager Loading ---

    protected function eagerLoadRelations(Collection $models) {
        foreach ($this->with as $relationName) {
            if ($models->count() === 0) break;

            // Get relation definition from the first model
            $firstModel = $models->first();
            if (!method_exists($firstModel, $relationName)) continue;

            $relationDef = $firstModel->$relationName();
            if (!is_array($relationDef)) continue; // Must use new relation style

            $type = $relationDef['type'];
            $relatedClass = $relationDef['model'];
            $foreignKey = $relationDef['foreign_key'];
            $localKey = $relationDef['local_key'];

            if ($type === 'hasMany') {
                $this->eagerLoadHasMany($models, $relationName, $relatedClass, $foreignKey, $localKey);
            } elseif ($type === 'hasOne') {
                // Was silently skipped, so Project::workspace() always came back
                // null with no error to explain why.
                $this->eagerLoadHasMany($models, $relationName, $relatedClass, $foreignKey, $localKey, true);
            } elseif ($type === 'belongsTo') {
                $this->eagerLoadBelongsTo($models, $relationName, $relatedClass, $foreignKey, $localKey); // localKey here is ownerKey
            }
        }
    }

    /** Fetch related rows for a set of keys, honouring the related model's own soft-delete setting. */
    private function fetchRelated(string $relatedClass, string $column, array $ids): array {
        $instance = new $relatedClass();
        $builder = $instance->whereIn($column, array_values($ids));
        if ($instance->withDeleted) $builder->withTrashed();
        return $builder->get()->all();
    }

    protected function eagerLoadHasMany(Collection $models, $relationName, $relatedClass, $foreignKey, $localKey, bool $single = false) {
        $ids = [];
        foreach ($models as $model) {
            $val = $model->attributes[$localKey] ?? null;
            if ($val !== null && $val !== '') $ids[] = $val;
        }
        $ids = array_unique($ids);
        if (empty($ids)) return;

        $relatedModels = $this->fetchRelated($relatedClass, $foreignKey, $ids);

        // Match back
        $dictionary = [];
        foreach ($relatedModels as $rel) {
            $dictionary[$rel->attributes[$foreignKey]][] = $rel;
        }

        foreach ($models as $model) {
            $key = $model->attributes[$localKey] ?? null;
            $matches = $dictionary[$key] ?? [];
            $model->relations[$relationName] = $single ? ($matches[0] ?? null) : new Collection($matches);
        }
    }

    protected function eagerLoadBelongsTo(Collection $models, $relationName, $relatedClass, $foreignKey, $ownerKey) {
        $ids = [];
        foreach ($models as $model) {
            $val = $model->attributes[$foreignKey] ?? null;
            if ($val !== null && $val !== '') $ids[] = $val;
        }
        $ids = array_unique($ids);
        if (empty($ids)) return;

        $relatedModels = $this->fetchRelated($relatedClass, $ownerKey, $ids);

        $dictionary = [];
        foreach ($relatedModels as $model) {
            $dictionary[$model->attributes[$ownerKey]] = $model;
        }

        foreach ($models as $model) {
            $key = $model->attributes[$foreignKey] ?? null;
            $model->relations[$relationName] = $dictionary[$key] ?? null;
        }
    }

    protected function resolveLazyRelation($def) {
        $type = $def['type'];
        $relatedClass = $def['model'];
        $foreignKey = $def['foreign_key'];
        $localKey = $def['local_key'];

        $instance = new $relatedClass();

        if ($type === 'hasMany' || $type === 'hasOne') {
            $local = $this->attributes[$localKey] ?? null;
            if ($local === null || $local === '') {
                return $type === 'hasOne' ? null : new Collection([]);
            }
            // get() already returns hydrated models; the previous version passed
            // each one back through the constructor and built them all twice.
            $related = $instance->where($foreignKey, $local)->get();
            return $type === 'hasOne' ? $related->first() : $related;
        }

        if ($type === 'belongsTo') {
             // localKey is ownerKey
             $val = $this->attributes[$foreignKey] ?? null;
             if ($val === null || $val === '') return null;
             return $instance->where($localKey, $val)->first();
        }
        return null;
    }

    // --- Helpers ---

    public function getTable() {
        if ($this->table) return $this->table;
        $className = (new ReflectionClass($this))->getShortName();
        return strtolower($className) . 's';
    }

    /**
     * The acting user id, for deleted_by stamping.
     * Guarded because User() is an application helper and the core should not
     * hard-fail when a Model is used outside the app bootstrap.
     */
    protected static function currentUserId() {
        return function_exists('User') ? User('id') : null;
    }

    public function getKey() {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    // --- Relationships Definitions (Return Config) ---

    protected function hasOne($relatedClass, $foreignKey = null, $localKey = 'id') {
        return [
            'type' => 'hasOne',
            'model' => $relatedClass,
            'foreign_key' => $foreignKey ?: $this->getForeignKey(),
            'local_key' => $localKey
        ];
    }

    protected function hasMany($relatedClass, $foreignKey = null, $localKey = 'id') {
        return [
            'type' => 'hasMany',
            'model' => $relatedClass,
            'foreign_key' => $foreignKey ?: $this->getForeignKey(),
            'local_key' => $localKey
        ];
    }

    protected function belongsTo($relatedClass, $foreignKey = null, $ownerKey = 'id') {
        $instance = new $relatedClass();
        return [
            'type' => 'belongsTo',
            'model' => $relatedClass,
            'foreign_key' => $foreignKey ?: $instance->getForeignKey(),
            'local_key' => $ownerKey
        ];
    }

    protected function getForeignKey() {
        return rtrim($this->getTable(), 's') . '_id';
    }

    protected function joinTableName($relatedTable) {
        $tables = [$this->getTable(), $relatedTable];
        sort($tables);
        return implode('_', $tables);
    }
}
