# Architecture

## Overview
A public, single-page marketing site for Someone Technical, with a
conversational intake behind every "Get someone technical" action.
Server-rendered PHP on Baustein, on shared hosting. Every visitor gets an
anonymous session so the intake can post through `updater.php`; completed
requests are stored on the SQL engine and the owner is notified by mail. No
accounts, no admin screen, no payments, no build step. English only.

The **Map** describes the repository as it is. **Page shell**, **Sections** and
**Visitor session** describe what exists; **Planned structure** describes what
`PLAN.md` still builds.

## Stack
PHP 8.2 (DEV runs 8.4) · Baustein (IDEALS microframework) · SQL engine on
SQLite (`DB_ENGINE=sql`) · `Baustein.css` restyled by `public/css/app.css` · CSS
animation · `.htaccess` (DEV serves it through LiteSpeed). No Composer, npm,
bundler, client framework, or third-party request at page load.

## Request lifecycle
```
Page:        index.php → initialize (runtime.php, which sets the cookie
             flags; starts the session) → functions → boot.inc.php →
             public/index.php (the visitor, ?page= allowlist)
             → Template::view() → @extend('app')
Interaction: Baustein.js → updater.php → session, CSRF, Component, method
             gates → handler → Event → DOM patch
Health:      GET /health → .htaccess → health.php (runtime.php and Log
             only: no session) → {"status":"ok"}
```

## Page shell
`main` renders `SiteHeader`, a `<main id="main">` holding the sections built so
far, and `SiteFooter`, all static markup. The name shown, like the `<title>`, is
`APP_NAME`. Every link stays visible at every width — the section links and
the action wrap as one group — so source order is Tab order.

`public/css/app.css` opens with the brand tokens — paper `#f4efe6`, ink
`#1b1916`, one accent `--signal` `#ff4f00` — pointed onto Baustein's `--page`,
`--text` and `--brand`. The accent carries fills, marks and underlines beneath
ink; as text on the paper it fails contrast (2.9:1). Links are ink with an
accent underline. Keyboard focus is a 3px outline in `--focus`, which the ink
bands, `TrustSection` and the footer, set to the accent. Each `.site-cta`
button sits in a flex row, as a box: inline, it would not lift on hover or
press in. Inter 400 and 700; one theme.

## Sections
All nine exist, in `PRODUCT.md` page order:

| Component | Anchor | Holds |
| --- | --- | --- |
| `HeroSection` | none | `PRODUCT.md` §1: the page's only `<h1>`, the supporting text, the action to the intake, "See how it works", the availability note; the animated card |
| `RecognitionSection` | none — nothing links to it | `PRODUCT.md` §2: the heading, the six situations, the closing line |
| `HowItWorksSection` | `SiteHeader::HOW_IT_WORKS` | `PRODUCT.md` §3: the three steps in order, then the action to the intake |
| `SupportAreasSection` | `SiteHeader::WHAT_WE_HELP_WITH` | `PRODUCT.md` §4: the twelve areas in order, each with one sentence of ours; a note and the action to the intake |
| `PositioningSection` | none | `PRODUCT.md` §5: the statement, a two-sentence explanation of ours, the five differentiators; on the paper darkened by 5% ink |
| `HelpTypesSection` | none | `PRODUCT.md` §6: the four formats in order, no price; Help Session alone set apart, with "Start here" and the action to the intake |
| `ContinuitySection` | none | `PRODUCT.md` §7: the heading, the record kept with the visitor's permission, what it holds; no word for a credential |
| `TrustSection` | none | `PRODUCT.md` §8: the heading and the eight principles, on an ink band; no quote, figure or image |
| `FinalCtaSection` | none | `PRODUCT.md` §9: the heading, the supporting text, the action to the intake and the note, on a panel filled with the accent |

All are static markup with no handler and no client code. A section with a
list builds it in `mount()` and prints it as one property, which is how every
core component is written (`LLM.txt` §5.2). Anchor ids are `SiteHeader`'s
constants, which every link reads too (DECISIONS 2026-09-15), and every "Get
someone technical" and "Book a session" action links to
`SiteHeader::START_HREF`, `?page=start`.

`SupportAreasSection` is an index, not a grid of cards (§4): its entries sit
in newspaper columns, three, two or one by width, with no box, each under a
hairline and an accent tab drawn inside the entry. A mark placed above an
entry is also drawn at the foot of the previous column.

