# History

Newest first. One entry per change that reached `dev`. Entries are never edited except to correct a fact. See .agent/policies/documentation.md.

## 2026-09-21 — The owner hears of every request, and a bot is kept out (#16)

**Changed** `IntakeHandler::send()` now checks a honeypot and a rate limit
before it validates, and mails the owner after it stores. A filled
`IntakeScreen::TRAP` field is thanked and dropped with a `security` warn. A
fourth stored request from one `REMOTE_ADDR` inside the hour is refused with a
notice appended to the form and a `security` warn; the count lives in `Cache`
under a hash of the address. Each stored request goes to `INTAKE_NOTIFY_TO`
under `New intake request #<id>`, `Reply-To` the visitor; an empty recipient
is an `app` warn. `runtime.php` gains the key and resolves it to a
reserved-domain placeholder where `MAIL_TRANSPORT` is `log`, empty elsewhere.
`intake.php` gains six cases, `config.php` one. **Why** #16.
**Now true** No request arrives unannounced once production names the owner,
and a script can no longer fill the table or the inbox. **Evidence** Issue
#16. **Documents** ARCHITECTURE (Intake, Logging, Environments, Constraints,
Map); DECISIONS gains three lines; `runtime.local.example.php` names the key.
**By** claude-code, ITNEUE-154F1007

## 2026-09-18 — The intake reads as a conversation (#43)

**Changed** `IntakeScreen` builds the seven questions as a thread. Each is one
turn: a bubble on the ink carrying the speaker's name, the question, and — on
the four free-text ones — the "I don't know" line that used to sit under the
field, then the visitor's reply on the paper, named "You" and set in from the
other side. A turn is a `<div>` when its answer is one control and a
`<fieldset>` when it is a group, where the bubble is the `<legend>`. A POST
reaching `?page=start` now renders a notice saying the request did not send
and nothing was kept, and the form carries a `<noscript>` line. The
`IntakeScreen` block in `app.css` is rewritten around one offset token and a
staggered entrance; `tests/cases/intake.php` gains five cases. **Why** #43,
carrying #15's "conversational" objective, and the note folded into it from
#42's re-review. **Now true** The intake speaks: every question says who is
asking, every answer says whose it is, and a submit the client never caught is
answered instead of swallowed. The stylesheet is the settled thread, so
reduced motion needs only `animation: none` on `.intake-turn`. Nothing about
what is stored, validated or logged moved. **Evidence** Issue #43.
**Documents** ARCHITECTURE (Intake, Map); DECISIONS gains the two choices —
the speakers are words rather than shapes, and the uncaught submit is told so.
**By** claude-code, ITNEUE-154F1007

## 2026-09-18 — ATLAS rules synced (f54d237)

**Changed** The rules the project runs on came forward one ATLAS commit.
`policies/issues.md` adds a `later` label and states the whole priority order
in one place: `high-priority`, then unlabelled, then `later`.
`policies/proportion.md` gains a build budget — 15 minutes for a small change,
45 for a standard one, none for a heavy one, and an Issue's own `budget: 30m`
line overrides it. `skills/build/SKILL.md` checks the clock against it at step
9b, and `skills/daily-check/SKILL.md` puts `later` behind everything else when
it selects. **Why** A session had no stop rule on a build and no way for the
owner to push an Issue down the queue without closing it. **Now true** A build
that runs long says so on its Issue and either splits or escalates, and an
Issue the owner marks `later` is worked only when nothing else is ready.
**Evidence** `atlas sync` reported ATLAS f54d237; the sync PR's checks.
**Documents** None of the description documents changed — the sync touched
`.agent/` only. **By** claude-code, ITNEUE-154F1007

## 2026-09-18 — The intake's form posts (#42)

**Changed** `IntakeScreen`'s form carries `method="post"`. Its two error slots
are their inputs' `aria-describedby` and `role="alert"` live regions. A choice
the page never offered is dropped before the length check, so it is reported
once rather than twice. `tests/cases/intake.php` has two new cases, and the
choice case now counts the lines. **Why** #42's review, blocking: `xon:submit`
compiles to an inline `onSubmit` and only `Baustein.js` calls
`preventDefault()`, but every script is moved to just before `</body>` — so a
submit before that script ran, or with it blocked, was the browser's own, and a
form with no method sends its fields as a GET query string. The reviewer
reproduced it on DEV: the visitor's name and email address in the address bar,
the history and the web server's access log, the request lost, and nothing in
the application log to say so. **Now true** A submit the framework does not
catch is a POST that carries nothing in the URL. A form on this site without a
method is a defect a test now names. **Evidence** Issue #42. **Documents**
ARCHITECTURE (Intake, Hazards); the planned *Intake* stub is gone, and the
audit hook's one-table filter is written down where it is described. **By**
claude-code, ITNEUE-154F1007

