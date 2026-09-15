# Architecture

## Overview
A public, single-page marketing site for Someone Technical, with a
conversational intake behind every "Get someone technical" action.
Server-rendered PHP on Baustein, on shared hosting. Every visitor gets an
anonymous session so the intake can post through `updater.php`; completed
requests are stored on the file engine and the owner is notified by mail. No
accounts, no admin screen, no payments, no build step. English only.

The **Map** describes the repository as it is: the ATLAS Baustein template,
demo included. **Planned structure** describes what `PLAN.md` builds. None of
it exists yet.

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
component. Anchor ids are class constants and are the contract with the header
and footer links: `HowItWorksSection::ANCHOR` is `how-it-works`,
`SupportAreasSection::ANCHOR` is `what-we-help-with`. Every "Get someone
technical" and "Book a session" action links to `?page=start`.

### Styling and motion
Brand tokens (accent, background, ink, type) open `public/css/app.css`,
followed by one delimited block per component in page order. Fonts are
self-hosted under `public/fonts/`. Motion is CSS keyframes and transitions;
under `prefers-reduced-motion: reduce` every animation shows its final state.
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
| `src/app/Views/` | `app` (layout, CSRF meta tag), `main` (the demo's app shell) |
| `src/app/Components/` | demo: `Welcome`, `ItemsScreen`, `SettingsScreen`, `SideNav` |
| `src/app/Events/` | demo: `AppHandler` — navigation, theme, language, items |
| `src/app/Models/` | demo: `Item` |
| `src/app/Translations/` | demo: `ar`, `de` |
| `public/css/app.css` | token overrides, all commented out |
| `public/css/Baustein.css`, `public/js/` | framework stylesheet and client — read-only |
| `public/fonts/Inter/`, `public/img/` | self-hosted Inter; the favicon |
| `src/core/` | the framework — read-only |
| `tests/` | `run.php` (read-only), `cases/`, `snapshots/render.txt` |
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
  `APP_NAME` cannot stay `Baustein`; no prices, testimonials, ratings, logos or
  customer numbers; reduced motion respected; keyboard operable; strong
  contrast; no horizontal overflow on mobile.

## Hazards
- `tests/snapshots/render.txt` embeds `APP_NAME`, `APP_URL` and the demo
  components, and `tests/cases/view.php` renders `main`. Removing the demo or
  renaming the app changes both: read the diff, and never `--update` with a
  local `APP_URL`.
- A visitor session lets anonymous visitors call every public method of every
  `Component` and `Handler` subclass. The demo's writing handlers
  (`AppHandler::addItem()` and the rest) must be gone before the site is
  public, and every new handler must assume a bot is calling it.
- Pages must resolve to the project root directory. `Baustein.js` posts to
  `<page directory>/updater.php`, so `/public/index.php`, or any rewritten URL
  ending in `/`, breaks every interaction.
- Every section Issue edits `app.css`. One delimited block per component keeps
  rebases trivial.
