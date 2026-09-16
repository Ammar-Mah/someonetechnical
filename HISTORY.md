# History

Newest first. One entry per change that reached `dev`. Entries are never edited except to correct a fact. See .agent/policies/documentation.md.

## 2026-09-16 — Visitors get an anonymous session with a flagged cookie (#7)

**Changed** `public/index.php` no longer signs everyone in as user 1. A session
without a user gets a random `visitor:` identity, and at that moment the
session id is regenerated, the CSRF token rotated and `visitor session started`
logged on `auth`. `runtime.php` makes the session cookie `HttpOnly`,
`SameSite=Lax` and, over HTTPS or with an https `APP_URL`, `Secure`. `User()`
names the visitor; `tests/cases/visitor.php` covers it. **Why** #7: the starter
blocked every release, and the DEV cookie lacked the policy's flags. **Now
true** The probe's `starter_auto_login` is false. **Evidence** Issue #7.
**Documents** ARCHITECTURE (Overview, Request lifecycle, Visitor session, Map,
Logging, Constraints, Hazards); DECISIONS (the cookie flags); PRODUCT, PLAN
unchanged. **By** claude-code, ITNEUE-154F1007

## 2026-09-16 — The never-deployed guard reads literals as PHP does (#11)

**Changed** `tests/cases/site.php`: the guard undoes PHP's string escapes,
reads each `{{ }}` and `{% %}` block as text too, and carries production's
never list besides DEV's, bar `runtime.dev.php` and `__dev/` (the checks
refuse that name); a new case holds the copy to both workflows; a bare name
counts, as its comment and message now say. `ARCHITECTURE.md` *Sections*,
*Constraints*; `RecognitionSection`'s docblock; the #11 entry below
corrected. **Why** #11's review failed repair attempt 2: a `\'` literal in a
component template's block passed. A Decision chose this repair. **Now
true** The review's spellings are refused; *Constraints* names what passes.
**Evidence** Issue #11, repair attempt 3 of 3. **Documents** ARCHITECTURE as
above; PRODUCT, PLAN, DECISIONS unchanged. **By** claude-code,
ITNEUE-154F1007

## 2026-09-16 — ATLAS rules and framework files refreshed (34f23bf)

**Changed** Synced from ATLAS 34f23bf. For the first time `atlas sync`
replaced the paths the framework rules mark Framework or ATLAS - `src/core/`,
`__dev/`, `tests/run.php`, `LLM.txt`, `.htaccess` and Baustein's own scripts
and stylesheet; nothing here had changed them since the template. They bring
`SqliteDatabase`, so the SQL engine runs on SQLite in `DB_PATH` when no MySQL
database is named; `__dev/migrate`, the probe (`db_driver`) and diagnostics
on both databases; `FileStorage` guarding `data/` with its `.htaccess` even
when something else made the directory; and a test runner that ignores
carriage returns in rendered markup. `php-checks` runs every `database/*.sql`
pair up, down and up on SQLite and MySQL. Rules: the repair limit counts
repairs, and the build is not one; a person answers a `needs-human` Issue with
a comment beginning `Decision:`, which the survey acts on. **Why** ATLAS
fb6165e and 34f23bf. **Now true** The site still runs `DB_ENGINE=file`, so
nothing it stores moved. `runtime.php` is project-owned and its comment still
calls `sql` MySQL only. The suite passes on a CRLF checkout with no LF
conversion. **Evidence** `atlas sync` output; 128 tests passed in a fresh
worktree with 130 CRLF files; this pull request's checks and the DEV
deployment it starts. **Documents** Only `HISTORY.md` and the synced files
changed. **By** claude-code, ITNEUE-154F1007

## 2026-09-16 — The never-deployed guard reads paths as code builds them (#11)

