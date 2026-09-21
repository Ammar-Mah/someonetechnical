# Architecture

## Overview
A public, single-page marketing site for Someone Technical, with a
conversational intake behind every "Get someone technical" action.
Server-rendered PHP on Baustein, shared hosting. Every visitor gets an
anonymous session so the intake can post; requests are stored and mailed to
the owner. No accounts, admin screen, payments or build step.

## Stack
PHP 8.2 (DEV runs 8.4) · Baustein · SQL engine on SQLite (`DB_ENGINE=sql`) ·
`Baustein.css` restyled by `public/css/app.css` · CSS animation · `.htaccess`.
No Composer, npm or third-party request at page load.

## Request lifecycle
```
Page:        index.php → initialize (runtime.php sets the cookie flags;
             session starts) → boot.inc.php → public/index.php (visitor,
             ?page= allowlist) → Template::view() → @extend('app')
Interaction: Baustein.js → updater.php → session, CSRF, method gates
             → handler → Event → DOM patch
Health:      GET /health → .htaccess → health.php (no session) → {"status":"ok"}
```

## Map
| Path | Holds |
| --- | --- |
| `index.php`, `updater.php` | entry points; `updater.php` is framework, read-only |
| `health.php` | `GET /health` and a `health answered` line; framework, ships to production |
| `public/index.php` | the visitor identity and `$views`: `main`, `start` |
| `runtime.php` | defaults; merges `runtime.dev.php`, then `runtime.local.php`; resolves `LOG_METRICS` and `INTAKE_NOTIFY_TO`; sets the cookie flags |
| `database/`, `docs/DATABASE.md` | schema pair `0001_create_intake_requests`, and its description |
| `src/app/boot.inc.php` | `User()`, and the `audit` hook that logs `intake_requests` writes by column name |
| `src/app/Views/` | `app` (layout, CSRF meta), `main` (shell and sections), `start` (shell and `IntakeScreen`) |
| `src/app/Components/` | `SiteHeader` (link-target constants), the five sections, `Pictogram` (the drawn icons), `SiteFooter`, `IntakeScreen` |
| `src/app/Events/` | `IntakeHandler::send()` — honeypot, limit, validation, store, notification |
| `src/app/Models/` | `IntakeRequest`: `$fillable`, and `add()`, which stamps the timestamps |
| `public/css/app.css` | brand tokens, then one block per component in page order, the scroll reveal, then `IntakeScreen` |
| `public/css/Baustein.css`, `public/js/`, `src/core/` | the framework — read-only |
| `public/fonts/Inter/`, `public/img/` | Inter; the favicon |
| `tests/cases/` | `site.php` (shell, sections, motion, the never-deployed guard), `visitor.php`, `config.php` (`runtime.php`, in child processes), `database.php`, `intake.php` (page, handler, and no personal data logged); `snapshots/render.txt` |
| `__dev/` | ATLAS probe, diagnostics, migrator — DEV only |
| `.htaccess` | refuses source, data, logs, dot-files, Markdown; routes `/health`; headers. `atlas sync` owns all but its empty `project rules` block |
| `LLM.txt` | the framework manual |