**A section's copy lives in the section**, in the component's constants and
properties — `RecognitionSection::SITUATIONS` holds `PRODUCT.md` §2 word for
word, in the code rather than in a file read at render time. A file under a
path a deployment leaves out is on disk locally and in CI and missing on the
server, so copy read from one passes every rendering check and is absent there
(on DEV, Issue #11). DEV validation is what proves the copy is on the page;
*Constraints* describes the test that looks for such a path.

`HowItWorksSection` numbers its steps with an `<ol>`, so the order is in the
markup rather than only in the styling, and the numeral beside each step is
`aria-hidden`. Its one entrance animation runs from the keyframe's offset **to**
the base state, so `prefers-reduced-motion: reduce` only has to switch the
animation off for every step to stay visible and still. The support areas'
entries, the positioning differentiators, `HelpTypesSection`'s formats,
`ContinuitySection`'s record entries and `TrustSection`'s principles enter the
same way, and `FinalCtaSection`'s availability light pulses three times.

`HeroSection`'s words never move. Its card, `aria-hidden` and without a live
region, plays once in about 4.5 s: three AI suggestions under "Still asking
AI…", then "Someone technical joined" and a human reply. Its markup and
styles are the settled card, and every animation runs to them, so reduced
motion switches them off.

## Visitor session
Nobody signs in. `public/index.php` gives a session without a user a random
`visitor:` identity, unrelated to the session id, so `updater.php` accepts its
interactions under CSRF. Gaining it regenerates the session id, rotates the
token and logs `visitor session started` on `auth`. `User()` returns the
visitor.

`runtime.php`, the only application file read before `session_start()`, makes
the cookie `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS or with an https
`APP_URL` (DECISIONS 2026-09-16). The framework names the cookie after the
folder `APP_URL`'s `public/` sits in and limits it to that path, so the projects
sharing DEV's domain keep their sessions apart, and `APP_URL` must name the
folder the site is served from.

## Planned structure

### Pages
| View | Entry | Holds |
| --- | --- | --- |
| `main` | `/` | `SiteHeader`, the nine sections, `SiteFooter` |
| `start` | `?page=start` | the intake, `IntakeScreen` |
| `privacy`, `terms`, `contact` | `?page=<view>` | text supplied by the owner |

Each view is listed in `$views` in `public/index.php`; an unknown page falls
back to `main`.

### Styling and motion
Each section adds its block to `public/css/app.css` between `SiteHeader`'s and
`SiteFooter`'s, in page order, and builds on the brand tokens. Motion is CSS
keyframes and transitions; under `prefers-reduced-motion: reduce` every
animation shows its final state.
A `public/js/app.js`, if one is added, only enhances (scroll reveals): content
and actions work without it, and it stores nothing in the browser.

## Intake
`?page=start` is the intake. `IntakeScreen` asks `PRODUCT.md`'s seven
questions, one per `intake_requests` column and nothing else: three free-text
answers, two choices that each offer "I don't know", the contact pair, and the
preferred time. The contact pair is the only thing the page requires.

It reads as a conversation. Each question is one turn: a bubble on the ink
carrying who is speaking, the question, and — on the four free-text ones — the
line that says "I don't know" is a fine answer, and under it the visitor's
reply on the paper, set in from the other side. Both speakers are words in the
markup, not shapes in the stylesheet, so the thread survives a screen reader,
forced colours and a stylesheet that never arrives. A turn is a `<div>` when
its answer is one control and a `<fieldset>` when the answer is a group; a
`<legend>` must be its fieldset's first child, so there the bubble is the
legend. The turns arrive in order, the whole thread settling inside 740ms, and
under `prefers-reduced-motion: reduce` nothing moves at all — the stylesheet
is the settled thread, and the entrance only leads up to it.

`IntakeHandler::send()` is the one flow. It reads the form's single JSON
`value`, trims every answer and cuts it to the length its column accepts,
keeps a choice only if `IntakeScreen` offered it, and stores `NULL` for a
question left alone. An empty name or an address `FILTER_VALIDATE_EMAIL`
refuses is an early return: that field's error slot is filled and its input
marked `is-invalid`, nothing is stored, and the visitor keeps what they typed.
A stored request replaces the contents of `IntakeScreen::REGION_ID` with the
confirmation, which renders the visitor's name through `e()` and no answer at
all.

The form carries `method="post"`. `xon:submit` compiles to an inline
`onSubmit` and only `Baustein.js` calls `preventDefault()`, so a submit before
that script has run — or with it blocked — is the browser's own, and a form
with no method would send every answer as a GET query string.

Such a submit is answered rather than swallowed. It reaches `?page=start`
again as a POST, which nothing stores and nothing logs, and `IntakeScreen`
renders a notice at the top of the region: it did not send, and nothing typed
was kept. A browser that will not run the client at all reads the `<noscript>`
line in the form before it ever tries.

Nothing the visitor typed ever reaches the log. The handler's `app` lines
carry an id, a field name or a count; the `audit` line for `intake_requests`
is reduced to the names of the columns that changed by the hook in
`boot.inc.php`. That hook names this one table: a second table holding
personal data must be added to it, or its values are audited by default.

Still to come: the owner's notification through `Mailer`, and the abuse
limits (#16).

## Map
| Path | Holds |
| --- | --- |
| `index.php`, `updater.php` | page and interaction entry points; `updater.php` is framework, read-only |
| `health.php` | `GET /health`: `{"status":"ok"}` and a `health answered` line; framework, read-only, and ships to production |
| `public/index.php` | the visitor identity and `$views`: `main`, `start` |
| `runtime.php` | configuration defaults; merges `runtime.dev.php`, then `runtime.local.php`; turns `LOG_METRICS` on where `APP_ENV` is development unless a server file sets it; sets the session cookie's flags |
| `database/` | schema pairs: `0001_create_intake_requests` |
| `docs/` | `DATABASE.md`: the database and its schema |
| `src/app/boot.inc.php` | `app_data()`, `User()` (the session's visitor), the `audit` hook on `Model::$onWrite`, which logs `intake_requests` writes by column name alone |
| `src/app/Views/` | `app` (layout, CSRF meta tag, `<title>` from `APP_NAME`), `main` (the page shell and its sections), `start` (the page shell around `IntakeScreen`) |
| `src/app/Components/` | `SiteHeader` (and the link-target constants), `HeroSection`, `RecognitionSection`, `HowItWorksSection`, `SupportAreasSection`, `PositioningSection`, `HelpTypesSection`, `ContinuitySection`, `TrustSection`, `FinalCtaSection`, `SiteFooter`, `IntakeScreen` (the start page: the thread, the did-not-send notice and the confirmation) |
| `src/app/Events/` | `IntakeHandler`: `send()`, the intake's one flow |
| `src/app/Models/` | `IntakeRequest` over `intake_requests`: `$fillable`, the `CREATE TABLE` docblock, and `add()`, which stamps the timestamps |
| `src/app/Translations/` | `ar`, `de` from the template; nothing selects a language |
| `public/css/app.css` | brand tokens, then one block per component in page order: page, `SiteHeader`, `HeroSection` (with its keyframes), `RecognitionSection`, `HowItWorksSection`, `SupportAreasSection`, `PositioningSection`, `HelpTypesSection`, `ContinuitySection`, `TrustSection`, `FinalCtaSection`, `SiteFooter`, then `IntakeScreen` — the start page's block, after the shell's rather than in the sections' order, holding the thread's bubbles, its one offset token and its entrance |
| `public/css/Baustein.css`, `public/js/` | framework stylesheet and client — read-only |
| `public/fonts/Inter/`, `public/img/` | self-hosted Inter; the favicon |
| `src/core/` | the framework — read-only |
| `tests/` | `run.php` (read-only), `cases/` (`site.php`: the shell's and the sections' links, text and order, no price and no social proof on the page, each button's flex row, the hero's hidden card and the motion rules, and the never-deployed-path guard over the application's PHP — see *Constraints*; `visitor.php`: the visitor session and its cookie; `config.php`: what `runtime.php` resolves beside a server's files — both in child processes; `database.php`: the pairs' columns and reverse, on in-memory SQLite; `intake.php`: the start page's seven questions and the thread they are asked in, the did-not-send notice, the entrance and its reduced-motion rule, the handler's outcomes, and that no line it logs carries an answer or a contact detail), `snapshots/render.txt` |
| `__dev/` | ATLAS probe, diagnostics, migrator — DEV only, never in production |
| `.htaccess` | refuses source, data, logs, dot-files and Markdown; routes `/health`; sets headers. `atlas sync` replaces all but its `project rules` block, empty here |
| `LLM.txt` | the framework manual |

## Planned domains
| Domain | Responsibility |
| --- | --- |
| Site | layout, brand, the nine sections, the legal pages |
| Intake | the conversational flow, storage, owner notification, abuse limits |
| Operations | the visitor session, `/health`, DEV request summaries, production |

## Data model
One table, `intake_requests`: a request's answers, the contact name and email
address, the preferred session time, `created_at`, `updated_at`, `deleted_at`
(`docs/DATABASE.md`), written by `IntakeHandler` alone. SQLite keeps it
in `data/database.sqlite` locally and on DEV; production chooses in #19. No
credential, project access or payment detail is ever collected.

## Logging
Channels: `app` for handler outcomes — `intake request stored`, `intake
request refused`, `intake answer cut to fit`, `intake choice not offered`; `audit` for every write, through the hook
in `boot.inc.php`; `auth` for `visitor session started`; `security` for
`updater.php` refusals and abuse refusals; `mail` for notifications;
`request` for each request's `request complete` summary where `LOG_METRICS` is
on; `health` for `health answered`. No `site` channel — the sections are static markup that reads nothing and
can fail at nothing. JSONL under `logs/`, request id on every line. Contexts carry ids and counts, and the `auth` line an ip, never intake
answers or contact details, and mail subjects carry neither, because the
`mail` line logs the subject. DEV reads the log through
`/__dev/diagnostics?check=log`.

## Environments
| | Local | DEV | Production |
| --- | --- | --- | --- |
| URL | `http://localhost/someonetechnical/repo/` | `https://exceedlimits.site/atlas/someonetechnical` | `https://someonetechnical.com`, not provisioned |
| Server | WAMP on Windows | shared account, LiteSpeed, PHP 8.4 | not chosen |
| Settings | `runtime.php` | `runtime.dev.php`, written by `deploy-dev.yml` | `runtime.local.php`, written by a person |
| `DEBUG_MODE` | true | true | false |
| `MAIL_TRANSPORT` | `log` | `log` — nothing is delivered | `mail` |
| `LOG_METRICS` | on | on | off |

## Constraints
- Shared hosting: no long-running process or queue worker. Mail is sent inside
  the request.
- CI runs PHP 8.2 and DEV runs 8.4.23: code must work on both.
- DEV and production filesystems are case-sensitive; local Windows is not.
- `updater.php` refuses any interaction without a session user (401); only a
  page load through `public/index.php` gives a session its visitor. A PR into
  `main` fails CI if `public/index.php` contains `Session::set('user', 1)`.
- `src/core/inc/initialize.inc.php` calls `session_start()` on every booted
  request, before app code runs, with a 30-day `SESSION_LIFETIME`. Every
  visitor and crawler gets a session, and every page load without one a new
  visitor and an `auth` line. `health.php` does not boot the framework.
- `deploy-prod.yml` requires `GET /health` → 200 and does not follow
  redirects, so `.htaccess` rewrites the path rather than redirecting it.
- `.htaccess` sends `X-Frame-Options: SAMEORIGIN` and no HSTS, and DEV adds
  `X-Powered-By`. `policies/security.md` requires `DENY` or CSP
  `frame-ancestors`, and HSTS in production.
- DEV honours `.htaccess` (`GET /data/` → 403). The production host is
  unknown; the SQLite file in `data/` relies on it doing the same.
- `MAIL_TRANSPORT=log` on DEV: delivery is never verifiable there, and each
  notification logs a `warn` (`Accepted but NOT delivered`).
- SQLite takes one writer at a time. Schema changes are new `database/` pairs
  (`schema-change`); one that ran on DEV is never edited.
- The never lists in `php-deploy-dev.yml` and `php-deploy-prod.yml` keep the
  repository's own material off the servers — `*.md`, `docs/`, `LLM.txt`,
  `tests/`, `captures/`, the git, agent and tool files — and production also
  drops `__dev/`, `seeds/` and `fixtures/`. Page content never lives there.
  `tests/cases/site.php` carries both lists, checked against the workflows,
  bar `runtime.dev.php` (merged by `runtime.php`) and `__dev/`, and scans
  `src/app/`, `index.php`, `public/index.php` and `runtime.php` for a path
  into them or a bare name like `'tests'`. The scan is a best-effort check for
  the common spellings, not a proof.
- From `PRODUCT.md`: no stack or implementation detail on the page, so
  `APP_NAME` is the product's name and `tests/cases/site.php` guards the page
  text; no prices, testimonials, ratings, logos or customer numbers; reduced
  motion respected; keyboard operable; strong contrast; no horizontal overflow
  on mobile.

## Hazards
- `tests/snapshots/render.txt` embeds `APP_NAME` and `APP_URL` through the
  kit's `Logo`, so renaming the app changes it: read the diff, and never
  `--update` with a local `APP_URL`.
- Any session holder can call every public method of every `Component` and
  `Handler` subclass, and every page load makes one. Every new handler must
  assume a bot is calling it.
- `SiteHeader`'s and `SiteFooter`'s section links read `./#<id>`, not
  `#<id>`, so they lead to the home page's sections from every view the shell
  renders on. A link written as a bare fragment on `start` scrolls the intake
  to nothing (DECISIONS 2026-09-18). `HeroSection`'s link stays a bare
  fragment: the hero is only ever on `main`.
- A form whose only submit handler is `xon:submit` still needs
  `method="post"`. Scripts move to just before `</body>`, so the form is live
  before `xhandle` exists, and a method-less form sends every field as a GET
  query string — into the address bar, the history and the access log.
- `Event::inner()` replaces a region's CHILDREN. Markup that re-declares the
  region's own id nests a second element with that id inside the first — what
  goes in is the region's contents.
- Pages must resolve to the project root directory. `Baustein.js` posts to
  `<page directory>/updater.php`, so `/public/index.php`, or any rewritten URL
  ending in `/`, breaks every interaction.
- Every section Issue edits `app.css`. One delimited block per component keeps
  rebases trivial.
