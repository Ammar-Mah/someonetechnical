# History

Newest first. One entry per change that reached `dev`. Entries are never edited except to correct a fact. See .agent/policies/documentation.md.

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
