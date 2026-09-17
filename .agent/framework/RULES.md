# Framework Rules: Baustein (the IDEALS microframework)

Centrally managed by ATLAS. Authoritative for structure and convention in
Baustein projects. Read before writing any code.

## 0. The manual comes first

**`LLM.txt` at the project root is the complete reference for this framework.**
It documents every class, method, setting and gotcha as implemented, and it was
written to be read by an agent. This file does not repeat it. This file tells
you how ATLAS expects you to work *within* it.

Read order for a new session: `LLM.txt` §1–3 (the mental model), then §21
(sharp edges), then §24 (cheat sheet), then §25 (what ATLAS adds to the
template). Open the section you need when you need it: components §4,
templates §5, events §6, dispatch §7, the client §8, models §10,
session/state/cache §13, the component kit §19, recipes §20.

**The code is the source of truth for `LLM.txt`.** ATLAS maintains the manual
against the code — every method, key and file it names is cross-checked. If
you find the two disagree, the manual is wrong: fix `LLM.txt` in the same PR
as the change, and say so in the PR. Never leave a known discrepancy for the
next reader. If this file and `LLM.txt` disagree about how to *work*, this
file wins.

## 1. What it is

A server-driven PHP framework. All UI logic lives on the server; the browser is
a thin terminal. The cycle for every interaction:

```
user clicks / types
  → Baustein.js POSTs the element's attributes (+ its component's) to updater.php
  → updater.php rebuilds the named component from those attributes
  → calls the named handler method
  → the handler returns an Event: DOM instructions ("set #x's inner HTML", …)
  → the runtime applies them through a DOM diff, so focus and scroll survive
```

Component state **round-trips through the DOM**: public properties are rendered
as attributes on the component's root element and posted back on the next
interaction. No server-side instance survives between requests. This is the
single most important thing to understand — `LLM.txt` §1 and §4.3.

No build step, no npm, no Composer, no JSON API layer, no client router, no
client-side state. Do not add any of them.

PHP 8.2 or later. Shared hosting: Apache with `.htaccess`, MySQL, SQLite or the
built-in file engine, no long-running processes.

## 2. What you may touch

| Path | Ownership | Rule |
| --- | --- | --- |
| `src/core/` | **Framework** | Read-only. Never edit. Extend or override from `src/app/`. |
| `updater.php`, `health.php` | **Framework** | Read-only. |
| `public/js/Baustein.js`, `public/js/ui.js`, `public/css/Baustein.css` | **Framework** | Read-only. Override CSS from `app.css`; add behaviour through handlers, not by editing the runtime. |
| `tests/run.php` | **Framework** | Read-only. Add cases in `tests/cases/`. |
| `LLM.txt` | **Framework** | Read-only. |
| `src/app/` | **Yours** | Components, Events, Models, Views, Translations, `boot.inc.php`. |
| `public/index.php` | **Yours** | Sign-in and the page allowlist. |
| `public/css/app.css` | **Yours** | Loaded after the framework stylesheet, so it wins. |
| `runtime.php` | **Yours** | Configuration and defaults. No secrets. |
| `tests/cases/`, `tests/snapshots/` | **Yours** | Your tests and their snapshots. |
| `database/` | **Yours** | Schema changes for the SQL engine. See §8. |
| `__dev/`, `.htaccess` | **ATLAS** | Deployment and validation tooling. A project's own `.htaccess` rules go between its `project rules` markers. |
| `.deployignore`, `.deployignore.production` | **Yours**, seeded by ATLAS | What each server keeps. |

A change inside a framework path fails review, whatever it fixes. If the
framework genuinely needs a change, that is an Issue against the ATLAS
repository, and the project waits: `atlas sync` replaces every Framework and
ATLAS path above with ATLAS's current copy, so the fix arrives with the next
sync.

`src/app/Components` is searched *before* `src/core/Components`, so a file of
the same name **replaces** a core component outright. Prefer extending under a
new name — `class PrimaryButton extends Button` — which needs no registration.
Replace only when extending cannot express the change, and say so in the PR.

## 3. Request lifecycle

Two entry points, both at the **project root**:

```
index.php      full page load
                 src/core/inc/initialize.inc.php   constants, autoload, error handlers, session
                 src/core/inc/functions.inc.php    global helpers
                 src/app/boot.inc.php              your bootstrap — runs on EVERY request
                 public/index.php                  sign-in, page allowlist, Template::view()

updater.php    every interaction: the same three includes, then four security
               gates (session user, CSRF, Component subclass, declared public
               instance method), then dispatch
```

The **document root is the project root**. `Baustein.js` resolves `updater.php`
relative to the page's directory, so a page served from `/public/index.php`
posts to `/public/updater.php`, which does not exist, and every interaction
fails with a 404 that looks like a broken handler. Never link to
`public/index.php`. The manual says both routes work; for page rendering they
do, for interactions only the root does.

`boot.inc.php` runs on every page load **and** every click. Anything expensive
there is paid for on every interaction — cache it with `Cache::remember()`.

After boot, warnings and notices are `ErrorException`s. An undefined array key
or a failed `file_get_contents` is **fatal**. Guard with `isset`, `??`,
`is_file`.

