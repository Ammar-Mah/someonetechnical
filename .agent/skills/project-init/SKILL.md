---
name: project-init
description: Turn a freshly bootstrapped repository into a planned project — read PRODUCT.md, write ARCHITECTURE.md and PLAN.md, and file the roadmap as GitHub Issues. Run once, immediately after atlas new, before any implementation work.
---

# Skill: project-init

Run once, on a new project created by `atlas new`. Turns a product description
into a planned, Issue-tracked project.

Output: `ARCHITECTURE.md`, `PLAN.md`, and a sequenced set of GitHub Issues.

## 1. Verify the bootstrap

```powershell
git rev-parse --abbrev-ref HEAD        # dev
git remote -v
gh repo view --json name,visibility,defaultBranchRef
gh label list
gh api repos/{owner}/{repo}/environments --jq '.environments[].name'
```

Confirm present:

- [ ] `AGENTS.md`, `PRODUCT.md`
- [ ] `.agent/skills/`, `.agent/policies/`, `.agent/framework/RULES.md`
- [ ] `.agent/project.json`
- [ ] `.github/workflows/` — checks, deploy-dev, deploy-prod
- [ ] Branches `main` and `dev`, `main` protected
- [ ] Labels installed
- [ ] Environments `development` and `production`

If anything is missing, report it and stop. Re-run the bootstrap script rather
than hand-patching — a project that starts inconsistent stays inconsistent.

## 2. Read the product

```powershell
Get-Content PRODUCT.md
Get-Content .agent/project.json
Get-Content .agent/framework/RULES.md
```

Understand: what is being built, for whom, what it must do, what it must not do,
and any stated constraint.

If `PRODUCT.md` is thin — a few lines, or missing what the product actually
does — **stop and ask**. Do not invent a product. List the specific questions:

```
PRODUCT.md describes an "IDEALS company website" but does not say:
1. Is there a contact form, and where do submissions go?
2. Is there a blog or news section? Who edits it?
3. Are case studies static or database-driven?
4. Any authentication, or is it entirely public?
5. Is multi-language needed?

I can proceed with reasonable defaults and flag them, or wait for answers.
Answers now will save rework.
```

## 3. Inspect the starter code

```powershell
git ls-files | Select-Object -First 100
```

Read the entry point, the routes, the config, and one example of each layer. You
must know what already exists before planning what to add.

## 4. Write ARCHITECTURE.md

Describe the system as it will be, based on the template plus what the product
needs. Follow `policies/documentation.md`.