## 2026-09-18 — A visitor can send a request from the intake (#42)

**Changed** `?page=start` is a page: `start.php` in `$views`, the shell around
a new `IntakeScreen`. It asks `PRODUCT.md`'s seven questions, one per
`intake_requests` column, with "I don't know" as a real option on the two
choice questions and as a note under each free-text one. `IntakeHandler::send()`
trims every answer, cuts it to its column's length, keeps only a choice the
screen offered, refuses an empty name or an address `FILTER_VALIDATE_EMAIL`
rejects, and otherwise stores the request through a new `IntakeRequest` model
and replaces the region with a confirmation. The `audit` hook in
`boot.inc.php` now reduces `intake_requests` writes to their column names, and
`SiteHeader`'s and `SiteFooter`'s section links became `./#<id>` so they lead
to the home page from the intake. `tests/cases/intake.php` is new with twelve
cases; `app.css` gains the `IntakeScreen` block. **Why** #42, carrying #15's
first four criteria. **Now true** `intake_requests` has a writer, and nothing
the visitor typed reaches the log — the handler's lines carry an id, a field
name or a count, and the `audit` line carries column names. A bare fragment in
the page shell is now a defect. **Evidence** Issue #42. **Documents**
ARCHITECTURE (Intake — new and no longer planned, Pages, Map, Data model,
Logging, Hazards); DECISIONS (the link form, the cut-to-fit rule); docs/DATABASE
(how a checkout applies the pairs, the audit line's shape); PRODUCT and PLAN
unchanged. **By** claude-code, ITNEUE-154F1007

## 2026-09-18 — Support areas and positioning complete the page (#12)

**Changed** `SupportAreasSection` and `PositioningSection` follow
`HowItWorksSection` in `main.php`. The first takes
`SiteHeader::WHAT_WE_HELP_WITH` as its id and sets `PRODUCT.md` §4's twelve
areas, each with one sentence of ours, as an index in columns, then a note
and the action to the intake. The second states §5 on a darker band, with a
two-sentence explanation and the five differentiators. Each has its
`app.css` block with an entrance that reduced motion switches off.
`tests/cases/site.php` has six new cases, and two cases now cover the new
sections. **Why** #12. **Now true** All nine sections exist, and the header's
and footer's "What we help with" reach their target. **Evidence** Issue #12.
**Documents** ARCHITECTURE (Sections, Planned structure, Map); PRODUCT,
PLAN, DECISIONS unchanged. **By** claude-code, ITNEUE-154F1007

## 2026-09-18 — The process now costs what the change is worth (ATLAS 1171b71)

**Changed** `atlas sync` brought ATLAS `df273be..1171b71`: a new
`.agent/policies/proportion.md`, a new absolute rule 11 in `AGENTS.md`, and the
rule threaded through `policies/deployment.md`, `documentation.md`, `issues.md`,
`review.md`, `testing.md` and the `build`, `daily-check`, `plan`, `project-init`,
`review`, `update-issue` and `validate-dev` skills. **Why** A thirteen-line CSS
fix was being planned, validated, reviewed and written up like a database table.
**Now true** Every Issue is sized at the claim — `size: small`, `standard` or
`heavy` — and the size caps the plan, the validation, the review and the history
entry, and decides whether the Issue holds the DEV lane. A small change rides
with the next deployment. The proof never shrinks; a failure lifts every cap. A
reversible product choice is the agent's to take and record in `DECISIONS.md`,
not a reason to stop. **Evidence** `atlas check` reports Ammar-Mah/atlas at
1171b71; `.agent/project.json` now records it. **Documents** HISTORY only;
`.agent/` is centrally managed. **By** claude-code, ITNEUE-154F1007

## 2026-09-17 — Every "Get someone technical" button lifts and presses in (#51)

**Changed** In `public/css/app.css`, `.how-it-works-action` and
`.help-type-action` are one-line flex rows (`display: flex`, `align-items:
center`, `height: 1lh`). `tests/cases/site.php` has one new case: each of the
page's five `site-cta` buttons sits in a flex row. **Why** #51. Printed
inline, those two buttons ignored the `translate` of `.site-cta:hover` and
`:active`, and only their shadow changed. **Now true** All five move −1px,
−1px on hover and +2px, +2px when pressed. The two paragraphs stay 24px tall,
so nothing around them moves; a browser without the `lh` unit lets them grow
to 44px. **Evidence** Issue #51. **Documents** ARCHITECTURE (Page shell,
Planned structure, Map); PRODUCT, PLAN, DECISIONS unchanged. **By**
claude-code, ITNEUE-154F1007

