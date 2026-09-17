# Plan

The shape of the work. GitHub Issues hold the live state; this file holds none.
Coarse Issues are split with `plan` (Mode B) before anyone claims them.

## Phase 1 — Foundation
A public page shell on DEV that is safe to show anyone and visible in the log.
- Brand foundation and page shell: tokens, type, header, footer; the demo
  removed (#6)
- Visitor session in place of the starter sign-in, with a hardened session
  cookie (#7)
- `/health` for the production smoke check (#8)
- Request summaries on DEV (#9)

## Phase 2 — The page
`PRODUCT.md` sections 1–9 on DEV, in order, each with its motion and its
reduced-motion fallback. The five Issues run in parallel once #6 lands.
- Hero with the stuck-to-unstuck animation (#10)
- Recognition and how it works (#11)
- What we help with, and positioning (#12)
- Types of help, and human continuity (#13)
- Trust, and the final call to action (#14)

## Phase 3 — Conversion
"Get someone technical" becomes a request the owner receives.
- Conversational intake with stored requests (#15), in order:
  - the SQL engine on SQLite and the `intake_requests` table, integrating
    alone as a schema change (#41)
  - a visitor sends a request from `?page=start` (#42)
  - the intake reads as a conversation (#43)
- Owner notification and abuse limits (#16, coarse)
- Privacy, terms and contact pages, with text from the owner (#17)

## Phase 4 — Launch
someonetechnical.com serves the site from `main`.
- Launch quality: metadata, security headers, keyboard, overflow, motion,
  page weight (#18, coarse)
- Production provisioned by a person, and the first release proven (#19)
