<?php

/**
 * A model — one class per table.
 *
 * This one is a working example with nothing behind it: create an `items`
 * table and it runs as written. Rename it, or delete it once you have models
 * of your own.
 *
 * THE TABLE IT EXPECTS
 *
 *   CREATE TABLE `items` (
 *     `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
 *     `title`      VARCHAR(255) NOT NULL,
 *     `notes`      TEXT NULL,
 *     `status`     VARCHAR(32) NOT NULL DEFAULT 'open',
 *     `owner_id`   INT UNSIGNED NULL,
 *     `created_at` DATETIME NULL,
 *     `updated_at` DATETIME NULL,
 *     `deleted_at` DATETIME NULL,
 *     `deleted_by` INT UNSIGNED NULL,
 *     PRIMARY KEY (`id`),
 *     KEY `idx_items_status` (`status`),
 *     KEY `idx_items_deleted_at` (`deleted_at`)
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * NOTE THE deleted_at COLUMN. Soft delete is ON by default: every query adds
 * "deleted_at IS NULL" and delete() sets the column instead of removing the
 * row. A table without it must say so — protected $softDelete = false; —
 * or every query against it fails.
 */
class Item extends Model
{
    /** Defaults to strtolower(class) . 's', which is naive — say it explicitly. */
    protected $table = 'items';

    protected $primaryKey = 'id';

    /**
     * Columns that may be mass-assigned by create() and fill().
     *
     * This is the guard between a request body and your table: without it, a
     * crafted payload could set any column at all. An empty array means "no
     * filtering", which is only safe when you never pass request data straight
     * into fill().
     */
    protected $fillable = ['title', 'notes', 'status', 'owner_id', 'created_at', 'updated_at'];

    /** Set to false for a table with no deleted_at column. */
    protected $softDelete = true;

    /** A hint for the file engine; the SQL engine takes its indexes from the schema. */
    protected $indexes = ['status', 'owner_id'];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------
    // A relation is a METHOD that returns a definition array. Read it as a
    // property to resolve it:  $item->owner
    //
    // Reading it per row inside a loop is one query per row — the classic N+1.
    // Load them in one go instead:
    //     Item::query()->where('status', 'open')->with('owner')->get()

    /*
    public function owner()
    {
        // this table's owner_id -> users.id
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function comments()
    {
        // comments.item_id -> this table's id
        return $this->hasMany(Comment::class, 'item_id');
    }

    public function settings()
    {
        return $this->hasOne(ItemSettings::class, 'item_id');
    }
    */

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------
    // Put the queries the application actually asks for here, named after the
    // question they answer. One definition, used everywhere — so when the
    // meaning of "open" changes, it changes once.

    /** Open items, newest first. */
    public static function open(int $limit = 50): Collection
    {
        return static::query()
            ->where('status', 'open')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /** Everything a person owns. */
    public static function ownedBy($userId): Collection
    {
        if (empty($userId)) return new Collection([]);

        return static::query()
            ->where('owner_id', $userId)
            ->orderBy('id', 'DESC')
            ->get();
    }

    /**
     * How many open items there are.
     *
     * count() counts in SQL. Doing get()->count() would fetch and hydrate every
     * row in order to throw them all away.
     */
    public static function openCount(): int
    {
        return static::query()->where('status', 'open')->count();
    }

    /*
     * More of the builder, for reference:
     *
     *   ->where('col', $v)                    ->where('col', '>=', $v)
     *   ->orWhere('col', $v)                  ->whereIn('col', [1,2,3])
     *   ->whereNull('col')                    ->whereNotNull('col')
     *   ->whereRaw('(a = ? OR b = ?)', [$x, $y])
     *   ->join('t', 'items.x', '=', 't.y')    ->leftJoin(…)
     *   ->select('id', 'title')               ->groupBy('status')
     *   ->orderBy('title')                    ->forPage($page, 25)
     *   ->first()  ->exists()  ->pluck('title', 'id')  ->toSql()
     *   ->withTrashed()                       // include soft-deleted rows
     */

    // -------------------------------------------------------------------------
    // Writing
    // -------------------------------------------------------------------------

    /**
     * Create an item with its timestamps set.
     *
     * created_at/updated_at are not automatic — stamp them here or give the
     * columns database defaults. Doing it in one named method means every
     * caller gets it right.
     */
    public static function add(string $title, array $extra = []): self
    {
        $now = date('Y-m-d H:i:s');

        return static::create(array_merge([
            'title'      => trim($title),
            'status'     => 'open',
            'owner_id'   => function_exists('User') ? User('id') : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $extra));
    }

    /**
     * save() only writes the columns that actually changed, and does nothing at
     * all when none did — so calling it after an unchanged edit is free.
     */
    public function touchNow(): self
    {
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
        return $this;
    }

    /*
     * Typical use in a handler:
     *
     *   $item = Item::find($request->get('record'));
     *   if (!$item) return Event::make()->send();
     *
     *   $item->status = 'done';
     *   $item->touchNow();                 // save() is dirty-aware
     *
     *   $item->delete();                   // soft delete
     *   Item::query()->where('owner_id', $id)->deleteAll();   // bulk, one statement
     */
}
