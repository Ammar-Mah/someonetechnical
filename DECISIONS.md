# Decisions

Append-only. Newest last. See policies/documentation.md.

## 2026-09-15 — Link targets live on `SiteHeader`, not on the sections

**Context**
`ARCHITECTURE.md` planned `HowItWorksSection::ANCHOR` and
`SupportAreasSection::ANCHOR` as the ids the header and footer link to. #6
builds the header and footer before any section exists, and #10–#14 then run
in parallel.

**Decision**
`SiteHeader::HOW_IT_WORKS`, `SiteHeader::WHAT_WE_HELP_WITH` and
`SiteHeader::START_HREF` hold the targets. A section takes its id from its
constant; the footer, the hero and every other link read the same one.

**Alternatives**
- The literals in both header and footer until the sections arrive — one value
  in two places.
- Constants on the section classes — #11 and #12 would each edit the header and
  footer, in parallel.
- Section classes holding only a constant — a shell with nothing behind it.

**Consequences**
No section Issue edits the header or footer to connect its anchor. Renaming an
anchor is one edit.

**Refs** #6

## 2026-09-16 — The session cookie's flags are set in `runtime.php`

**Context**
`policies/security.md` asks for an `HttpOnly`, `Secure`, `SameSite=Lax` session
cookie (#7). The framework starts the session before any file in `src/app/`
runs, and `.htaccess` belongs to ATLAS.

**Decision**
`runtime.php`, the one application file read before `session_start()`, sets
`session.cookie_httponly` and `session.cookie_samesite`, and
`session.cookie_secure` over HTTPS or with an https `APP_URL`. It never turns
Secure off, and does nothing once a session exists.

**Alternatives**
- `php_value` in `.htaccess`: replaced by every `atlas sync`.
- A `.user.ini`: depends on the server's PHP SAPI, is cached for minutes, and
  WAMP ignores it.
- Re-sending the cookie from `public/index.php`: `updater.php` and a
  regenerated id would send it bare.
- A framework change: unneeded while an application file runs first.

**Consequences**
Every entry point sends the same cookie. If the framework ever started the
session before reading `runtime.php`, the flags would stop applying, and
`tests/cases/visitor.php` would fail.

**Refs** #7

## 2026-09-16 — `APP_ENV` decides the request summary in `runtime.php`

**Context**
The framework rules want `LOG_METRICS` on locally and on DEV, and off in
production (#9). DEV's deployment writes `runtime.dev.php` without it, and a
production `runtime.local.php` may leave it out.

**Decision**
`runtime.php` defaults it to `null` and, after the per-server merge, resolves
it to whether `APP_ENV` is `development`. A server file's own value wins.

**Alternatives**
- `deploy-dev.yml` writing it into `runtime.dev.php`: an ATLAS change that
  still leaves local and production to `runtime.php`'s default.
- A plain `true` default: production would log every request unless its file
  remembered to turn it off.

**Consequences**
A server that says it is `production`, or names no known environment, writes
no summary line unless its file asks for one.

**Refs** #9

## 2026-09-17 — The never-deployed guard is best-effort; DEV proves the copy

**Context**
`tests/cases/site.php` scans the application's PHP for a path a deployment
leaves out (#11). Three reviews in a row found a spelling past it, each one
where a document had just said it was caught.

**Decision**
A person chose, on #11: the guard is a best-effort check for the common
spellings, and its comment and the documents list nothing it follows or lets
pass. DEV validation proves the copy is on the page. A spelling the guard
misses is not a blocking finding unless a document claims it is caught.

**Alternatives**
- Extending the scan after every review: each repair moved the edge, not the
  fault.
- Rendering the page from a copy built with both never lists: it catches
  computed names too, but it is a new mechanism, not a repair.

**Consequences**
Nobody extends the scan to satisfy a review. A missed spelling is caught on
DEV, as #11's first one was.

**Refs** #11

## 2026-09-17 — Requests are stored on the SQL engine, on SQLite

**Context**
The intake stores personal data (#15). The file engine has no schema: a table
appears with its first insert, and nothing states its columns, nulls or
indexes. The shared DEV host gives the project no database of its own; DEV's
PHP has `pdo_sqlite`. A person asked for the SQL engine on #15.

**Decision**
`'DB_ENGINE' => 'sql'` in `runtime.php`, `DB_NAME` empty: SQLite in
`data/database.sqlite` locally and on DEV. `intake_requests` comes from the
pair `database/0001_create_intake_requests`, which the DEV deployment applies.

**Alternatives**
- The file engine: nothing to version or review before a table holds data.
- MySQL on DEV: a database and a credential on the shared host.

**Consequences**
Every schema change is a reversible pair that takes the DEV lane alone.
`data/` must stay refused over HTTP wherever SQLite runs. Production picks
SQLite or MySQL in its own settings (#19), from the same files.

**Refs** #41, #15

## 2026-09-18 — The shell's section links are `./#<id>` everywhere

**Context**
`SiteHeader` and `SiteFooter` linked the two sections as bare fragments. They
render on the intake as well from #42, and on the legal pages from #17, where
`#how-it-works` scrolls the current page to nothing.

**Decision**
`SiteHeader::HOME_HREF` is `./`, and both shell links are `HOME_HREF` plus the
fragment, on every page. On `main` the resolved URL differs from the current
one only in its fragment, so the browser jumps within the page rather than
reloading it. `HeroSection`'s link stays a bare fragment: the hero only ever
renders on `main`.

**Alternatives**
- A per-page prefix passed into the shell — two code paths, and a view that
  forgets to pass it is broken in a way nothing notices.
- Absolute URLs from `APP_URL` — they would hard-code the host into the markup
  and differ per environment.

**Consequence**
The two shell cases in `tests/cases/site.php` assert `./#…`, and a bare
fragment in the shell is now the defect.

## 2026-09-18 — An over-long answer is cut to fit, not refused

**Context**
`intake_requests` gives each answer a column size, and MySQL in strict mode
refuses an over-long value that SQLite silently accepts. Anyone with a session
can post to `IntakeHandler`.

**Decision**
Every answer is trimmed and cut to its column's length before it is stored;
the two free-text answers on `TEXT` columns are bounded at 5,000 characters.
A cut leaves a `warn` naming the field and how much was kept, never the value.
Only the contact pair can refuse a request outright.

**Alternatives**
- Refusing anything over the limit — it turns a visitor away over a length
  they were never shown, for answers that are optional to begin with.
- Trusting the column — the same code then works on SQLite and fails on MySQL.

**Consequence**
The row always fits both databases. A visitor who writes more than 5,000
characters about what they are building is not told that the rest was dropped;
if that ever matters, the field gets a counter, not a refusal.
