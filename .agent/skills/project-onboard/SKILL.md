---
name: project-onboard
description: Adopt an existing codebase into ATLAS — investigate what is actually there, reconstruct PRODUCT.md and ARCHITECTURE.md, record constraints, and file Issues for discovered unfinished work. Run once, after atlas onboard, on a project that already has code.
---

# Skill: project-onboard

Adopts an existing project. The hard part is not writing files — it is finding
out what is really there.

**The existing system is the specification.** You are documenting reality, not
proposing a rewrite.

## 1. Verify the bootstrap

```powershell
git rev-parse --abbrev-ref HEAD
gh repo view --json name,visibility,defaultBranchRef
gh label list
gh issue list --state open --limit 100
```

Confirm the ATLAS files landed: `AGENTS.md`, `.agent/`, `.github/workflows/`,
and `/captures/` in `.gitignore`. Confirm `dev` exists and `main` is protected.

Note anything the script could not do — for example, a repository whose default
branch is `master`, or one with no `dev` branch. Report it; do not silently
rename branches on a live project.

## 2. Investigate — the real work

Spend serious effort here. Everything downstream depends on it.

### Shape and history

```powershell
git log --oneline -30
git log --format="%an" | Sort-Object -Unique
git ls-files | Measure-Object -Line
git ls-files | ForEach-Object { [IO.Path]::GetExtension($_) } | Group-Object | Sort-Object Count -Descending
```

Recent commits tell you what is active. Old dates on core files tell you what is
stable or abandoned.

### Identify the framework

Do not assume the flag passed to the script was right. Verify:

| Evidence | Framework |
| --- | --- |
| `artisan`, `app/Providers/`, `composer.json` requires `laravel/framework` | Laravel |
| `wp-config.php`, `wp-content/`, `wp-load.php` | WordPress |
| `updater.php` + `src/core/inc/initialize.inc.php` + `LLM.txt` | Baustein (IDEALS microframework) |
| None of the above | generic |

If it contradicts `.agent/project.json`, fix the config and re-run
`atlas sync` to install the right framework rules. Say so in your report.

### Answer the orientation questions

From `frameworks/generic/RULES.md`, answer all ten — even for a known
framework, because every real project deviates:

entry point · routing · layering · data access · output and escaping ·
configuration · dependencies · naming conventions · error handling · tests

### Find the constraints

The most valuable thing you will produce. Look for:

```powershell
Get-ChildItem -Recurse -Include *.htaccess,*.ini,*.conf | Select-Object FullName
Get-Content composer.json
Get-Content package.json -ErrorAction SilentlyContinue
```

- PHP version required and PHP version available
- Hosting type — shared, VPS, containerised
- Whether cron, workers, or long-running processes exist
- Hard-coded absolute paths
- Hard-coded URLs or environment assumptions
- Anything the code works around rather than solves

### Find the hazards

```powershell
git grep -n -i -E "password|api[_-]?key|secret|token" -- ":!*.md" | Select-Object -First 40
git grep -n -E "SELECT .*\\\$|WHERE .*\\\$|query\(.*\\\$" | Select-Object -First 40
git grep -n -E "<\?= *\\\$[a-zA-Z_]+ *\?>" | Select-Object -First 40
```

Looking for: committed secrets, string-concatenated SQL, unescaped output,
missing authorisation checks, `eval`, `unserialize` on user input.

**If you find a committed secret: stop.** Do not touch it, do not rewrite
history. Report the file and the kind of secret — never the value. Label
`security` `needs-human` and escalate. The credential must be rotated first.

**If you find a live vulnerability:** create a `security` `high-priority` Issue
describing the affected component and class of problem, not a working exploit.

### Check it runs

```powershell
if (Test-Path composer.json) { composer install }
php -l index.php; php -l public/index.php
if (Test-Path tests/run.php) { php tests/run.php }      # Baustein: the shipped suite
```

Try to run it locally. If it will not run, that is a finding worth an Issue and
worth stating prominently — an agent cannot work a project it cannot execute.

If a DEV or production URL exists, photograph it now: the home page and the
five or so screens a user meets first, desktop and mobile, with the capture
command in `policies/testing.md` ("Captures"). Attach them to the DEV-probe
enablement Issue you file in step 6. They are the *before* of everything ATLAS
will change here, and the first thing a human reads when asked whether the
modernisation broke anything.

## 3. Write or repair PRODUCT.md

Most existing projects have none. Reconstruct it from the code, the UI, the
routes, and the database:

```markdown
# Product

## What it is
RELAY is an internal message-routing tool used by IDEALS support staff to
triage inbound client requests and assign them to engineers.

## Users
Support staff (triage, assign) · engineers (view assigned) · admins (manage
users and routing rules)

## Core capabilities
- Inbound email ingestion into a queue
- Rule-based routing to teams
- Assignment and status tracking
- Daily digest email

## Current state
In production at relay.ideals.example since 2024. Actively used. Roughly 40
routes, 12 tables, no automated tests.

## Reconstructed, not specified
This file was reconstructed by reading the code — no product specification
existed. It describes what the system **does**, which is not necessarily what it
is **meant** to do. A human should review it.
```

That last section is mandatory on a reconstruction. Never present inferred
intent as stated intent.

## 4. Write ARCHITECTURE.md

Describe what **is**, not what should be. Include:

- Overview, stack with actual versions, request lifecycle
- **The Map** — every directory and notable file, one line each: what it
  holds and what it is for. The single most token-saving thing you will
  write: every later session reads it instead of the tree
- Real directory structure with what lives where
- Data model and relationships
- External dependencies and integrations
- Environments and how deployment currently happens
- **Constraints** — everything from step 2
- **Hazards** — fragile areas, load-bearing code, known-bad patterns
- **Conventions in use** — including inconsistent ones, noted as such

```markdown
## Hazards
- `includes/legacy_router.php` is 900 lines, handles 60% of routes, and has no
  tests. Change with care and validate broadly.
- Sessions are file-based; the host rotates the directory nightly. Users are
  logged out at 03:00. Known, not yet fixed — see #14.
- `helpers.php` is included everywhere and defines 40 global functions.
  Renaming any of them breaks unknown call sites.
- Two naming conventions coexist: `snake_case` in `includes/`, `PascalCase` in
  `src/`. Newer code is `src/`. Follow the directory you are in.
```

This is the file that stops the next agent breaking the system.

## 5. Review existing Issues

```powershell
gh issue list --state open --limit 100 --json number,title,body,labels,updatedAt
```

For each: still relevant? Understandable? Has observable acceptance criteria?

- Add ATLAS state labels — `ready` only if genuinely actionable.
- Comment on ones that need clarification; label `needs-human`.
- Do not close anything. Not yours to decide.

## 6. File Issues for discovered work

Be selective. A hundred Issues from an onboarding pass is noise nobody triages.

File, in priority order:

1. **Security findings** — `security`, `high-priority`
2. **Broken things** — anything that does not work now
3. **Blockers to ATLAS working** — no DEV environment, no probe, cannot run
   locally
4. **Explicit `TODO`/`FIXME` that represent real unfinished work** — verify each
   is still relevant before filing
5. **Documented-but-missing behavior**

Do **not** file: refactors nobody asked for, style cleanups, test-coverage
Issues for the whole codebase, or framework upgrades. Those need a human
decision.

Cap it at around 15 Issues. Note the rest in `ARCHITECTURE.md` under Hazards.

### Always file the ATLAS enablement Issues

```
feat: add DEV probe endpoint            — required by validate-dev
chore: configure DEV deployment target  — required by deploy-dev
docs: document local setup              — required for any agent to work here
feat: logging baseline                  — every action logs its outcome; the
                                          probe reports the log; diagnostics
                                          reads it (policies/logging.md)
```

Without these the project cannot be worked autonomously.

## 7. Start the history

`atlas onboard` created `HISTORY.md` with the adoption entry. Add the
onboarding itself, and be honest about what precedes it:

```markdown
## 2026-09-10 — Onboarded (#48–#58 filed)

**Changed** PRODUCT.md reconstructed from the code; ARCHITECTURE.md written
with the Map, 7 constraints and 4 hazards; 11 Issues filed, 2 security.
**Why** /project-onboard. **Now true** The documents describe the system as
found on 2026-09-10. Everything before this entry is recorded only in
`git log`; nothing here reconstructs it. **Evidence** The Issues.
**Documents** PRODUCT (needs human review), ARCHITECTURE, DECISIONS started.
**By** claude-code, MACHINE-A
```

## 8. Commit

```powershell
git add PRODUCT.md ARCHITECTURE.md DECISIONS.md HISTORY.md
git commit -m "docs: onboard project into ATLAS

Reconstructed PRODUCT.md from the codebase. Documented architecture,
constraints and hazards. Filed 11 issues for discovered work.

Agent: claude-code"
git push origin dev
```

## 9. Report honestly

```
Onboarded: RELAY (Laravel 9.52 — confirmed, config said 10; corrected)

Scale       412 files · 38 routes · 12 tables · 0 tests
Activity    Last commit 3 weeks ago. 2 contributors.
Runs locally: yes, after composer install and a .env copy.

Documented
  PRODUCT.md       reconstructed from code — NEEDS HUMAN REVIEW
  ARCHITECTURE.md  stack, lifecycle, data model, 7 constraints, 4 hazards
  DECISIONS.md     started; 2 entries inferred from code comments
  HISTORY.md       started; adoption and onboarding entries — git log before that

Issues filed: 11
  SECURITY (2)
    #48  SQL injection in ReportController::filter  [security, high-priority]
    #49  Password reset tokens do not expire        [security, high-priority]
  BLOCKERS (3)
    #50  No DEV environment configured
    #51  No DEV probe endpoint
    #52  No documented local setup
  BROKEN (2), UNFINISHED (4)

Not filed, recorded as hazards instead:
  - No test suite. Adding one is a project-level decision.
  - legacy_router.php needs restructuring. Needs a human plan.
  - Laravel 9 is EOL. Upgrade needs scheduling.

WARNING — #48 is exploitable in production now. Address before anything else.

Blocked until #50 and #51 land: autonomous work cannot validate anything
without a DEV environment and a probe. /daily-check will not be useful yet.
```

The last paragraph matters. Do not imply the project is ready for autonomous
work when it has no DEV environment.

## Never

- Rewrite working architecture because you would have done it differently
- Refactor during onboarding
- Present reconstructed intent as stated intent
- File a hundred Issues
- Close or reword an existing Issue
- Touch a committed secret yourself — escalate
- Report the project ready for `/daily-check` when validation is impossible