**Changed** `tests/cases/site.php`: the guard joins strings across `.`, reads
interpolated strings, heredocs and template blocks, matches anywhere in a
path, carries DEV's whole never list, and reads `index.php`, `public/index.php`
and `runtime.php` besides `src/app/`; three new cases hold it. `ARCHITECTURE.md`
*Sections*, *Constraints* and Map; `RecognitionSection`'s docblock. **Why**
#11's review failed repair attempt 1: four spellings of the planted defect
passed the guard, and the documents over-claimed it and render time. **Now
true** *Constraints* says what the guard reads, and that only a name existing
at run time passes it - wrongly: a `\'` literal inside a component template's
block passed too (review, 09:16). **Evidence** Issue #11, repair attempt 2 of 3.
**Documents** ARCHITECTURE as above; PRODUCT, PLAN, DECISIONS unchanged.
**By** claude-code, ITNEUE-154F1007

## 2026-09-16 — ATLAS rules refreshed (95c2b08)

**Changed** Rules synced from ATLAS 95c2b08: `build` attaches the DEV before
captures to the Issue before merging; `daily-check` sweeps every `blocked`
Issue in the survey and re-reads `AGENTS.md` and its skill after a sync;
`review` and `.agent/policies/review.md` say being asked to review one's own
change in the same session does not make that context independent.
**Why** Phase 5 of the ATLAS test plan lost #11's before captures to the
merge, left Issues `blocked` behind a `validated` dependency, and had #11
reviewed by the session that wrote it. **Now true** Past a typo or copy edit,
a subagent reviews and its verdict is posted. **Evidence** `atlas sync`
output; this pull request. **Documents** Only `HISTORY.md` and the synced
files changed. **By** claude-code, ITNEUE-154F1007

## 2026-09-16 — Recognition situations move into the component (#11)

**Changed** `RecognitionSection` holds the six `PRODUCT.md` §2 situations
itself; `docs/copy/recognition-situations.txt` and the render-time file read
are gone, and `tests/cases/site.php` asserts all six strings reach the page.
**Why** #11's DEV validation failed acceptance criterion one: `docs/` is never
deployed, so on DEV the situations were absent and `mount()` logged
`recognition situations unavailable` (rid `216e72bb`, 2026-09-16 07:07:22Z).
That fault was planted for a Phase 5 drill of the ATLAS test plan; this entry
records its repair. **Now true** Section copy lives in its component, as
ARCHITECTURE -> *Sections* has always said, and the `site` channel has no
fallback left to record. The *Constraints* exception naming
`RecognitionSection` is withdrawn. **Evidence** Issue #11: the failing DEV
validation and the re-validation after this change. **Documents** ARCHITECTURE
*Sections*, *Constraints*, Map and Logging rewritten; PRODUCT, PLAN, DECISIONS
unchanged. **By** claude-code, WAMP workstation
## 2026-09-16 — Recognition and how-it-works sections (#11)

**Changed** `RecognitionSection` and `HowItWorksSection` render inside `main`'s
`<main id="main">`, which was empty; `HowItWorksSection` carries
`SiteHeader::HOW_IT_WORKS` as its id, so the header and footer links now reach
a target. `app.css` gains a block for each between `SiteHeader`'s and
`SiteFooter`'s; `tests/cases/site.php` gains five cases. **Why** #11, worked as
a Phase 5 drill of the ATLAS test plan. **Now true** Two of the nine sections
exist. `RecognitionSection` reads its six situations at render time from
`docs/copy/recognition-situations.txt`, which the deployment never uploads — a
**deliberate fault**: on DEV the section renders without them and logs
`recognition situations unavailable` at `warn` on the new `site` channel. The
checks cannot see it; DEV validation is expected to fail acceptance criterion
one. **Evidence** Issue #11: the plan comment, the DEV validation and the
captures. **Documents** ARCHITECTURE rewritten (Overview, Page shell, new
*Sections*, Planned structure, Map, Logging, Constraints); PRODUCT, PLAN,
DECISIONS unchanged. **By** claude-code, WAMP workstation
## 2026-09-16 — ATLAS rules refreshed (fcc3cfc)

