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
in-memory SQLite database.

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
first. Nothing writes the table yet; the intake does (#42). It holds personal
data: contact details and free-text answers. No log line, the `audit` line
included, may carry them.

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