## 4. Non-negotiables

1. **One class per file, filename identical to the class name, no namespaces.**
   The autoloader finds `src/app/Components/ItemsScreen.php` for `ItemsScreen`
   and nothing else. Case matters on the server.
2. **A handler is a public, non-static, non-magic method declared on your own
   class.** `updater.php` refuses anything else — including every method
   inherited from `Component` or `Handler`. Static methods are not dispatchable.
3. **A handler returns `Event::make()->…->send()`.** Always. An empty
   `Event::make()->send()` is a valid "do nothing".
4. **Send back only what changed.** A handler that re-renders a whole screen
   when one row moved is the most expensive mistake available here.
5. **`{{ }}` escapes.** A string is data; a Component is markup; `raw()` is
   markup because you said so. A helper that returns pre-rendered markup as a
   string gets escaped and the page fills with `&lt;div&gt;`. Return the
   component, or `raw()`. Inside `raw()` you are on your own: `e()` every value.
6. **Authorise inside handlers.** The framework authenticates the session. It
   knows nothing about which rows this user may touch. `$request->get('record')`
   is a number the browser sent; treat it that way.
7. **Every model table has `deleted_at`** (and optionally `deleted_by`), or its
   model declares `protected $softDelete = false;`. Otherwise every query
   against it fails outright.
8. **Declare `$fillable`.** It is the guard between a request body and your
   table.
9. **Multiple writes go in `Model::transaction()`.**
10. **Never touch `$_SESSION` directly after boot.** `Session` and `State` only —
    the session lock is released before dispatch, and a direct write is lost.
11. **Stable ids and `key="…"`** on anything an event will target or a list
    will re-render. Random component ids change on every render.
12. **Never edit a framework path.** §2.
13. **Every handler logs its outcome.** `info` when it did what was asked,
    `warn` when it refused, with the ids involved. Writes are audited by the
    hook in `boot.inc.php`. The log is read before the screen. §11.

## 5. Building a feature

The pieces, and where each goes:

| Piece | Where | What it is |
| --- | --- | --- |
| A page | `src/app/Views/<name>.php` + the `$views` list in `public/index.php` | `@extend('app')`, one `@section('content')`, usually a single `<x:Screen/>` |
| A screen or widget | `src/app/Components/<Name>.php` | `class Name extends Component` — a template, public properties, `mount()` |
| Application flows | `src/app/Events/<Name>.php` | `class Name extends Handler` — a group of handlers with no template |
| A table | `src/app/Models/<Name>.php` | `class Name extends Model` — with the `CREATE TABLE` in its docblock |
| Reference data, helpers, hooks | `src/app/boot.inc.php` | Runs on every request. Cache what is expensive. |
| A language | `src/app/Translations/<code>.json` | Keys are the lowercased English strings |

The recipe for an interactive feature is `LLM.txt` §20.2, and the demo in
`src/app/` is a complete worked example: `ItemsScreen` renders regions with
stable ids; `AppHandler` re-renders exactly those regions; `Item` holds the
queries, named after the question they answer. Read them before writing
anything like them. Delete them once real screens exist.

Conventions the demo establishes, which review will hold you to:

- **Behaviour intrinsic to a widget lives on the component; application flows
  live in an Events class.** Address a handler by class:
  `->onClick([AppHandler::class, 'save'])` — the editor resolves it, a rename
  survives, and in `DEBUG_MODE` a missing method is reported at render time.
- **Region ids are constants on the screen** (`ItemsScreen::LIST_ID`), and a
  handler re-renders a region by targeting the constant, never a string
  repeated in six places.
- **Build repeated HTML in `mount()` or a setter, assign it to a property,
  print `{{$property}}`.** Not in `{% %}` blocks. Every core component is
  written this way.
- **Queries live on the model, named after the question**: `Item::open()`,
  `Item::ownedBy($id)`, `Item::openCount()`. One definition, used everywhere.
- **What is data and what is one person's view of it**: rows go in a table;
  a filter, an open panel, the chosen theme go in `State`.
- **Validation is an early return** that says what is wrong and changes
  nothing else: add `is-invalid`, toast, `->send()`.
- **A modal is rendered when needed and removed when done.** No show/hide
  state.

## 6. Components and templates — the rules that bite

- `mount()` runs at the end of the constructor. Initialise there.
- Every scalar public property is emitted as an attribute and posted back.
  Declare `protected array $expose = [...]` on wide components: each attribute
  costs HTML size and rides along in every subsequent payload.
- A property the template mentions as `$name` is *not* emitted as an
  attribute. A property that must never reach the DOM is declared `private`.
- `<x:Comp attr="…">` attributes require **double quotes**; the tag name is
  `ucfirst()`'d and must equal the class name.
- `removeClass()` also edits the template source by substring. Avoid.
- A view file's own PHP variables do not reach its `{{ }}` expressions. Build
  markup in a component, or pass it through `$data`.
- In a full page every `<script>` is moved to just before `</body>`. The CSRF
  token therefore travels on a `<meta>` tag and nowhere else.
