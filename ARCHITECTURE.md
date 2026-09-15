# Architecture

## Overview
A public, single-page marketing site for Someone Technical, with a
conversational intake behind every "Get someone technical" action.
Server-rendered PHP on Baustein, on shared hosting. Every visitor gets an
anonymous session so the intake can post through `updater.php`; completed
requests are stored on the file engine and the owner is notified by mail. No
accounts, no admin screen, no payments, no build step. English only.

The **Map** describes the repository as it is: the ATLAS Baustein template with
the page shell in place of its demo. **Page shell** describes what exists;
**Planned structure** describes what `PLAN.md` still builds, none of which
exists yet.

## Stack
PHP 8.2 (DEV runs 8.4) · Baustein (IDEALS microframework) · file engine
(`DB_ENGINE=file`) · `Baustein.css` restyled by `public/css/app.css` · CSS
animation · `.htaccess` (DEV serves it through LiteSpeed). No Composer, npm,
bundler, client framework, or third-party request at page load.

## Request lifecycle
```
Page:        index.php → initialize (starts the session) → functions →
             boot.inc.php → public/index.php (who is this, ?page= allowlist)
             → Template::view() → @extend('app')
Interaction: Baustein.js → updater.php → session, CSRF, Component, method
             gates → handler → Event → DOM patch
Health:      GET /health → {"status":"ok"}   (planned)
```

## Page shell
`main` renders `SiteHeader`, an empty `<main id="main">` for the sections, and
`SiteFooter`, all static markup. The name shown, like the `<title>`, is
`APP_NAME`. Every link stays visible at every width — the section links and
the action wrap as one group — so source order is Tab order.

`public/css/app.css` opens with the brand tokens — paper `#f4efe6`, ink
`#1b1916`, one accent `--signal` `#ff4f00` — pointed onto Baustein's `--page`,
`--text` and `--brand`. The accent carries fills, marks and underlines beneath
ink; as text on the paper it fails contrast (2.9:1). Links are ink with an
accent underline. Keyboard focus is a 3px outline in `--focus`, which the ink
footer sets to the accent. Inter 400 and 700; one theme.

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
`src/app/Components/<Name>Section.php`, one per `PRODUCT.md` section, in page
order: `HeroSection`, `RecognitionSection`, `HowItWorksSection`,
`SupportAreasSection`, `PositioningSection`, `HelpTypesSection`,
`ContinuitySection`, `TrustSection`, `FinalCtaSection`. The copy lives in the
component. Anchor ids are `SiteHeader`'s constants, which every link reads
too: `HowItWorksSection` takes `SiteHeader::HOW_IT_WORKS` (`how-it-works`),
`SupportAreasSection` `SiteHeader::WHAT_WE_HELP_WITH` (`what-we-help-with`) —
DECISIONS 2026-09-15. Every "Get someone technical" and "Book a session" action
links to `SiteHeader::START_HREF`, `?page=start`.

### Styling and motion
Each section adds its block to `public/css/app.css` between `SiteHeader`'s and
`SiteFooter`'s, in page order, and builds on the brand tokens. Motion is CSS
keyframes and transitions; under `prefers-reduced-motion: reduce` every
animation shows its final state.
A `public/js/app.js`, if one is added, only enhances (scroll reveals): content
and actions work without it, and it stores nothing in the browser.

### Visitor session
`public/index.php` gives a visitor without a session an anonymous identity, so
`updater.php` accepts their interactions; CSRF still applies. It replaces the
starter's `Session::set('user', 1)`. Nothing signs in.

### Intake
`IntakeScreen` renders the conversational steps. `IntakeHandler` (an Events
class) validates, applies the abuse limits, stores through `IntakeRequest`,
notifies the owner with `Mailer`, and re-renders only the intake region.
Validation is an early return; every outcome is logged.

### Health
`GET /health` answers `{"status":"ok"}` and nothing else. Unlike `__dev/`, it
ships to production, where `deploy-prod.yml` checks it.

