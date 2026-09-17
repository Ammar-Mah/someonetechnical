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
footer sets to the accent. Inter 400 and 700; one theme.

## Sections
Three of the nine exist, in `PRODUCT.md` page order:

| Component | Anchor | Holds |
| --- | --- | --- |
| `HeroSection` | none | `PRODUCT.md` §1: the page's only `<h1>`, the supporting text, the action to the intake, "See how it works", the availability note; the animated card |
| `RecognitionSection` | none — nothing links to it | `PRODUCT.md` §2: the heading, the six situations, the closing line |
| `HowItWorksSection` | `SiteHeader::HOW_IT_WORKS` | `PRODUCT.md` §3: the three steps in order, then the action to the intake |

All are static markup with no handler and no client code. Each builds its
list in `mount()` and prints it as one property, which is how every core
component is written (`LLM.txt` §5.2).

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
animation off for every step to stay visible and still.

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
`APP_URL` (DECISIONS 2026-09-16).

## Planned structure

### Pages
| View | Entry | Holds |
| --- | --- | --- |
| `main` | `/` | `SiteHeader`, the nine sections, `SiteFooter` |
| `start` | `?page=start` | the intake, `IntakeScreen` |
| `privacy`, `terms`, `contact` | `?page=<view>` | text supplied by the owner |

Each view is listed in `$views` in `public/index.php`; an unknown page falls
back to `main`.

### Sections
Six remain, `src/app/Components/<Name>Section.php`, one per `PRODUCT.md`
section, after `HowItWorksSection`: `SupportAreasSection`,
`PositioningSection`, `HelpTypesSection`, `ContinuitySection`, `TrustSection`,
`FinalCtaSection`. The copy lives in the
component. Anchor ids are `SiteHeader`'s constants, which every link reads
too: `SupportAreasSection` takes `SiteHeader::WHAT_WE_HELP_WITH`
(`what-we-help-with`) — DECISIONS 2026-09-15. Every "Get someone technical" and
"Book a session" action links to `SiteHeader::START_HREF`, `?page=start`.

### Styling and motion
Each section adds its block to `public/css/app.css` between `SiteHeader`'s and
`SiteFooter`'s, in page order, and builds on the brand tokens. Motion is CSS
keyframes and transitions; under `prefers-reduced-motion: reduce` every
animation shows its final state.
A `public/js/app.js`, if one is added, only enhances (scroll reveals): content
and actions work without it, and it stores nothing in the browser.

### Intake
`IntakeScreen` renders the conversational steps. `IntakeHandler` (an Events
class) validates, applies the abuse limits, stores through `IntakeRequest`,
notifies the owner with `Mailer`, and re-renders only the intake region.
Validation is an early return; every outcome is logged.

## Map
| Path | Holds |
| --- | --- |
| `index.php`, `updater.php` | page and interaction entry points; `updater.php` is framework, read-only |
| `health.php` | `GET /health`: `{"status":"ok"}` and a `health answered` line; framework, read-only, and ships to production |
| `public/index.php` | the visitor identity and `$views`: `main` |
| `runtime.php` | configuration defaults; merges `runtime.dev.php`, then `runtime.local.php`; turns `LOG_METRICS` on where `APP_ENV` is development unless a server file sets it; sets the session cookie's flags |
| `database/` | schema pairs: `0001_create_intake_requests` |
| `docs/` | `DATABASE.md`: the database and its schema |
| `src/app/boot.inc.php` | `app_data()`, `User()` (the session's visitor), the `audit` hook on `Model::$onWrite` |
| `src/app/Views/` | `app` (layout, CSRF meta tag, `<title>` from `APP_NAME`), `main` (the page shell and its sections) |
| `src/app/Components/` | `SiteHeader` (and the link-target constants), `HeroSection`, `RecognitionSection`, `HowItWorksSection`, `SiteFooter` |
| `src/app/Events/`, `src/app/Models/` | not present yet; the autoloader searches both |
| `src/app/Translations/` | `ar`, `de` from the template; nothing selects a language |
| `public/css/app.css` | brand tokens, then one block per component in page order: page, `SiteHeader`, `HeroSection` (with its keyframes), `RecognitionSection`, `HowItWorksSection`, `SiteFooter` |
| `public/css/Baustein.css`, `public/js/` | framework stylesheet and client — read-only |
| `public/fonts/Inter/`, `public/img/` | self-hosted Inter; the favicon |
| `src/core/` | the framework — read-only |
| `tests/` | `run.php` (read-only), `cases/` (`site.php`: the shell's and the sections' links and text, the hero's hidden card and motion rules, and the never-deployed-path guard over the application's PHP — see *Constraints*; `visitor.php`: the visitor session and its cookie; `config.php`: what `runtime.php` resolves beside a server's files — both in child processes; `database.php`: the pairs' columns and reverse, on in-memory SQLite), `snapshots/render.txt` |
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
(`docs/DATABASE.md`), empty until the intake (#42) writes it. SQLite keeps it
in `data/database.sqlite` locally and on DEV; production chooses in #19. No
credential, project access or payment detail is ever collected.

## Logging
Channels: `app` for handler outcomes; `audit` for every write, through the hook
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
- On DEV the session cookie's `path=/` covers the other ATLAS projects on the
  same domain.
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
- `SiteHeader`'s and `SiteFooter`'s section links are bare fragments
  (`#how-it-works`), so they only work on `main`. A view that renders the shell
  elsewhere (#42, #17) must point them at the home page.
- Pages must resolve to the project root directory. `Baustein.js` posts to
  `<page directory>/updater.php`, so `/public/index.php`, or any rewritten URL
  ending in `/`, breaks every interaction.
- Every section Issue edits `app.css`. One delimited block per component keeps
  rebases trivial.