- Templates are compiled to `cache/templates/` keyed by the **md5 of the
  source**, so a changed template compiles afresh on its own. Nothing to clear.
  A template that will not compile is logged on the `template` channel and
  falls back to the interpreter — it degrades rather than breaks, so check the
  log when output looks odd.

## 7. Handlers and events — the rules that bite

- Take `Request $request` and read `$request->get('key')`, or read
  `$this->key` — the component was rebuilt from the payload. Both work; the
  `Request` form makes the input explicit.
- A form posts every named field as **one JSON object in `value`**:
  `$data = json_decode((string) $request->get('value'), true) ?: [];`
- `input` events are debounced 300 ms per element. `change` on a file input
  arrives as base64 after 1 s.
- Event targets are selectors and update **all** matches. `append()` is an
  upsert by id of the first element in the HTML. `value()` sets the attribute
  only; `setValue()` is what changes what the user sees.
- `->call('fn', [...])` resolves a dotted name against `window` — no eval.
  Arguments arrive as strings, split on commas.
- Surface handlers (`$toggle(#x,hidden)`) never reach the server. Use them for
  pure UI.
- Errors inside a handler come back as `{status:'error', message, rid}`. The
  `rid` is the request id: grep `logs/app-<day>.log.php` for it.
- In `DEBUG_MODE` a refused request explains itself ("did you mean
  `addItem()`?"). In production every refusal is the flat "Unknown action."

## 8. Data

### Engines

`DB_ENGINE` is `file` or `sql`, and the Model layer is identical on both.

- **`file`** — one JSON file per table under `DB_PATH` (default `data/`),
  created by the first insert. No server, no schema. Comfortable into the low
  tens of thousands of rows and one writer at a time. `whereRaw()` and
  `groupBy()` throw. `data/` sits inside the document root; the engine writes
  its own `.htaccess` and the root `.htaccess` refuses the directory too.
- **`sql`** — over PDO, every value bound, every identifier quoted. The
  database is MySQL/MariaDB when `DB_NAME` names one, and otherwise SQLite in
  `DB_PATH/database.sqlite` (`SqliteDatabase`). So `sql` needs no server, and
  a server moves to MySQL by naming a database in its `runtime.local.php`.

The engine is set in `runtime.php`; which database is each server's. A
prototype on `file` moves to `sql` with no application change.

### Schema changes — the ATLAS convention

Baustein has **no migration system**. On the file engine there is nothing to
migrate. On the SQL engine the framework's convention is a `CREATE TABLE` in
the model's docblock, applied by hand. ATLAS needs that to be versioned and
reversible (`policies/database.md`), so:

```
database/0001_create_items.sql          the change, applied in name order
database/0001_create_items.down.sql     its reverse — REQUIRED
```

- **One file, both databases.** A server without `DB_NAME` runs it on SQLite,
  one with it on MySQL, and the checks run every pair up, down and up on
  both. Write the subset both read: the key exactly as
  `id INTEGER PRIMARY KEY AUTO_INCREMENT`; `INTEGER`, `VARCHAR(n)`, `TEXT`,
  `DATETIME`, `DECIMAL(p,s)`; `UNIQUE (...)` and `FOREIGN KEY` as table
  constraints; indexes as `CREATE INDEX name ON table (column)`. No
  `ENGINE`, `CHARSET`, `COLLATE` or `COMMENT` clauses, no `ENUM`, no `KEY`
  inside `CREATE TABLE`, no `MODIFY`, `CHANGE` or `ADD INDEX`.
  `SqliteDatabase::schema()` makes the three adjustments SQLite needs:
  `AUTOINCREMENT`, text compared case-insensitively as MySQL compares it,
  and `DROP INDEX` without its table.
- Keep the `CREATE TABLE` in the model docblock too; it is what the framework
  expects and what a reader looks at first.
- Every table: `id INTEGER PRIMARY KEY AUTO_INCREMENT`, `deleted_at DATETIME NULL`
  unless `$softDelete = false`, indexes on what you filter, join and sort on.
  `created_at`/`updated_at` are **not** automatic — stamp them in a named
  method (`Item::add()`) or give them database defaults.
- One statement per `;` at the end of a line. No `DELIMITER` blocks.
- **DEV** applies them through the token-protected `POST /__dev/migrate`,
  which records each file in `schema_migrations`. `deploy-dev.yml` calls it
  after every upload that carries schema files, and then requires
  `/__dev/probe` to report `migration_status: current`.
- **Production** applies the same files as part of the release a person
  approved: `deploy-prod.yml` uploads the project's migrator alone, under a
  random name with a one-time token, applies what is pending, removes it, and
  the smoke check requires it gone. `__dev/` itself never ships. A rollback
  does not reverse a schema change; the release notes list the files and
  their reverses.
- Expand-migrate-contract sequencing across releases applies as everywhere:
  `policies/database.md`.

### Queries

- Name the columns on wide tables: `->select('id', 'title')` before `pluck()`.
- Eager-load anything rendered per row: `->with('owner')`. A lazy relation in
  a loop is the N+1.
- `->count()`, `->exists()`, `->deleteAll()` — not `get()->count()`,
  `first() !== null`, or a delete loop.
- Set `$table` explicitly; the default pluralisation is naive (`Status` →
  `statuss`). Pass foreign keys explicitly for the same reason.
- `Model::$onWrite` in `boot.inc.php` is where an audit trail plugs in.

## 9. Configuration and secrets

`runtime.php` is committed and holds defaults. Every key becomes a PHP
constant. **No secret in it, ever.**

Two server files are merged over it, the later winning. On DEV the deployment
writes `runtime.dev.php` on every deploy — `APP_ENV`, `APP_URL`, `DEBUG_MODE`,
`DEV_PROBE_TOKEN` — so a DEV server needs nothing done to it by hand. A person
writes `runtime.local.php` on a server for what that leaves out: database
credentials, mail, and on production everything. It is deploy-ignored, so it
survives every deployment and never leaves its server. Both are git-ignored;
`runtime.local.example.php` shows the shape.

> The framework itself reads only `runtime.php`. The manual's advice to "copy
> to runtime.local.php" describes a file nothing loads. The ATLAS template's
> `runtime.php` therefore merges both files over its own array at the bottom —
> that is the mechanism, and it lives in the app-owned config file, not in the
> framework. Do not remove it.

Keys that matter operationally:

| Key | Rule |
| --- | --- |
| `APP_URL` | Absolute URL of `public/`, **with a trailing slash**. The usual reason assets 404 after a move. |
| `APP_ENV` | `development` or `production`. The probe reports it; validation refuses an environment that does not say what it is. |
| `DEBUG_MODE` | `true` on local and DEV — refusals and handler errors explain themselves. **`false` in production.** |
| `APP_TIMEZONE` | The **database server's** zone, so PHP dates and `CURRENT_TIMESTAMP` agree. |
| `DB_ENGINE` | `file` or `sql`, in `runtime.php`. Under `sql`, a server whose `runtime.local.php` sets `DB_NAME` uses that MySQL database; any other runs SQLite in `DB_PATH`. |
| `DEV_PROBE_TOKEN` | Gates `/__dev/diagnostics` and `/__dev/migrate`. Written on DEV by the deployment, derived from the DEV password and the project name; `atlas token` prints it. |
| `MAIL_TRANSPORT` | `log` until a real sender is configured. `MAIL_REDIRECT_ALL_TO` for testing delivery. |

`initialize.inc.php` forces `display_errors` on regardless of configuration.
Production safety rests on `DEBUG_MODE` being `false`: the exception handler
then renders a neutral box and logs the detail — for exceptions, and for parse
errors in files included after boot. What it cannot catch prints with file
paths: a fatal at shutdown (memory, time limit) or a parse error in
`runtime.php` or `index.php` themselves. Keep those two files trivially valid,
deploy nothing that has not passed `php -l`, and treat a production server with
`DEBUG_MODE` true as an incident.

## 10. Testing

```bash
php tests/run.php              # everything — no dependencies, no database
php tests/run.php model        # cases whose filename matches
php tests/run.php --update     # accept the current output as the snapshot
```

Exits non-zero on failure; `checks.yml` runs it on every PR.

- Cases are plain PHP files in `tests/cases/` using `same()`, `ok()`,
  `contains()`/`lacks()`, `throws()` and `snapshot()`. Add a case file per
  feature area; keep the framework's own cases.
- **Do not reflexively `--update`.** A snapshot mismatch prints the first
  differing line. Read it, decide whether the change was intended, then
  update. A snapshot proves the new code renders what the old code rendered;
  it cannot notice that both are wrong.
- `tests/cases/view.php` renders a whole page and asserts no markup is
  double-escaped. Keep it passing: it catches the `raw()` mistake that
  component-level snapshots cannot see.
- Tests run against a scratch data directory in the system temp folder — they
  never touch `data/`. They do write `cache/` and `logs/` in the project; both
  are git-ignored. Some of what they write is *deliberate failure* — the
  storage case throws inside a transaction to prove the rollback, the template
  case renders an unknown tag — and those land in the log as real `error` and
  `warn` lines. A probe on a workstation therefore shows `errors_recent` for
  fifteen minutes after a run. Expected there; never on DEV, where the suite
  does not run.
- **The render snapshot embeds `APP_NAME` and `APP_URL`** — the kit's `Logo`
  renders both into its markup. Changing `APP_NAME` is a deliberate `--update`
  whose diff is exactly the Logo lines. Overriding `APP_URL` in a local
  `runtime.local.php` makes those same two lines differ on your machine; that
  is why the committed `APP_URL` default matches the WAMP layout and DEV/PROD
  set theirs on the server. Never `--update` a snapshot that carries a local
  URL into the repository.

Every bug fix gets the case that would have caught it. Every handler with a
branch gets a case for each branch — the `model.php` case file shows how to
assert on the query a builder produces without a database.

## 11. Logging — the primary instrument

Read the log before the screen, the database or a stack trace
(`policies/logging.md`). The framework's logger is built for exactly that.
This section is what it does, verified against `src/core/Classes/Log.php`.

### What the framework gives you

| | |
| --- | --- |
| Files | `logs/app-YYYY-MM-DD.log.php`, one per day: JSON Lines behind a `<?php exit; ?>` guard line, rotated to `app-YYYY-MM-DD.1.log.php` past `LOG_MAX_FILE_KB`. The directory is created on demand. |
| Line | `{"ts","ms","rid","lvl","ch","msg","ctx"}` — time, milliseconds since the request began, request id, level, channel, message, context |
| Levels | `debug` < `info` < `warn` < `error`; `LOG_LEVEL` is the minimum recorded |
| Channels | free-form strings; `LOG_CHANNELS` is an allowlist, `[]` meaning all |
| Request id | 8 hex characters, new per request, on every line; every handler error returns it to the client as `rid` |
| Cost | free when `LOG_ENABLED` is false. Otherwise buffered in memory and written with one append at shutdown; a cap of 2000 lines, past which drops are counted and reported on the `log` channel. A closure passed as context is only called if the line will be recorded. |
| Fatal capture | a fatal that ends the request is still written, on channel `fatal` |
| Request summary | with `LOG_METRICS`, one `request complete` line per request on channel `request`: method, uri, status, ms, mem_kb, user, `ms_boot` / `ms_work` (from `Log::mark('boot_done')`), every mark, queries, ms_db, bytes, every `Log::count()` and `Log::set()` — `updater.php` sets `comp`, `func` and `actions` |
| Query timing | every query is counted into the summary; a line is written only when it is slower than `LOG_SLOW_QUERY_MS` (`warn`, `slow query`) or the `db` channel is at debug |
| Never breaks the request | an unwritable `logs/` means nothing is written — silently. The probe's `log.writable` is how you find out. |

```php
Log::info('app', 'item added', ['id' => $item->id]);      // debug / info / warn / error
Log::exception('app', $e, ['item' => $id]);               // class, message, file, line, 12 trace lines
Log::warn('app', 'export skipped', fn () => ['rows' => count($this->rows())]);  // built only if recorded
Log::wants(Log::DEBUG, 'db');                              // cheap pre-check
Log::mark('rendered');  Log::count('items_added');  Log::set('screen', $name);   // → the request summary
Log::requestId();  Log::enabled();
```

### What the core already logs

| Channel | Level | Event |
| --- | --- | --- |
| `request` | info | `request complete` — the per-request summary (`LOG_METRICS`) |
| `health` | info | `health answered` — each `GET /health` |
| `security` | warn | every `updater.php` refusal: unauthenticated, bad CSRF, not a component, no such method, not a handler — with the reason |
| `updater` | error | an exception inside a handler, with the `rid` the client received |
| `uncaught` | error | an uncaught exception on a page load |
| `fatal` | error | a fatal error captured at shutdown |
| `db` | error / warn / debug | query failed; transaction rolled back; table file unreadable; unknown `DB_ENGINE` fallback; slow query; every query at debug |
| `template` | error / warn / info / debug | a template that would not compile and fell back to the interpreter; unknown `<x:>` tag; view or layout not found; cache pruned; undefined variable at debug |
| `mail` | info / warn / error | `Sent`; accepted but not delivered under `MAIL_TRANSPORT=log`; invalid message or recipient dropped; unknown transport; send failed |
| `ui` | warn | at render time in `DEBUG_MODE`: a handler reference that is not `[Class::class, 'method']`, or a method that does not exist |
| `autoload` | warn | class not found |
| `model` | error | the `Model::$onWrite` listener threw |
| `log` | warn | the buffer filled and lines were dropped |
| `audit` | info | every insert, update, soft-delete and force-delete with the changed columns — the hook in `boot.inc.php`, which the ATLAS template turns on |
| `atlas` | info / warn / error | the DEV tooling: probe answered, diagnostics served or refused, migration applied, reversed, refused or failed |

What the core does **not** log: a handler that succeeded. Without `LOG_METRICS`
a click that worked leaves no line at all. That gap is yours to close.

### What your code must log

1. **Every handler records its outcome** on channel `app` — or a domain
   channel (`orders`, `billing`) once the application is large enough to want
   one:

   ```php
   $item = Item::add($title);
   Log::info('app', 'item added', ['id' => $item->id, 'by' => User('id')]);

   if ($title === '') {
       Log::warn('app', 'item rejected', ['reason' => 'empty title']);
       return Event::make()->add('#' . ItemsScreen::INPUT_ID, 'is-invalid')->send();
   }
   ```

   `info` for the expected outcome, `warn` for a refusal the code handled.
   Nothing for an exception: let it propagate, and `updater.php` logs it once
   with the `rid` it returns to the client.
2. **Messages are fixed phrases, past tense; variables go in context.**
   `'item added'` with `['id' => 42]`, never `"item 42 added"`. A fixed phrase
   is greppable and countable.
3. **Writes are audited** by the `Model::$onWrite` hook in `boot.inc.php`. Do
   not remove it. If a table holds a sensitive column, filter it out of
   `old` / `new` there.
4. **Authentication events** on channel `auth` — signed in (user id), sign-in
   failed (attempted identifier, ip, never the password), signed out,
   privilege changed — are the contract for whatever replaces the starter
   sign-in.
5. **External calls** on `external`: endpoint, duration, outcome. Mail is
   already covered.
6. **Never in a context array**: passwords, tokens, session ids, card numbers,
   full request payloads, personal data beyond an id.
7. **Counters for the summary**: `Log::count('items_added')` for anything a
   request is worth ranking by.

### Configuration per environment

| | `LOG_LEVEL` | `LOG_METRICS` | `LOG_SLOW_QUERY_MS` | Why |
| --- | --- | --- | --- | --- |
| local | `info` (`debug` while chasing) | `true` | 200 | see every request |
| DEV | `info` | `true` | 200 | the summary line is the heartbeat the pipeline and agents read |
| production | `info` | `false` | 200 | positive events stay; a line per request is noise at volume |

Chasing one thing: `LOG_LEVEL => 'debug'` and `LOG_CHANNELS => ['db']` in
`runtime.local.php`, then put them back. Never `warn` as a level in any
environment: it discards every positive event, and the log can then only say
what went wrong.

### Reading the log

The probe carries the logger's state — `log.enabled`, `log.writable`,
`log.last_entry_at`, `log.errors_recent`, `log.warnings_recent`,
`log.last_error` — so one unauthenticated call says whether DEV can be
observed and whether anything went wrong in the last fifteen minutes. The
deployment pipeline fails on `errors_recent`.

The lines themselves, on DEV, through the diagnostics endpoint (token in
`X-Dev-Token`):

```
/__dev/diagnostics?check=log&since=15m                 info and above, last 100
/__dev/diagnostics?check=log&since=15m&ch=app          one channel
/__dev/diagnostics?check=log&rid=3f9a1c02              everything that request did
/__dev/diagnostics?check=errors&since=15m              warn and error only
```

Parameters: `since` (`15m`, `2h`, `1d`), `level` (the minimum), `ch`, `rid`,
`limit` (at most 500). A `rid` search ignores the window.

On a server, or locally: `tail logs/app-$(date +%F).log.php`, skipping the
guard line — every other line is JSON — and `grep '"rid":"3f9a1c02"'` to
reconstruct one request.

The reading order, always: the window's `error` and `warn` entries first;
then the positive lines you expected — the handler outcome, the audit entry,
the request summary. Absence of errors is not evidence; the presence of the
expected line is.

## 12. Validating on DEV

Validation happens on remote DEV, never locally — `policies/testing.md`. The
endpoints, all under `__dev/` (append `.php` where `mod_rewrite` is off):

| Endpoint | Token | Returns |
| --- | --- | --- |
| `GET /__dev/probe` | no | `environment`, `health`, `git_commit`, `database`, `db_engine`, `db_driver`, `storage`, `migration_status`, `debug_mode`, `starter_auto_login` |
| `GET /__dev/diagnostics?check=db` | yes | engine, driver and its version, tables with row counts and column names, applied and pending schema files |
| `GET /__dev/diagnostics?check=errors&since=15m` | yes | warn/error entries from the JSONL log in the window, with their `rid` |
| `GET /__dev/diagnostics?check=storage` | yes | `cache/`, `logs/`, `data/` state |
| `GET /__dev/diagnostics?check=config` | yes | the non-secret configuration, `runtime_local_present`, `starter_auto_login` |
| `POST /__dev/migrate` | yes | applies pending `database/*.sql` |

The token goes in the `X-Dev-Token` header. From inside the project, the site
and the token are:

```powershell
$dev   = (Get-Content .agent/project.json -Raw | ConvertFrom-Json).urls.dev   # https://<domain>/atlas/<project>
$token = atlas token
```

### Driving the application over HTTP

A Baustein UI is not a set of URLs. To prove a handler on DEV, do what the
browser does: load the page for a session and the CSRF token, then POST to
`updater.php`.

```powershell
# $dev and $token as above.
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# 1. The page. The starter signs the session in; a real app needs a login step here.
$page = Invoke-WebRequest "$dev/" -WebSession $session
$page.StatusCode                                              # 200
$csrf = [regex]::Match($page.Content, 'name="csrf-token" content="([^"]+)"').Groups[1].Value

# 2. An interaction: what Baustein.js would send for xon:click="AppHandler.bump()"
#    on an element carrying step="1". Attributes become payload keys.
$payload = @{ comp = 'AppHandler'; id = 'x'; func = 'AppHandler.bump()'; step = '1'; csrf = $csrf } | ConvertTo-Json -Compress
$r = Invoke-RestMethod "$dev/updater.php" -Method Post -WebSession $session -Body @{ data = $payload }
$r.status                                                     # ok
$r.actions | ConvertTo-Json -Depth 5                          # the DOM instructions

# 3. A form submit: every named field as one JSON object in `value`.
$value = @{ title = 'From validation'; notes = '' } | ConvertTo-Json -Compress
$payload = @{ comp = 'AppHandler'; id = 'items-add'; func = 'AppHandler.addItem()'; value = $value; csrf = $csrf } | ConvertTo-Json -Compress
Invoke-RestMethod "$dev/updater.php" -Method Post -WebSession $session -Body @{ data = $payload }

# 4. Confirm the data effect — counts, never contents.
Invoke-RestMethod "$dev/__dev/diagnostics?check=db" -Headers @{ 'X-Dev-Token' = $token }

# 5. Read the log for the window: the positive lines, then anything that went wrong.
Invoke-RestMethod "$dev/__dev/diagnostics?check=log&since=10m&ch=app" -Headers @{ 'X-Dev-Token' = $token }
Invoke-RestMethod "$dev/__dev/diagnostics?check=errors&since=10m" -Headers @{ 'X-Dev-Token' = $token }
```

Evidence is the response `status`, the `actions` it returned (the selector and
the HTML for each), the row count before and after, the `app` and `audit`
lines for the window with their `rid`s, and a clean `check=errors` window. A `status: error` carries a `rid`; the matching log
entry is the diagnosis.

Read the `actions` critically: `inner('#items-list', …)` with the expected row
present is proof; a `status: ok` with no actions is a handler that did nothing.

### Capturing a screen

`policies/testing.md` ("Captures") photographs DEV with the headless browser
already on the machine — one URL, one PNG. Three Baustein facts decide what
that URL can show:

- **A screen has an entry URL only if `public/index.php` gives it one.** The
  starter routes on `?page=<view>`, with `$views` as the allowlist; a screen
  reached only through `updater.php` interactions cannot be captured one-shot.
  When a criterion is about a screen, give the screen an entry — a `?page=`
  entry is one line — or capture it with the browser you have and say so.
- **`APP_URL` must be right for the host being captured.** `asset()` builds
  every stylesheet and script URL from it. On DEV the deployment writes it, so
  an unstyled DEV capture means a `runtime.local.php` on that server is
  overriding it, or the FTP account does not land in the domain's `atlas/` folder —
  not that the CSS is missing. Fix that; do not post the picture.
- **The starter signs everyone in as user 1**, so its screens capture without
  a login. Once the application authenticates for real (`starter_auto_login`
  is `false` in the probe), a screen behind the login is captured with the
  browser you have, or reported as *not captured — behind login*.

A state produced by an interaction — a field error, a toast, a filled list —
lives in the session that produced it, and a one-shot capture starts a new
session. Prove such states with the `updater.php` actions and the log lines
above; capture the screen as a user first meets it.

## 13. Keeping the manual true

`LLM.txt` was cross-checked against the code when this template was adopted
and corrected where it had drifted — the local-config file nothing loaded, the
template cache that never needed clearing, the interactions that fail from
`/public/index.php`, `display_errors` being forced on, the suite writing
`cache/` and `logs/`. Those facts now live in the manual (§2, §3, §5, §19b,
§21, §22, §25) and are not repeated here.

What that means for you:

- A change to a framework-facing behaviour of the *application* — a new
  `runtime.php` key, a new endpoint under `__dev/`, a changed contract in
  `boot.inc.php` — is not finished until `LLM.txt` §25 says so.
- A discrepancy you discover between the manual and the code is a defect in
  the manual. Fix it in the same PR. Do not work around it silently.
- The manual documents limits honestly ("what the file engine will not do").
  Keep that standard: a feature that half-works is documented as not working.

## 14. Deployment

- **Document root = project root.** On DEV that is the project's folder on
  the shared account — the domain's `atlas/<project>` — served at
  `https://<domain>/atlas/<project>`; in production
  `PROD_PATH` points at the directory holding `index.php` and `updater.php`.
  `APP_URL` is that URL plus `public/` — on DEV the deployment writes it.
- `cache/`, `logs/`, `data/`, `runtime.local.php` belong to the server. They
  are listed in `.deployignore` and `.deployignore.production`: never uploaded,
  never deleted by a deployment, although the upload mirrors with `--delete`.
  The framework creates the directories on demand. `runtime.dev.php` can never
  be kept back: the DEV deployment writes and uploads it.
- The repository's own material never reaches a server: `.git/`, `.github/`,
  `.agent/`, `.claude/`, `.codex/`, every Markdown file at any depth
  (`AGENTS.md`, `PRODUCT.md` and the rest), `docs/`, `LLM.txt`, `captures/`,
  `tests/`. Both deployments leave it out, fail if it is in what they upload,
  and delete it from a server an earlier upload left it on. Never put it on a
  server by hand, and never keep what a page renders in a `.md` file.
- The root `.htaccess` refuses direct requests to `src/`, `database/`, `tests/`,
  `cache/`, `logs/`, `data/`, `runtime*.php` and `LLM.txt` and, as a second layer, every
  dot-folder but `.well-known/`, every Markdown file, `docs/`, `captures/`,
  `vendor/` and `node_modules/`, and routes `/health` to `health.php`. Keep
  it: a project's own rules go between its `# ---- project rules ----`
  markers, which `atlas sync` keeps when it replaces the rest.
- The production package also leaves out `__dev/`, `runtime.dev.php` and the
  deployment metadata. `deploy-prod.yml` fails if any of it survives, or any
  reference to `__dev`, and the smoke check asserts `/__dev/probe` and
  `/__dev/probe.php` return 404.
- Enable opcache on the server; keep `ENABLE_GZIP` on; `LOG_METRICS` off in
  production unless profiling. The DEV deployment turns it on: the request
  summary is how validation sees a page load.
- `.dev-state.json` at the root is written by the DEV deployment and read by
  the probe. It is git-ignored.
- **Before production:** `DEBUG_MODE` false, `APP_ENV` production, the starter
  sign-in gone (`starter_auto_login: false` on DEV), and `MAIL_TRANSPORT`
  deliberately chosen.

## 15. JavaScript and CSS

- No build step, no bundler, no framework, no npm. `Baustein.js` and `ui.js`
  are the whole client; do not edit them.
- Behaviour is declared on markup and lives on the server: `xon:click="…"` in
  a template, `->onClick(…)` in PHP, or `actions xonclick="…"` on hand-written
  HTML. Do not write `fetch()` calls, do not build a JSON API next to
  `updater.php`, do not keep state in the browser.
- Reach the client's helpers from a handler: `->call('toast', [...])`,
  `->call('Slider.open', ['id'])`, `->call('applyTheme', [...])`. A new global
  JS function in `app.js` is acceptable when it manipulates the DOM in a way
  the actions cannot express — chart libraries, editors — and such elements
  carry `data-preserve` so the patcher leaves them alone.
- `public/css/app.css` is loaded after `Baustein.css`. Redefine tokens
  (`--brand`, `--radius`, …) there; write components in the utility vocabulary
  (`LLM.txt` §17) before writing new CSS. `data-theme="dark"` on `<html>` is
  the whole theming mechanism.
- RTL works throughout because the framework uses logical properties. Keep it
  that way: `ms-*`/`me-*`, `border-s`, `text-start`, never `margin-left`.

## 16. Common failure patterns

| Symptom | Usual cause |
| --- | --- |
| Every click returns 404 | The page was loaded from `/public/index.php`; `updater.php` is resolved relative to it |
| Every click returns 401 | No session user. The starter sets one in `public/index.php`; a real login must `Session::set('user', $id)` |
| Every click returns 403 "This page is out of date" | CSRF meta tag missing from the layout, or the token was rotated after the page loaded |
| "Unknown action." | Handler not public, static, magic, or declared on `Component`/`Handler`; or the class is not a `Component`. Turn on `DEBUG_MODE` on DEV — the refusal then explains itself |
| Page shows `&lt;div&gt;` where a widget belongs | A helper returned a markup string to a template. Return the Component or `raw()` |
| Handler cannot read `$this->record` | The property was not emitted: the template mentions `$record`, or `$expose` omits it, or the value was an array |
| Works once, then a click targets nothing | Random component id changed on re-render. Use a stable id |
| A list re-renders slowly and loses focus | Rows without `key="…"`; the patcher rebuilds instead of moving |
| Fatal on an undefined index | Warnings are exceptions. Guard with `??` |
| Every query on a new table fails | No `deleted_at` column and no `$softDelete = false` |
| Query finds nothing for an id from the request | Column type mismatch on the SQL engine; the file engine compares numerically |
| Assets 404 after a move | `APP_URL` wrong, or missing its trailing slash |
| Session writes vanish | `$_SESSION` written directly after `Session::release()`. Use `Session`/`State` |
| Works locally, 500 on DEV | Case-sensitive filesystem: the file name must equal the class name exactly |
| Old UI after a deploy | The browser cache — `asset()` versions by `filemtime`, which the upload preserved. Not the template cache, which is content-keyed |
| Every request slow | Expensive work in `boot.inc.php`, paid on every click. `Cache::remember()` it |
| `data/` empty after a deploy | The deploy swept it. `data`, `cache`, `logs` must be in `.deployignore` and `.deployignore.production` |
| A click worked but the log has no line for it | The handler does not log its outcome — a review failure — or `LOG_METRICS` is off and you expected the request summary |
| Nothing at all reaches the log | `LOG_ENABLED` false, `LOG_LEVEL` above what you write, a `LOG_CHANNELS` allowlist that omits the channel, or `logs/` not writable — the logger never breaks the request, it just writes nothing. The probe's `log` block names which |
| A `rid` in an error finds no lines | The request ran with logging off, or the buffer overflowed (a `log`-channel warning says so) |

## 17. Prohibited in a Baustein project

- Editing anything under `src/core/`, `updater.php`, `Baustein.js`, `ui.js`,
  `Baustein.css`, `tests/run.php`
- Adding Composer, npm, a bundler, a client framework, a JSON API layer, a
  client router, or client-side state
- A static, private, or inherited method used as a handler
- A handler that returns anything but an `Event`
- A markup string returned to a template; unescaped user text inside `raw()`
- Reading `$_POST`, `$_GET` or `$_SESSION` in application code — `Request`,
  `Session`, `State`
- A model table without `deleted_at` and without `$softDelete = false`
- A secret in `runtime.php`; `DEBUG_MODE` true in production
- The starter's `Session::set('user', 1)` on any server other than DEV
- Linking to `public/index.php`
- Committing `cache/`, `logs/`, `data/`, `runtime.local.php` or a `--update`d
  snapshot whose diff was not read
- A handler, job or write that leaves no line in the log; a secret, a
  password, a session id or a full payload in a context array; `LOG_LEVEL`
  set to `warn`; removing the audit hook