## Map
| Path | Holds |
| --- | --- |
| `index.php`, `updater.php` | page and interaction entry points; `updater.php` is framework, read-only |
| `public/index.php` | the starter sign-in (`Session::set('user', 1)`) and `$views`: `main` |
| `runtime.php` | configuration defaults; merges `runtime.dev.php`, then `runtime.local.php` |
| `src/app/boot.inc.php` | `app_data()`, the `User()` stub, the `audit` hook on `Model::$onWrite` |
| `src/app/Views/` | `app` (layout, CSRF meta tag, `<title>` from `APP_NAME`), `main` (the page shell) |
| `src/app/Components/` | `SiteHeader` (and the link-target constants), `SiteFooter` |
| `src/app/Events/`, `src/app/Models/` | not present yet; the autoloader searches both |
| `src/app/Translations/` | `ar`, `de` from the template; nothing selects a language |
| `public/css/app.css` | brand tokens, then one block per component: page, `SiteHeader`, `SiteFooter` |
| `public/css/Baustein.css`, `public/js/` | framework stylesheet and client — read-only |
| `public/fonts/Inter/`, `public/img/` | self-hosted Inter; the favicon |
| `src/core/` | the framework — read-only |
| `tests/` | `run.php` (read-only), `cases/` (`site.php`: the shell's links and text), `snapshots/render.txt` |
| `__dev/` | ATLAS probe, diagnostics, migrator — DEV only, never in production |
| `.htaccess` | refuses source, data, logs, dot-files and Markdown; sets headers |
| `LLM.txt` | the framework manual |

## Planned domains
| Domain | Responsibility |
| --- | --- |
| Site | layout, brand, the nine sections, the legal pages |
| Intake | the conversational flow, storage, owner notification, abuse limits |
| Operations | the visitor session, `/health`, DEV request summaries, production |

## Data model
Planned: one table, `intake_requests` — the intake answers, a contact name and
email address, the preferred session time, `created_at`, `updated_at`,
`deleted_at`. On the file engine it lives under `data/`, which `.htaccess`
refuses over HTTP. No credential, project access or payment detail is ever
collected. `docs/DATABASE.md` is written when the table exists.

## Logging
Channels: `app` for handler outcomes; `audit` for every write, through the hook
in `boot.inc.php`; `security` for `updater.php` refusals and abuse refusals;
`mail` for notifications. No `auth` channel — nothing signs in. JSONL under
`logs/`, request id on every line. Contexts carry ids and counts, never intake
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
| `LOG_METRICS` | on | required on; currently off | off |

## Constraints
- Shared hosting: no long-running process or queue worker. Mail is sent inside
  the request.
- CI runs PHP 8.2 and DEV runs 8.4.23: code must work on both.
- DEV and production filesystems are case-sensitive; local Windows is not.
- `updater.php` refuses any interaction without a session user (401). A PR into
  `main` fails CI while `public/index.php` contains `Session::set('user', 1)`.
- `src/core/inc/initialize.inc.php` calls `session_start()` on every booted
  request, before app code runs, with a 30-day `SESSION_LIFETIME`. Every
  visitor, crawler and health check that boots the framework gets a session.
- DEV's session cookie is `path=/; secure`, with no `HttpOnly` and no
  `SameSite`, on a domain shared with other ATLAS projects (observed
  2026-09-15). `policies/security.md` requires `HttpOnly`, `Secure`,
  `SameSite=Lax`.
- `deploy-prod.yml` requires `GET /health` → 200 and does not follow
  redirects. DEV answers 404 (2026-09-15).
- The DEV probe reports `log.metrics: false` (2026-09-15): `runtime.php`
  defaults it off and `runtime.dev.php` does not set it.
- `.htaccess` sends `X-Frame-Options: SAMEORIGIN` and no HSTS, and DEV adds
  `X-Powered-By`. `policies/security.md` requires `DENY` or CSP
  `frame-ancestors`, and HSTS in production.
- DEV honours `.htaccess` (`GET /data/` → 403, 2026-09-15). The production
  host is unknown; the file engine relies on it doing the same.
- `MAIL_TRANSPORT=log` on DEV: delivery is never verifiable there, and each
  notification logs a `warn` (`Accepted but NOT delivered`).
- File engine: the whole table is decoded per request, one writer at a time,
  comfortable into the low tens of thousands of rows. `whereRaw()` and
  `groupBy()` throw.
- Deployments never upload `*.md`, `docs/`, `LLM.txt`, `tests/` or dot-folders.
  Page content never lives in them.
- From `PRODUCT.md`: no stack or implementation detail on the page, so
  `APP_NAME` is the product's name and `tests/cases/site.php` guards the page
  text; no prices, testimonials, ratings, logos or customer numbers; reduced
  motion respected; keyboard operable; strong contrast; no horizontal overflow
  on mobile.

## Hazards
- `tests/snapshots/render.txt` embeds `APP_NAME` and `APP_URL` through the
  kit's `Logo`, so renaming the app changes it: read the diff, and never
  `--update` with a local `APP_URL`. A Windows checkout with
  `core.autocrlf=true` fails that snapshot on line endings alone; convert the
  working copy to LF before trusting a local run.
- Any session holder can call every public method of every `Component` and
  `Handler` subclass — while the starter signs everyone in, that is every
  visitor. Every new handler must assume a bot is calling it.
- `SiteHeader`'s and `SiteFooter`'s section links are bare fragments
  (`#how-it-works`), so they only work on `main`. A view that renders the shell
  elsewhere (#15, #17) must point them at the home page.
- Pages must resolve to the project root directory. `Baustein.js` posts to
  `<page directory>/updater.php`, so `/public/index.php`, or any rewritten URL
  ending in `/`, breaks every interaction.
- Every section Issue edits `app.css`. One delimited block per component keeps
  rebases trivial.
