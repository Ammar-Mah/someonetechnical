# Policy: Database

Centrally managed by ATLAS.

Schema changes are the highest-risk routine operation in the system. They are
the one place where a mistake is not cheaply reversible.

## Versioned schema changes only

- Every schema change is a versioned file, committed with the code that needs
  it, applied in order, and recorded as applied.
- Never change a schema by hand on any server, including DEV, outside that
  mechanism.
- Never edit a schema file that has run on DEV or production. Write a new one.
- Filenames sort chronologically: `NNNN_verb_object` — `0007_add_email_to_contacts`.
- One logical change per file.

The mechanism is the framework's — see `.agent/framework/RULES.md`:

| Framework | Apply | Reverse | Recorded in |
| --- | --- | --- | --- |
| Laravel | `database/migrations/*.php` `up()` | `down()` | `migrations` table, by artisan |
| Baustein | `database/NNNN_name.sql` | `database/NNNN_name.down.sql` | `schema_migrations`, by `/__dev/migrate` on DEV; by the release approver in production |
| Baustein on the file engine | nothing — a table is created by its first insert | nothing | — |
| WordPress | `dbDelta()` on activation, versioned by an option | a versioned reverse step | the option |
| Generic | whatever the project documents in `ARCHITECTURE.md` | required all the same | — |

## Every change is reversible

A schema change must define both directions: a `down()`, a `.down.sql`, a
reverse step. A migrator refuses a file with no reverse.

If a change cannot be reversed — dropping a column with data, for example —
then:

1. Say so explicitly in the file's header comment.
2. Split it into two releases: release one stops writing the column, release two
   drops it. Ship them separately, with a validated DEV run between.

## Expand, migrate, contract

Never break a running application with a schema change. Sequence every risky
change across releases:

| Step | Release | Action |
| --- | --- | --- |
| Expand | 1 | Add the new column/table, nullable, with a default. Deploy. |
| Migrate | 1 or 2 | Backfill data. Write to both old and new. Deploy. |
| Switch | 2 | Read from the new. Deploy. Validate. |
| Contract | 3 | Stop writing the old. Drop it. Deploy. |

This applies to: renaming a column, changing a type, splitting a table, adding a
`NOT NULL` column to a populated table, and adding a unique constraint.

Renaming a column in a single release is prohibited on any project with real
data.

## Prohibited in a schema change

- `DROP DATABASE`
- `TRUNCATE` on a table with production data
- Dropping a column, table, or index in the same release that stops using it
- `ALTER TABLE` that locks a large table without a stated maintenance window
- Any statement whose failure leaves the schema half-applied with no path back
  — remember that MySQL DDL auto-commits, so one file is not one transaction
- Seeding production business data. Seeds are for DEV only.

## Serialised schema integration

Two Issues that touch the schema must never integrate into `dev` at the same
time. Ordering conflicts do not surface as merge conflicts — they surface as a
broken DEV environment nobody can explain.

Label every schema-touching Issue `schema-change`. Before merging one:

1. `gh issue list --label schema-change --label working` — if another is
   in flight past the `needs-review` stage, wait. Comment saying you are
   waiting and on which Issue.
2. Sync the latest `dev` into your branch.
3. Confirm no other schema file was added since you branched. If one was,
   renumber yours to sort after it and re-test.
4. Merge. Deploy. Validate the change on DEV before releasing the `dev` lane
   to the next schema change.

## Validating a schema change on DEV

`migration_status` in `/__dev/probe` must read `current` after deployment
where the framework has a migrator; `not-applicable` where it does not, in
which case `/__dev/diagnostics?check=db` is the evidence. In addition:

1. Confirm the new structure exists — column, type, nullability, default, index.
2. Confirm existing rows survived. Compare row counts before and after.
3. Exercise the application paths that read and write the changed structure.
4. Exercise the paths that were **not** supposed to change, to catch an
   accidentally broken query.
5. If a backfill ran, spot-check the backfilled values, including the edge rows
   (oldest, newest, null-adjacent).

Record all of it in the Issue.

## Production database changes

Production schema changes require a human decision, always. Even under an
auto-promotion policy, an Issue labelled `schema-change` requires explicit
approval.

Before a production change:

- [ ] The identical file ran successfully on DEV and was validated.
- [ ] A backup exists and its restore path is known.
- [ ] The reverse path is documented and, where possible, rehearsed on DEV.
- [ ] The estimated lock duration is acceptable for the table size.
- [ ] The release notes say what changes and what the rollback is.

Never apply a production schema change outside the release: through the
deployment workflow where the framework has a migrator, or by the release
approver from the versioned files where it does not. Never through anything
under `__dev/`, which does not exist in production, and never by an agent.

## Queries

- Parameterised only. See `policies/security.md`.
- Index the columns you filter, join, and sort on. State the index in the
  schema file, not as an afterthought.
- No `SELECT *` in application code — name the columns, so a schema change
  cannot silently change a result shape.
- No query inside a loop. Fetch the set, then iterate.
- Any query that can return an unbounded number of rows must paginate.

## Data in DEV

DEV must not hold real customer data. If a project needs realistic data, use
generated or anonymised fixtures. Copying a production database to DEV requires
explicit human approval and an anonymisation pass — never do it autonomously.
