# Policy: Documentation

Centrally managed by ATLAS.

Documentation is the project's memory — and it is the agent's memory too. A
model reads code expensively and forgets it when the session ends; the next
session pays again, in tokens and in time, to rediscover the same facts. The
documents exist so that it does not have to: they are the bounded, current
summary that answers most questions without opening a source file.

That only works if they are true. A stale document costs more than none,
because it is believed. So every change updates the documents, on every level
it touches, in the same pull request — and the history of changes lives in
one place, so the descriptions can stay clean.

## Two kinds of document

| Kind | Files | Tense | Discipline |
| --- | --- | --- | --- |
| **Description** | `PRODUCT.md`, `ARCHITECTURE.md`, `PLAN.md`, `docs/*` | Present | **Rewritten** whenever a change alters what they describe. They contain no history. |
| **Record** | `HISTORY.md`, `DECISIONS.md` | Past | **Appended**, newest first. Never rewritten, except to correct a fact. |

A description document with history in it is a defect. A record document with
a rewritten past is a defect.

## Read the documents before the code

The order, at the start of every piece of work:

1. `AGENTS.md` — the rules.
2. `PRODUCT.md` — what this is and for whom. Short.
3. `ARCHITECTURE.md` — how it is built, and its **Map**: which directory and
   which file holds what.
4. `HISTORY.md` — the newest entries: what changed recently, where, and what
   is now true. This is what happened while you were away.
5. `DECISIONS.md` — scan the headings; read only the entries that touch the
   Issue.
6. The Issue and every comment on it.
7. Only then the files the Map and the Issue point at.

Never read `src/` end to end. If the documents cannot locate what you need,
that is a documentation defect: fix the Map as part of your change rather
than reading around it, so the next agent does not pay again.

Together, the description documents and the newest ten history entries should
be readable in one short pass — see *Sizes*. That pass is the whole point: it
is cheaper than the code, and it is complete enough that most Issues can be
planned from it alone.

## Every change updates the documents

In the same pull request as the change:

| Document | When | How |
| --- | --- | --- |
| `HISTORY.md` | **Always.** Every change that reaches `dev`. | Append one entry at the top. |
| `ARCHITECTURE.md` | The structure, a component, the data model, a dependency, an environment, the Map, a constraint, or a hazard changed. | Rewrite the affected sections. |
| `PRODUCT.md` | The product's scope, intent, users, or a core capability changed. Rare, and proposed in an Issue first. | Rewrite the affected sections. |
| `PLAN.md` | A milestone completed, was added, or moved. | Rewrite the roadmap. Issues hold live state; the plan holds shape. |
| `docs/DATABASE.md`, `docs/API.md`, `docs/DEPLOYMENT.md` | The schema, a public contract, or the deployment changed. | Rewrite the affected sections. |
| `DECISIONS.md` | A non-obvious choice was made — something a future agent would otherwise redo. | Append. |

An entry is as long as the change is big: one line for a small change, one
entry for a standard or heavy one (`policies/proportion.md`). A bug fix with no
structural consequence still gets a `HISTORY.md` entry and changes nothing
else. A new screen changes `HISTORY.md`, the Map, and probably
`PLAN.md`. A new table changes those and `docs/DATABASE.md`.

Review checks this (check 12). A change whose history entry is missing, or
whose description documents no longer describe the present, fails.

## Rewriting, not appending

A description document reads as if it were written today, by someone who
knows the system as it is now. Integrating a change means editing the
sentences that are no longer true and the sections that are now incomplete —
not adding a paragraph that says what changed.

Wrong:

```markdown
### Persistence
Models in `src/app/Models/`, one per table. `DB_ENGINE` is chosen per server.

**Update 2026-09-10:** we now also have a `Contact` model and the SQL engine
is used on DEV.
```

Right:

```markdown
### Persistence
Models in `src/app/Models/` — `Item`, `Contact` — one per table, queries
named after the question they answer. `DB_ENGINE` is `sql`: SQLite on DEV
and locally, MySQL in production.
```