## Sections
`main` is `SiteHeader`, `<main id="main">` with the five sections in
`PRODUCT.md` order — `HeroSection`, `RecognitionSection`, `HowItWorksSection`,
`SupportAreasSection`, `FinalCtaSection` — and `SiteFooter`. The page is short
by design: at most 300 words in `<main>` (#67, tested). All are static markup
with no handler, lists built in `mount()`. Icons are `Pictogram::svg()`: inline
stroke drawings in `currentColor`, `aria-hidden`, nothing fetched. An unknown `?page=`
falls back to `main`; `privacy`, `terms` and `contact` wait on the owner's
text (#17).

Anchor ids are `SiteHeader` constants that every link reads (DECISIONS
2026-09-15); only How it works and What we help with have one. Every action
links to `SiteHeader::START_HREF`, `?page=start`. The shell's section links
read `./#<id>` so they work from `start` too.

**Copy lives in the section**, never in a file read at render time: a path a
deployment leaves out exists locally and in CI, not on the server (#11).

The brand tokens — paper `#f4efe6`, ink `#1b1916`, accent `--signal`
`#ff4f00` — are pointed onto Baustein's `--page`, `--text`, `--brand`. The
accent fills and underlines; as text on paper it fails contrast (2.9:1). Focus
is a 3px `--focus` outline, the accent on ink bands. Each `.site-cta` sits in
a flex row so it can lift on hover. Inter 400 and 700. Every link stays
visible at every width, so source order is Tab order.

Motion runs **to** the styled state: markup and styles are the settled page,
and each entrance animates from an offset, so `prefers-reduced-motion: reduce`
only switches animations off. The hero is an ink band; its drawing
(`aria-hidden`) plays once in about 4.3 s — a tangle, then a straight line —
and its words never move. The bubbles, steps, tags and final panel rise in on
a scroll timeline (`animation-timeline: view()` inside `@supports`, no script);
a browser without it shows the settled page.

## Visitor session
Nobody signs in. `public/index.php` gives a session without a user a random
`visitor:` identity, regenerates the session id, rotates the token and logs
`visitor session started` on `auth`. `runtime.php` makes the cookie
`HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS or an https `APP_URL`
(DECISIONS 2026-09-16). The cookie is named after and limited to `APP_URL`'s
folder, so projects sharing DEV's domain keep sessions apart.

## Intake
`?page=start` asks `PRODUCT.md`'s seven questions, one per `intake_requests`
column: four free-text questions (building, tool, stuck on, preferred time),
two choices that each offer "I don't know", and the contact pair — the only
thing required.

It reads as a conversation. Each question is a turn: a bubble on the ink
naming the speaker, the question and, on the four free-text ones, "I don't
know is a fine answer", then the visitor's reply, named "You". Speakers are
words in the markup, not shapes. A turn is a `<fieldset>` when its answer is a
group, and there the bubble is the `<legend>`.

`IntakeHandler::send()`, in order:
1. A filled honeypot (`IntakeScreen::TRAP`, off-screen, `aria-hidden`, out of
   the Tab order) gets the normal confirmation; nothing is stored.
2. An address (an IPv6 /64) that has stored `LIMIT` (3) requests in the hour
   gets a notice appended to the form. The count is in `Cache` under a hash;
   only stored requests count. A lock on `cache/intake-limit.lock` spans
   reading, storing and writing it, so a burst cannot slip past.
3. Every answer is trimmed and cut to its column's length; a choice not offered
   is dropped; unanswered is `NULL`. An empty name or invalid address fills
   that field's error slot and keeps what was typed.
4. The request is stored and the region replaced by the confirmation, which
   prints only the name, through `e()`.
5. It is mailed to `INTAKE_NOTIFY_TO` as `New intake request #<id>`, answers
   escaped, `Reply-To` the visitor. An empty recipient is a warning; a failed
   send is `Mailer`'s error. The visitor is confirmed either way.

The form has `method="post"`: a submit before `Baustein.js` runs is the
browser's own, and a method-less form would put every answer in the URL. Such
a POST lands on `?page=start`, which stores nothing and shows a notice that it
did not send; `<noscript>` warns first.

Nothing typed reaches the log. The `audit` hook reduces this table's writes to
column names; a second table holding personal data must be added to it.

## Data model
One table, `intake_requests`: the answers, contact name and email, preferred
time, `created_at`, `updated_at`, `deleted_at` (`docs/DATABASE.md`), written by
`IntakeHandler` alone. SQLite in `data/database.sqlite` locally and on DEV;
production chooses in #19. No credential or payment detail is collected.

## Logging
JSONL under `logs/`, request id on every line. `app`: `intake request stored`,
`refused`, `answer cut to fit`, `choice not offered`, `notification skipped`.
`audit`: every write. `auth`: `visitor session started`. `security`:
`updater.php` refusals, `intake honeypot filled`, `intake request refused:
rate limit`. `mail`: notifications. `request`: `request complete` where
`LOG_METRICS` is on. `health`: `health answered`. Contexts carry ids and
counts, and the `auth` and abuse lines an ip — never answers or contact
details. Mail subjects carry neither: the `mail` line logs the subject.

## Environments
| | Local | DEV | Production |
| --- | --- | --- | --- |
| URL | `http://localhost/someonetechnical/repo/` | `https://exceedlimits.site/atlas/someonetechnical` | `https://someonetechnical.com`, not provisioned |
| Settings | `runtime.php` | `runtime.dev.php`, written by `deploy-dev.yml` | `runtime.local.php`, written by a person |
| `DEBUG_MODE` | true | true | false |
| `MAIL_TRANSPORT` | `log` | `log` — nothing delivered | `mail` |
| `INTAKE_NOTIFY_TO` | placeholder `owner@someonetechnical.invalid` | the same, wherever the transport is `log` | the owner; empty skips the mail |
| `LOG_METRICS` | on | on | off |

## Constraints
- Shared hosting: no queue worker; mail is sent inside the request.
- CI runs PHP 8.2, DEV 8.4. Servers are case-sensitive; Windows is not.
- `updater.php` refuses an interaction without a session user; only a page load
  gives one. CI fails a PR into `main` if `public/index.php` signs in user 1.
- Every booted request, a crawler's too, starts a 30-day session.
- `deploy-prod.yml` needs `GET /health` → 200 without following redirects.
- `.htaccess` sends `X-Frame-Options: SAMEORIGIN`, no HSTS, and DEV adds
  `X-Powered-By`; `policies/security.md` wants `DENY` or `frame-ancestors`,
  and HSTS in production.
- DEV honours `.htaccess` (`/data/` → 403); the SQLite file relies on
  production doing the same.
- On `MAIL_TRANSPORT=log` delivery is never verifiable, each notification logs
  a `warn`, and a `debug` level would log the body's excerpt — the answers —
  so `info` is the floor.
- Schema changes are new `database/` pairs; one that ran on DEV is never
  edited.
- The deployments' never lists keep `*.md`, `docs/`, `LLM.txt`, `tests/`,
  `captures/` and tool folders off servers; production also drops `__dev/`.
  `site.php` checks both lists against the workflows and scans application PHP
  for a path into them — best effort, not proof.
- `PRODUCT.md`: no stack detail, prices, testimonials or ratings on the page.

## Hazards
- `render.txt` embeds `APP_NAME` and `APP_URL`: never `--update` with a local
  `APP_URL`.
- Any session holder can call every public method of every `Component` and
  `Handler`; every new handler must assume a bot.
- A form using `xon:submit` still needs `method="post"`.
- `Event::inner()` replaces a region's children: never re-declare the
  region's id inside it.
- Pages must resolve to the project root: `Baustein.js` posts to
  `<page directory>/updater.php`.
- One `app.css` block per component keeps rebases trivial.