```markdown
# Architecture

## Overview
Public marketing site with a contact form and a case-study section. Server-
rendered PHP on shared hosting. No JavaScript framework, no build step.

## Stack
PHP 8.2 · Baustein (IDEALS microframework) · MySQL 8, or the file engine
until there is a real database · vanilla JS · Apache

## Request lifecycle
Page load:   index.php → initialize → functions → boot.inc.php →
             public/index.php → Template::view()
Interaction: Baustein.js → updater.php → four gates → component rebuilt from
             the payload → handler → Event → DOM patch

## Components
### Screens
`src/app/Components/*Screen.php`, one per page region. Region ids are class
constants; handlers re-render regions, never pages.

### Handlers
`src/app/Events/*.php`, grouped by flow. Public instance methods returning
`Event`. Send back only what changed.

### Persistence
Models in `src/app/Models/`, one per table, queries named after the question
they answer. `DB_ENGINE` is chosen per server. Schema changes in `database/`
as `NNNN_name.sql` + `.down.sql`.

### Views
`src/app/Views/*.php` — `@extend('app')`, one `<x:Screen/>`. The layout
carries the CSRF meta tag. Templates escape; components and `raw()` do not.

### Logging
Channels: `app` (handler outcomes), `audit` (every write, through the hook in
`boot.inc.php`), `auth`. JSONL under `logs/`, request id on every line. DEV
runs with metrics on; read through `/__dev/diagnostics?check=log`.

## Map
| Path | Holds |
| --- | --- |
| `src/app/Components/` | screens: `Welcome`, `ItemsScreen`; layout: `SideNav` |
| `src/app/Events/` | `AppHandler` — navigation, theme, items |
| `src/app/Models/` | `Item` |
| `src/app/Views/` | `main` (shell) |
| `database/` | schema pairs, SQL engine only |
| `__dev/` | ATLAS probe, diagnostics, migrator — never in production |

## Planned domains
| Domain | Responsibility |
| --- | --- |
| `Content` | Pages, case studies |
| `Contact` | Form submission and storage |

## Data model
`contacts`, `case_studies`. See docs/DATABASE.md once the schema lands.

## Environments
Local WAMP · DEV https://dev.ideals.example · production https://ideals.example

## Constraints
- Shared cPanel: no long-running processes, no Redis, no supervisor
- Cron granularity 5 minutes
- PHP memory limit 128M
- Case-sensitive filesystem on DEV and production; local Windows is not
```

The constraints section is the highest-value part. Record every limit you know
now, and every one you learn later.

## 5. Write PLAN.md

Phases, each with a deliverable outcome:

```markdown
# Plan

## Phase 1 — Foundation
Runnable skeleton deployed to DEV, probe reporting the right commit.
- Base layout, header, footer, error pages
- Home page
- DEV probe and diagnostics verified end to end
- Logging baseline: every handler logs its outcome, the audit hook is on, the
  probe reports the log's state, and `diagnostics?check=log` reads it

## Phase 2 — Content
- Static pages: about, services, contact
- Case study listing and detail

## Phase 3 — Contact
- Contact form with validation and persistence
- Owner notification email
- Admin submission list

## Phase 4 — Production
- Production deployment proven
- SEO metadata, sitemap, analytics
- Performance and accessibility pass
```

`PLAN.md` holds the shape. GitHub Issues hold live state. Never duplicate Issue
status into `PLAN.md` — it goes stale within a day.

## 6. File the Issues

Use `plan` (Mode B) for the decomposition rules. Each Issue: independently
valuable, independently testable, roughly under 400 lines, with observable
acceptance criteria.

File Phase 1 and Phase 2 in full. File later phases as coarse Issues to be split
when they come close — detailed Issues written now will be wrong by then.

```powershell
gh issue create --title "feat: base layout and error pages" `
  --body-file .git/issue-01.md --label "ready,frontend"
```

Then:

- Record dependencies as comments: `Depends on #1.`
- Label anything touching the schema `schema-change`.
- Label only genuinely unblocked Issues `ready`; the rest `blocked`.

Aim for 8–15 Issues at init. Fewer means they are too big; more means you are
planning too far ahead.

## 7. Start the history

`HISTORY.md` is the record every later session reads first. `atlas new`
created it with the creation entry; add the initialisation:

```markdown
## 2026-09-10 — Initialised (#1–#12 filed)

**Changed** ARCHITECTURE.md and PLAN.md written from PRODUCT.md; 12 Issues
filed, 5 ready. **Why** /project-init. **Now true** The Map describes the
template as shipped; the demo screens still exist and go with the first real
screen. **Evidence** The Issues. **Documents** ARCHITECTURE, PLAN written;
PRODUCT supplied by the human. **By** claude-code, MACHINE-A
```

Newest first, about 120 words, the format in `policies/documentation.md`.

## 8. Commit, and put the documents on `dev` yourself

Like every change: a worktree, a branch, a pull request. This one is
documentation only, so it merges on green checks with no DEV validation
(`policies/git.md`) - and you merge it, rather than leaving a human to.

```powershell
git worktree add -b docs/project-init ..\worktrees\project-init-claude origin/dev
cd ..\worktrees\project-init-claude
```

Write `ARCHITECTURE.md`, `PLAN.md` and the `HISTORY.md` entry there, then:

```powershell
git add ARCHITECTURE.md PLAN.md HISTORY.md
git commit -m "docs: add architecture, plan and history

Derived from PRODUCT.md. Roadmap filed as issues #1-#12.

Agent: claude-code"
git push -u origin docs/project-init
gh pr create --base dev --title "docs: add architecture, plan and history" --body-file .git/PR_BODY.md
gh pr checks --watch
gh pr merge --squash --delete-branch
```

Then clean up and leave the primary checkout on `dev`, current:

```powershell
cd ..\..\repo
git worktree remove ..\worktrees\project-init-claude
git worktree prune
git pull --ff-only origin dev
```

The merge starts a DEV deployment of the unchanged site; let it finish before
handing over, so the next session finds a free DEV lane. Everything after this
goes through the same route.

## 9. Report

```
Project initialised: ideals-website (microframework)

ARCHITECTURE.md  — stack, lifecycle, 2 planned domains, 4 recorded constraints
PLAN.md          — 4 phases
HISTORY.md       — started; the initialisation entry is at the top
Issues           — 12 filed, 5 ready, 7 blocked on dependencies

Ready now:
  #1  base layout and error pages
  #2  home page
  #3  DEV probe verification
  #5  static content pages
  #7  case study schema          [schema-change]

Assumptions made — confirm or correct:
  - Contact submissions store to the database and email the owner
  - Case studies are database-driven, not static files
  - No authentication anywhere in Phase 1-3
  - English only

Next: /daily-check to begin, or correct the assumptions first.

Not yet proven: DEV deployment has never run for this project. #3 exercises it.
Do that before trusting the pipeline.
```

State the assumptions explicitly. They are the most likely source of rework.

## Never

- Invent product requirements not derivable from `PRODUCT.md` without flagging
  them as assumptions
- Write implementation code — this skill produces documentation and Issues only
- File an Issue with an unobservable acceptance criterion
- Plan every phase in fine detail
- Claim the DEV pipeline works before it has ever run
