# Database

Baustein's SQL engine (`'DB_ENGINE' => 'sql'` in `runtime.php`). The schema
comes only from the versioned pairs in `database/`; nothing is changed by hand
on any server.

## Where it lives

| | Database | Set by |
| --- | --- | --- |
| Local | SQLite, `data/database.sqlite` | `runtime.php` |
| DEV | SQLite, `data/database.sqlite` | `runtime.php`; no `runtime.local.php` |
| Production | SQLite in `DB_PATH`, or MySQL where its `runtime.local.php` names `DB_NAME` | chosen in #19 |

`data/` is git-ignored, never uploaded or deleted by a deployment, and refused
over HTTP by the root `.htaccess` and by the one the engine writes into it.
`tests/` never touches it: `tests/cases/database.php` applies the pairs to an
in-memory SQLite database, and `tests/cases/intake.php` writes through the
runner's own scratch engine.

### A checkout applies them itself

The migrator is the only thing that applies a pair, on a checkout as on a
server. It wants a token, so give the checkout one in `runtime.local.php` —
git-ignored:

```powershell
'<?php return [''DEV_PROBE_TOKEN'' => ''local''];' | Set-Content runtime.local.php -Encoding ascii
php --% -r "$_SERVER['REQUEST_METHOD']='POST'; $_SERVER['HTTP_X_DEV_TOKEN']='local'; $_GET['action']='up'; include '__dev/migrate.php';"
```

It prints `"status": "current"` and what it applied. `--%` stops PowerShell
reading `$_SERVER` as its own variable. This is what CI's *Migrations declare
a reverse* step does, in a copy, on SQLite and on MySQL.

## Tables

### `intake_requests`

One visitor's request from the intake. Created by
`0001_create_intake_requests`.

| Column | Type | Holds |
| --- | --- | --- |
| `id` | `INTEGER PRIMARY KEY AUTO_INCREMENT` | |
| `building` | `TEXT NULL` | What are you building? |
| `ai_tool` | `VARCHAR(100) NULL` | Which AI building tool are you using? |
| `stuck_on` | `TEXT NULL` | What are you currently stuck on? |
| `is_live` | `VARCHAR(20) NULL` | Is the project already live? |
| `help_wanted` | `VARCHAR(20) NULL` | Guidance, hands-on help, or unsure |
| `contact_name` | `VARCHAR(200) NOT NULL` | Contact details |
| `contact_email` | `VARCHAR(254) NOT NULL` | Contact details |
| `preferred_time` | `VARCHAR(200) NULL` | Preferred session time, in the visitor's words |
| `created_at`, `updated_at` | `DATETIME NOT NULL` | Stamped by the model, not the database |
| `deleted_at` | `DATETIME NULL` | Soft delete |

Index `intake_requests_created_at` on `created_at`: requests are read newest
first. `IntakeHandler` is the only thing that writes it, through
`IntakeRequest::add()`, which stamps `created_at` and `updated_at`.

It holds personal data: contact details and free-text answers. No log line may
carry them, and none does — the `audit` hook in `src/app/boot.inc.php` reduces
this table's writes to the NAMES of the columns that changed:

```
{"ch":"audit","msg":"Insert IntakeRequest","ctx":{"id":7,
 "old":null,"new":["building","ai_tool","stuck_on","is_live","help_wanted",
 "contact_name","contact_email","preferred_time","created_at","updated_at"]}}
```

A second table holding personal data is added to that filter at the same time
as its migration; the hook is otherwise unchanged for every other table.

## Changing the schema

- A change is a new pair, `NNNN_verb_object.sql` and its `.down.sql`, written
  once for SQLite and MySQL in the subset `.agent/framework/RULES.md` §8
  gives. A file that has run on DEV is never edited.
- The checks run every pair up, down and up on both databases. An Issue that
  adds one is labelled `schema-change` and takes the DEV lane alone.
- **DEV:** the deployment applies new pairs through `POST /__dev/migrate`,
  which records them in `schema_migrations`, and fails unless the probe then
  reports `migration_status: current`.
- **Production:** the release a person approved applies the same files.
  `deploy-prod.yml` uploads the project's migrator alone, under a random name
  with a one-time token, applies what is pending and removes it. The release
  fails if the files did not apply or that address still answers. `__dev/`
  itself never ships.
- **Reversing:** a rollback redeploys code and leaves the schema as it is.
  `0001_create_intake_requests.down.sql` drops the table and every request in
  it. Back the table up before running it anywhere that holds requests.