## 2026-09-17 — Trust and the final call to action close the page (#14)

**Changed** `TrustSection` and `FinalCtaSection` follow `ContinuitySection`
in `main.php`. The first, "Real technical judgment. No technical theatre.",
lists `PRODUCT.md` §8's eight principles on an ink band that sets `--focus`
to the accent. The second states §9 on a panel filled with the accent, with
"Get someone technical" to the intake and the note under it. Each has its
`app.css` block and motion that reduced motion switches off.
`tests/cases/site.php` has four new cases and extends the order case. **Why**
#14. **Now true** Seven of the nine sections exist; #12's two remain. The
tests refuse a testimonial, rating, star, customer count or logo anywhere on
the page. **Evidence** Issue #14. **Documents** ARCHITECTURE (Page shell,
Sections, Planned structure, Map); PRODUCT, PLAN, DECISIONS unchanged.
**By** claude-code, ITNEUE-154F1007

## 2026-09-17 — Types of help and continuity join the page (#13)

**Changed** `HelpTypesSection` and `ContinuitySection` follow
`HowItWorksSection` in `main.php`. The first lists `PRODUCT.md` §6's four
formats and sets Help Session apart on a tinted raised card, with "Start here"
and the action to the intake. The second, "Someone who remembers your
project", says the record is kept with the visitor's permission and lists what
it holds. Each has its `app.css` block and an entrance that reduced motion
switches off. `tests/cases/site.php` has six new cases. **Why** #13. **Now
true** Five of the nine sections exist, and the tests refuse a price anywhere
on the page. **Evidence** Issue #13. **Documents** ARCHITECTURE (Sections,
Planned structure, Map); PRODUCT, PLAN, DECISIONS unchanged. **By**
claude-code, ITNEUE-154F1007

## 2026-09-17 — Each project keeps its own session cookie (df273be)

**Changed** Synced from ATLAS df273be. `src/core/inc/initialize.inc.php`
limits the session cookie to the folder `APP_URL`'s `public/` sits in and
names it `bst` and ten hex characters of that path; it was `PHPSESSID` at
`/`. `deploy-prod.yml` applies a release's `database/` pairs after the
upload: it puts the project's migrator on the server alone, under a random
name with a one-time token (`__dev/migrate.php` reads a `.release-token`
beside it when no `DEV_PROBE_TOKEN` is set), removes it, and fails if the
pairs did not apply or that address still answers. Rules: a claim carries a
random mark and the claim GitHub dated earliest wins; a session that finds a
sync PR open waits for it. **Why** ATLAS 2f386cd, 4514ef9 and c46cc22, so
that two sessions can work one project (ATLAS test plan, Phase 7). **Now
true** The ATLAS projects on DEV's shared domain no longer exchange session
cookies. An approved release applies its own schema pairs with the migrator
DEV uses, so the SQLite files no longer need a person's database tool (#19's
comment of 07:11). Production's database is still #19's to choose.
**Evidence** `atlas sync` output; 141 tests passed in the sync worktree; this
pull request's checks and the DEV deployment it starts. **Documents**
ARCHITECTURE (Visitor session, Constraints); `docs/DATABASE.md` (Changing the
schema); PRODUCT, PLAN, DECISIONS unchanged. **By** claude-code,
ITNEUE-154F1007

## 2026-09-17 — Requests get a table on the SQL engine (#41)