**Changed** The agent rules were synced from ATLAS fcc3cfc: `daily-check` now records each sync in `HISTORY.md`, and an Issue labelled `blocked` whose every `Depends on #N` is `validated` or `done` is relabelled `ready` during the survey rather than left for a person. `.agent/project.json` records the new commit. **Why** A sync was the one change that reached `dev` and left no history entry, and cleared blockers sat unavailable until someone noticed. **Now true** #11 is `ready` — its only dependency, #6, is `validated`. **Evidence** `atlas sync` output; this pull request. **Documents** Only `HISTORY.md` and the synced files changed; no description document altered. **By** claude-code, WAMP workstation
## 2026-09-16 - ATLAS rules refreshed (8648bf2)

**Changed** The agent rules and workflows were synced from ATLAS 8648bf2:
`review` accepts a subagent as a fresh context and `daily-check` starts one, so
one prompt carries an Issue from claim to `validated`; the builder merges its
own pull request into `dev`; `project-init` merges its own documents pull
request; `daily-check` syncs when the rules are behind; screenshots go through
`atlas capture`, which lays a page out at the width it claims; `.gitattributes`
keeps `tests/snapshots/*` LF so a Windows checkout stops failing the render
snapshot on line endings. **Why** Phases 1-4 of the ATLAS test plan each cost a
person a manual step, and every mobile capture was a wide layout cropped.
**Now true** Branch protection also requires the `checks / Checks` run before a
merge. **Evidence** ATLAS CI at 8648bf2; this pull request. **Documents** None
of the project's own documents changed. **By** claude-code, WAMP workstation
## 2026-09-15 — Brand foundation and page shell (#6, PR #21)

**Changed** `main` renders the new `SiteHeader` and `SiteFooter` around an empty `<main>`; `public/css/app.css` holds the brand tokens and a block per component; `APP_NAME` is `Someone Technical`. The demo — `Welcome`, `ItemsScreen`, `SettingsScreen`, `SideNav`, `AppHandler`, `Item` — is deleted with its test cases and snapshot sections; `tests/cases/site.php` added. **Why** Phase 1: the sections build on this shell, and the demo's handlers had to go. **Now true** Link targets are `SiteHeader` constants (DECISIONS 2026-09-15). No section anchor or `start`, `privacy`, `terms`, `contact` view exists yet; those links land on the home page. Page loads log nothing while `LOG_METRICS` is off (#9). **Evidence** Issue #6: DEV validation and captures. **Documents** ARCHITECTURE rewritten (Page shell, Sections, Map, Constraints, Hazards); DECISIONS entry; PLAN, PRODUCT unchanged. **By** claude-code, ITNEUE-154F1007

## 2026-09-15 — Initialised (#6–#19 filed)

**Changed** ARCHITECTURE.md and PLAN.md written from PRODUCT.md; 14 Issues filed across four phases — 4 ready, 8 blocked, 2 needs-human. **Why** /project-init. **Now true** The Map describes the template as shipped, demo included; *Planned structure* is not built. DEV serves `76e31ec` healthy, but `/health` answers 404, `log.metrics` is false, `starter_auto_login` is true and the session cookie lacks `HttpOnly` and `SameSite` — #7, #8 and #9 cover them. **Evidence** The Issues; the DEV probe at 2026-09-15T10:31Z. **Documents** ARCHITECTURE and PLAN written; PRODUCT supplied by the human, unchanged. Merged through a PR, not pushed to `dev` (AGENTS.md §1). **By** claude-code, ITNEUE-154F1007

## 2026-09-14 — Project created

**Changed** Created from the ATLAS microframework template: repository Ammar-Mah/someonetechnical, branches main and dev, the ATLAS rules, workflows and DEV probe. **Why** New project. **Now true** Nothing is planned yet; /project-init writes ARCHITECTURE.md and PLAN.md and files the Issues. **Evidence** new-project.ps1 output. **Documents** PRODUCT.md supplied; the rest are stubs. **By** new-project.ps1 on ITNEUE-154F1007