The "update" belongs in `HISTORY.md`. The description just becomes correct.

When a change alters the *shape* of what a document describes — a new layer,
a replaced framework, a split of one domain into two — rewrite the whole
document, not its sections. A document patched section by section through
three re-shapings reads like three authors disagreeing.

Delete every sentence that is no longer true. Silence about something is
better than a wrong statement about it.

## HISTORY.md

Append-only, **newest first**, one entry per change that reached `dev`.
Written in the pull request that makes the change, so review sees it and so
it is exactly as accurate as the change.

```markdown
# History

Newest first. One entry per change that reached `dev`. Entries are never
edited except to correct a fact. See `.agent/policies/documentation.md`.

## 2026-09-10 — Contact form (#31, PR #52, `a1b2c3d`)

**Changed** `ContactScreen`, `ContactHandler` and the `Contact` model; the
`contact` view and its `$views` entry; schema pair `database/0003_create_contacts`.
**Why** Milestone 3. Reuses the kit's `Form`; no new component type
(DECISIONS 2026-09-10).
**Now true** Submissions persist to `contacts`; `schema-change` applied on
DEV; the owner notification is #33, not this.
**Evidence** Validated on DEV at `a1b2c3d` — Issue #31, rids `3f9a1c02`,
`7b2e0c11`.
**Documents** ARCHITECTURE (Map, data model) and PLAN rewritten; PRODUCT
unchanged; DATABASE.md rewritten.
**By** claude-code, MACHINE-A

## 2026-09-09 — Session storage moved to the database (#47, PR #50, `9f8e7d6`)
…

## Earlier

- **2026-08** — 14 changes. Project created from the Baustein template;
  layout, home page and DEV probe verified (#1–#3); static pages (#5);
  case-study schema and listing (#7, #9); …
```

The fields, always in this order, always one line where it fits:

- **Changed** — what, at the level of components, handlers, models, tables,
  files. Not the diff.
- **Why** — the reason in one sentence, with the decision reference if any.
- **Now true** — the facts a future agent needs that were not true before,
  including what this change deliberately does *not* cover.
- **Evidence** — where the proof is: the Issue, the commit, the request ids,
  the captures.
- **Documents** — which description documents were rewritten, and which were
  correctly left alone.
- **By** — the agent and machine.

An entry is at most about 120 words. It records; it does not narrate.

Releases are recorded too, by `promote-production`, in a documentation-only
pull request: the version, the Issues it contained, the production commit,
the rollback target.

### Compaction

The file must stay cheap to read. When more than thirty entries stand above
the `## Earlier` heading, fold the oldest into it: one bullet per month, a
sentence or two, the Issue numbers kept. The details survive in the Issues,
the pull requests and `git log`; the history file is the summary, not the
archive.

### What does not go in

Progress narration, session logs, agent conversation, restated diffs,
anything the Issue thread already holds verbatim. `HISTORY.md` is read at the
start of every session by every agent; every word in it is paid for every time.

## The description documents

### PRODUCT.md

Owned by the human. What it is, who it is for, what it must do, what it must
not do, its constraints. An agent may create it during `project-init` from the
supplied description, reconstruct it during `project-onboard` for a project
that never had one, or correct a factual error. An agent must not expand the
product's scope in it; that is proposed in an Issue.

### ARCHITECTURE.md

The system as it is now — not its history, not its roadmap.

