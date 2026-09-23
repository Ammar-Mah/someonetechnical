# someonetechnical

Built on **Baustein**, the IDEALS microframework, managed by ATLAS.

Baustein is a server-driven PHP micro-framework: all UI logic lives on the
server, the browser is a thin terminal, and every interaction is a POST to
`updater.php` that returns a list of DOM instructions. No build step, no npm,
no Composer, no JSON API layer.

**The manual is [`LLM.txt`](LLM.txt)** at the root of this project. It is the
only complete reference and it was written to be read by an agent. Read its
§1–3 before touching anything; `.agent/framework/RULES.md` tells you how
ATLAS expects you to work within it.

## Local setup

There is nothing to install. Serve the project root with PHP 8.2 or later:

```
http://localhost/someonetechnical/repo/          under WAMP
```

or, without Apache:

```bash
php -S localhost:8000
```

`runtime.php` is the configuration. Its `APP_URL` must be the absolute URL of
`public/` **with a trailing slash** — the default matches the WAMP path above.
For anything that differs on your machine (the database, say) create
`runtime.local.php` from `runtime.local.example.php`; it is git-ignored and its
keys replace the defaults.

```bash
php tests/run.php              # the suite — no dependencies, no database
php tests/run.php template     # only cases whose filename matches
php tests/run.php --update     # accept changed snapshots (read the diff first)
```

Running the tests writes to `cache/` and `logs/` in the project — both are
git-ignored — and keeps its scratch tables in the system temp directory.

The render snapshot writes `APP_URL` as `{APP_URL}` (the `Logo` links to it),
so the suite passes in any checkout or worktree, whatever its `APP_URL`.

## First things to do in a new project

1. **Set `APP_NAME`** in `runtime.php`, then `php tests/run.php --update` and
   confirm the only snapshot lines that changed are the Logo's.
2. **Choose the storage engine.** This site sets `DB_ENGINE` to `sql` in
   `runtime.php`: SQLite in `data/database.sqlite`, with the schema from the
   `database/` pairs, unless a server names a MySQL database in its own
   `runtime.local.php`. See `docs/DATABASE.md`.

## Layout

```
index.php            page entry — boots, then includes public/index.php
updater.php          every interaction. Framework file: do not edit
runtime.php          configuration; every key becomes a constant
runtime.local.php    this server's overrides — created by hand, never committed
LLM.txt              the manual
__dev/               ATLAS DEV probe, diagnostics, migrator — never in production
database/            schema changes as NNNN_name.sql + NNNN_name.down.sql (SQL engine)
cache/ logs/ data/   written by the application; never deployed, never swept
public/              index.php (routing), css/, js/, fonts/, img/
src/core/            THE FRAMEWORK — read-only
src/app/             YOUR APPLICATION — Components, Events, Models, Views, Translations
tests/               php tests/run.php; cases in tests/cases, snapshot in tests/snapshots
```

## The ATLAS endpoints

```
GET  /__dev/probe                    health and the deployed commit. No token.
GET  /__dev/diagnostics?check=…      db | errors | storage | php | config. Token.
POST /__dev/migrate                  apply pending database/*.sql. Token. SQL engine only.
```

The token is `DEV_PROBE_TOKEN`, sent as `X-Dev-Token`. The DEV deployment
writes it; `atlas token` prints it.
Where `mod_rewrite` is off, append `.php` to each path. The whole `__dev/`
directory is excluded from the production package, and the release fails if
it survives.

## Deployment

The **document root is the project root** — `index.php` and `updater.php` live
there, and `Baustein.js` resolves `updater.php` relative to the page. Never link
to `public/index.php` directly: a page served from `/public/` posts to
`/public/updater.php`, which does not exist, and every interaction fails.

The root `.htaccess` refuses direct requests to `src/`, `database/`, `tests/`,
`cache/`, `logs/`, `data/`, `runtime*.php` and `LLM.txt`. `cache/`, `logs/` and `data/`
are listed in `.deployignore`, so a deployment never overwrites or sweeps them.
It also routes `/health` to `health.php`, which answers `{"status":"ok"}` —
rewritten rather than redirected, because the production deployment's check
does not follow redirects.

The repository's own material never reaches a server: `.git/`, `.github/`,
`.agent/`, `.claude/`, `.codex/`, every Markdown file,
`docs/`, `LLM.txt`, `captures/` and `tests/`. Both deployments leave it out,
fail if it is there, and remove it from the server if an earlier upload left
it. As a second layer, `.htaccess` refuses every dot-folder but `.well-known/`,
every Markdown file, `docs/`, `captures/`, `vendor/` and `node_modules/`.

On DEV nothing is done by hand. The site deploys to `atlas/<project>` on the
shared DEV account, and the deployment writes `runtime.dev.php` with
`APP_ENV`, `APP_URL`, `DEBUG_MODE`, `LOG_METRICS` and `DEV_PROBE_TOKEN`, and applies any new
`database/` pair. The SQL engine needs nothing more there: it runs on SQLite.
A DEV site that needs more gets a `runtime.local.php` on the server, which
wins over it and which deployments never touch.

On production, `runtime.local.php` is the whole of it: `APP_URL`, `APP_ENV`,
`DEBUG_MODE` and the database, created on that server once.

Production: `DEBUG_MODE` must be `false`, `APP_ENV` must be `production`, and
the starter sign-in must be gone. Enable opcache; the framework is
include-heavy and its compiled templates are files opcache can hold.

## Rules

`AGENTS.md` first, then `.agent/framework/RULES.md`, then `LLM.txt`. The
shortest version:

- Never edit `src/core/`, `updater.php`, `Baustein.js` or `Baustein.css` —
  extend or override from `src/app/`.
- One class per file, filename identical to the class, global namespace.
- A handler is a **public, non-static** method on **your** class, returns
  `Event::make()->…->send()`, and sends back only what changed.
- `{{ }}` escapes. Return a Component or `raw()`, never a markup string.
  `e()` inside `raw()`.
- Every model table has `deleted_at`, or `$softDelete = false`. Declare
  `$fillable`. Wrap multiple writes in `Model::transaction()`.
- Authorise record access inside handlers — the framework only authenticates.
- Warnings are fatal. Guard every array index and file read.
- Validation happens on remote DEV, never locally.