**Changed** `runtime.php` sets `DB_ENGINE` to `sql`: with `DB_NAME` empty,
SQLite in `data/database.sqlite`. The pair `database/0001_create_intake_requests`
creates `intake_requests`, one column per intake question plus the contact,
indexed on `created_at`. `tests/cases/database.php` applies every pair to
in-memory SQLite and holds its columns and its reverse. `docs/DATABASE.md` is
new; `runtime.local.example.php`, two README sentences and the ignore files'
comments follow the switch. **Why** #41, split from #15. **Now true** A DEV
deployment applies the pair; neither DEV nor a checkout needs a database
server or a credential. The table stays empty until #42. **Evidence** Issue #41. **Documents**
ARCHITECTURE (Overview, Stack, Map, Data model, Constraints, Hazards);
DECISIONS; PLAN; `docs/DATABASE.md`; PRODUCT unchanged. **By** claude-code,
ITNEUE-154F1007

## 2026-09-17 — The never-deployed guard is a best-effort check (#11)

**Changed** `tests/cases/site.php`: the guard reads a `{{ }}` or `{% %}` block
inside an HTML comment, which the engine runs, and drops `<!-- -->` only from
the markup around the blocks; three refuse cases. The guard's comment,
`ARCHITECTURE.md` *Sections* and *Constraints*, and `RecognitionSection`'s
docblock call it a best-effort check for the common spellings, listing
nothing it follows or lets pass. Two test comments no longer say the checks
scan `tests/`. The #11 entry below corrected. **Why** #11's review failed
repair attempt 3; the Decision of 2026-09-17 chose this. **Now true** DEV
validation, not the guard, proves copy is on the page. **Evidence** Issue
#11, repair attempt 4. **Documents** ARCHITECTURE as above; DECISIONS (the
guard's standing); PRODUCT, PLAN unchanged. **By** claude-code,
ITNEUE-154F1007

## 2026-09-17 — `/health` arrives with the ATLAS rules (2bef511, #8)

**Changed** Synced from ATLAS 2bef511. New framework file `health.php`, which
the root `.htaccess` routes `/health` to: `{"status":"ok"}` as JSON and a
`health answered` line on `health`, from `runtime.php` and `Log` alone, so no
session. `.htaccess` gains a `project rules` block that syncs keep. The DEV
deployment writes `LOG_METRICS => true` and applies `database/*.sql` whenever
the upload carries any; `php-checks` no longer scans `tests/` for the DEV
tooling name. **Why** The Decision on #8: ATLAS ships `/health`, and the
project takes it through a sync. **Now true** `/health` needs no project code.
`runtime.php`'s override comment omits `LOG_METRICS` (#37). **Evidence**
`atlas sync` output; this pull request's checks; Issue #8. **Documents**
ARCHITECTURE (Request lifecycle, Planned structure, Map, Logging, Constraints); PRODUCT,
PLAN, DECISIONS unchanged. **By** claude-code, ITNEUE-154F1007

## 2026-09-16 — The hero opens the page (#10)

**Changed** `HeroSection` renders first in `<main>` (`main.php`): the page's
only `<h1>` and the rest of `PRODUCT.md` §1 in static markup, then an
`aria-hidden` card. In CSS only, once, the card moves from three AI
suggestions under "Still asking AI…" to "Someone technical joined" and a
human reply. `app.css` gains the hero's block; `tests/cases/site.php` four
cases. **Why** #10: the page lacked what the service is, at a glance.
**Now true** Three of the nine sections exist. Every card animation runs to
its styled state, so reduced motion only switches them off. **Evidence**
Issue #10. **Documents** ARCHITECTURE (Sections, Planned structure, Map);
PRODUCT, PLAN, DECISIONS unchanged. **By** claude-code, ITNEUE-154F1007

## 2026-09-16 — Every DEV request leaves its summary line (#9)

**Changed** `runtime.php`: `LOG_METRICS` defaults to `null` and is resolved
after the per-server merge — on where `APP_ENV` is `development`, off anywhere
else — unless a server file sets it. `tests/cases/config.php` runs
`runtime.php` beside each kind of server's files. **Why** #9: DEV reported
`log.metrics: false`, so a page load there left no line, and the logging rules
want the summary as DEV's heartbeat. **Now true** Local checkouts and DEV write
one `request complete` line per request on `request`; production writes none
unless its file asks. **Evidence** Issue #9. **Documents** ARCHITECTURE
(Logging, Environments, Constraints, Map); DECISIONS (the derived default);
PRODUCT, PLAN unchanged. **By** claude-code, ITNEUE-154F1007

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
true** The review's spellings are refused; *Constraints* names what passes -
not all of it: a block inside an HTML comment was never read (review, 11:23).
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