```markdown
# Architecture

## Overview
One paragraph: what runs, where, and how a request flows through it.

## Stack
PHP 8.2, Baustein (IDEALS microframework), SQLite or MySQL 8, or the file
engine, vanilla JS. No build step.

## Request lifecycle
Page:        index.php → boot → public/index.php → Template::view()
Interaction: Baustein.js → updater.php → handler → Event → DOM patch

## Map
| Path | Holds |
| --- | --- |
| `src/app/Components/` | screens: `ItemsScreen`, `ContactScreen`; layout: `SideNav` |
| `src/app/Events/` | `AppHandler` — navigation, theme, items; `ContactHandler` |
| `src/app/Models/` | `Item`, `Contact` |
| `src/app/Views/` | `main` (shell), `contact` |
| `database/` | schema pairs 0001–0003, SQL engine only |
| `__dev/` | ATLAS probe, diagnostics, migrator — never in production |

## Components
### Screens
...
### Persistence
...

## Data model
Summary and a link to docs/DATABASE.md.

## Environments
Local WAMP · DEV (exceedlimits.site/atlas/landingpage) · production (example.com)

## Logging
Channels: app (handler outcomes), audit (writes), auth. One JSONL file per
day under logs/, request id on every line. Read on DEV through
/__dev/diagnostics?check=log; in production through the host.

## Constraints
- Shared hosting: no long-running processes, no supervisor, no Redis
- PHP memory limit 128M, not adjustable
- Cron granularity is 5 minutes

## Hazards
- `src/app/Events/AppHandler.php` is 400 lines and every screen routes
  through it. Change with care; validate every screen.
```

The **Map** is the single most token-saving thing in the project: one line
per directory and notable file, saying what it holds and what it is for. It
is not an inventory of every file. It is kept current by every change that
adds, moves, or removes something.

The **Constraints** and **Hazards** sections are the most valuable prose in
the file. Record every limit discovered the hard way and every place the code
is fragile or load-bearing.

### PLAN.md

Phases, each with a deliverable outcome. The shape of the work, not its
status — GitHub Issues hold the live state, and duplicating it here goes
stale within a day. Rewritten when a milestone completes or the roadmap moves.

### docs/

`DATABASE.md` (schema, relationships, indexes), `API.md` (public endpoints,
contracts, auth), `DEPLOYMENT.md` (hosts, paths, quirks) — when the project
needs them. Present tense, rewritten like the rest.

## DECISIONS.md

Append. Never rewrite history — a superseded decision is replaced by a new
entry that references it.

```markdown
## 2026-09-10 — Sessions stored in the database, not files

**Context**
Shared cPanel hosting rotates the filesystem session directory nightly, logging
users out at 03:00. Observed on DEV over four consecutive nights.

**Decision**
Store sessions in the `sessions` table using the framework's database handler.

**Alternatives**
- Custom session path — the host still rotates it.
- Redis — not available on this hosting tier.
- Longer cookie lifetime — does not address server-side expiry.

**Consequences**
One extra query per request. Sessions survive deploys. `sessions` needs a
cleanup job; added as #61.

**Refs** #48
```

Context, decision, alternatives, consequences, reference. Short.

## Sizes

These are budgets, not targets. They exist so the documents stay cheaper to
read than the code.

| Document | Budget |
| --- | --- |
| `PRODUCT.md` | 500 words |
| `ARCHITECTURE.md`, Map included | 1,500 words |
| `PLAN.md` | 600 words |
| A `HISTORY.md` entry | 120 words; thirty entries above `## Earlier` |
| A `DECISIONS.md` entry | 150 words |
| All of the above together, newest ten history entries included | one short read — about 5,000 words |

A document over budget is a document that has started to narrate. Cut it back
to what a reader needs.

## Style

- Short sentences. Present tense. Active voice.
- Concrete over abstract: name the file, the table, the endpoint.
- Tables and lists over paragraphs where the content is structured.
- No marketing language, no filler, no "it is important to note that".
- Code identifiers in backticks. File paths as written on disk.
- Never document a thing that does not exist yet as if it does.

## Keeping documentation honest

Wrong documentation is worse than none — it is confidently misleading, and an
agent will act on it.

If you find documentation that contradicts the code:

1. Determine which is right. The code is usually right about *what*; the docs
   may still be right about *why*.
2. Fix the document as part of your current change — rewrite the sentence, do
   not annotate it.
3. Record the correction in `HISTORY.md` if it changes what a reader would
   have believed.
4. Never leave a contradiction you have noticed unrecorded.
