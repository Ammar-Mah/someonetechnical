<?php

/**
 * Application bootstrap.
 *
 * Runs on EVERY request — full page loads and every updater.php interaction
 * alike. That is the thing to keep in mind here: whatever you add is paid for
 * on every click in the interface, so anything expensive belongs behind
 * Cache::remember() with a TTL.
 *
 * Three jobs, in order:
 *   1. expose shared reference data through app_data()
 *   2. define the app-wide helpers (User(), and your own)
 *   3. register framework hooks (Model::$onWrite, and so on)
 */

// -----------------------------------------------------------------------------
// 1. Shared reference data
// -----------------------------------------------------------------------------

/** @var array $APP_DATA data every request needs, loaded once. */
$APP_DATA = [];

/**
 * Read shared data.
 *
 *   app_data()               the whole array
 *   app_data('translations') one entry, or null
 */
function app_data(?string $key = null)
{
    global $APP_DATA;
    if ($key === null) return $APP_DATA;
    return $APP_DATA[$key] ?? null;
}

/** Drop the cached reference data — call after editing any of it. */
function app_data_invalidate(): void
{
    Cache::forget('app_data.reference');
}

/*
 * Lookup tables belong here: things that are read constantly and written
 * rarely. Cached together under one key so it is one file read rather than
 * several queries, and re-queried when the TTL expires.
 *
 * $reference = Cache::remember('app_data.reference', APP_DATA_TTL, function () {
 *     return [
 *         'users'    => User::all()->toArray(),
 *         'statuses' => Status::query()->orderBy('position')->get()->toArray(),
 *     ];
 * });
 * $APP_DATA['users']    = $reference['users'];
 * $APP_DATA['statuses'] = $reference['statuses'];
 *
 * A note on failure: only cache a COMPLETE result. Caching a partial one
 * freezes an empty list in place for the whole TTL, where the uncached
 * behaviour would simply retry on the next request.
 */

// The interface language for this request. trans() reads this, so it has to be
// set before anything renders.
$APP_DATA['translations'] = getTranslation(currentLanguage());

// -----------------------------------------------------------------------------
// 2. Application helpers
// -----------------------------------------------------------------------------

/**
 * The session's visitor, or one of their fields.
 *
 *   User()          the whole row as an array, or null
 *   User('id')      one field, or null — "visitor:" and 32 hex characters
 *   User(null, true) re-read
 *
 * Nobody signs in: public/index.php gives each session an anonymous visitor
 * id, and there is no users table behind it.
 *
 * Memoised per request: it is called from inside render loops and from
 * Model::delete(), so a single request can reach it dozens of times.
 *
 * The framework calls this — when it exists — to stamp deleted_by on a soft
 * delete. Keep the name and the shape even if the storage changes.
 */
function User(?string $key = null, bool $fresh = false)
{
    static $cachedId = null;
    static $cachedUser = null;

    $userId = Session::get('user') ?? Session::get('user_id') ?? Session::get('userId');
    if (empty($userId)) {
        return null;
    }

    if ($fresh || $cachedId !== $userId) {
        $cachedUser = ['id' => $userId, 'name' => 'Visitor', 'email' => ''];
        $cachedId = $userId;
    }

    if ($key === null) return $cachedUser;
    return is_array($cachedUser) ? ($cachedUser[$key] ?? null) : null;
}

// -----------------------------------------------------------------------------
// 3. Framework hooks
// -----------------------------------------------------------------------------

/*
 * Model::$onWrite fires after every successful insert, update and delete, with
 * exactly the columns that changed. The core only offers the hook; ATLAS turns
 * it on. Every write is a positive event, and the audit channel is how a
 * reviewer confirms that a handler changed what it claimed to change — see
 * .agent/policies/logging.md. If a table ever holds a sensitive column, filter
 * it out of old/new here rather than removing the hook.
 */
Model::$onWrite = function (Model $model, string $type, ?array $old, ?array $new) {
    Log::info('audit', $type . ' ' . get_class($model), [
        'id'  => $model->getKey(),
        'old' => $old,
        'new' => $new,
    ]);
};

// Everything above is fixed per-request cost. Marking it here is what lets the
// request summary separate "boot" from "actual work" (LOG_METRICS).
Log::mark('boot_done');
