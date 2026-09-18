---
name: validate-dev
description: Prove a change works on the remote DEV environment by testing every acceptance criterion against the live deployment and recording the evidence. Use after merging to dev, before review, and whenever an Issue needs proof rather than assertion. Local success is never validation.
---

# Skill: validate-dev

Proves — or disproves — that a change works, on remote DEV, at the exact commit
under test.

> Passing locally does not mean the task is validated.

Nothing else in ATLAS produces truth. Everything downstream — review, release,
production — trusts this skill's output. A sloppy validation is worse than none,
because it is believed.

## 1. Establish which commit you are testing

```powershell
gh run list --workflow=deploy-dev.yml --limit 1 --json databaseId,status,conclusion,headSha
```

If it is running: `gh run watch <databaseId> --exit-status`.

If it failed:

```powershell
gh run view <databaseId> --log-failed
```

A failed deployment is a failed Issue. Stop. Do not validate. Diagnose the
deployment or escalate.

Record the expected SHA:

```powershell
git rev-parse origin/dev
```

## 2. The probe gate — non-negotiable

The site is `urls.dev` in the project's own settings — always
`https://<domain>/atlas/<project>` — and the token for the diagnostics
endpoint comes from `atlas token`, which derives it the way the deployment
did. Nothing is looked up or copied by hand.

```powershell
$dev   = (Get-Content .agent/project.json -Raw | ConvertFrom-Json).urls.dev
$token = atlas token
Invoke-RestMethod "$dev/__dev/probe" | ConvertTo-Json
```

Assert every one of these:

| Field | Required |
| --- | --- |
| HTTP status | `200` |
| `environment` | `development` |
| `git_commit` | **equals the SHA from step 1** |
| `health` | `ok` |
| `database` | `reachable` |
| `storage` | `writable` |
| `migration_status` | `current` (when the change touched the schema) |
| `log.enabled`, `log.writable` | `true` — otherwise nothing that happens can be observed |
| `log.errors_recent` | `0` — a deployment that booted with errors is not a baseline |

**If `git_commit` does not match, stop immediately.** You are testing old code.
Everything you observe would be a lie about the wrong commit. Investigate:

```powershell
gh run list --workflow deploy-dev.yml --branch dev --limit 3 --json headSha,conclusion,databaseId,createdAt
```

A run's `headSha` is what the pipeline deployed. The probe's `git_commit`,
`branch`, `workflow_run` and `deployed_at` are what the server holds — read
from its `.dev-state.json`, which is never served over HTTP. Disagreement means
a broken deployment — see `policies/deployment.md` for the diagnosis order.

If `log.errors_recent` is not `0`, read them before anything else —
`check=errors&since=15m` — and treat each as a finding.

Record the probe output in your evidence.

## 3. Re-read what you must prove

```powershell
gh issue view 31 --json body,comments
```

Every acceptance criterion, from the body and from the comments. A criterion
added in a comment counts.

## 4. Test each criterion

One at a time. Record what you did and what actually happened.

Use the tools available: HTTP requests, a browser if you have one, the
diagnostics endpoint, log inspection.

```powershell
# GET
$r = Invoke-WebRequest "$dev/contact" -SkipHttpErrorCheck
$r.StatusCode
$r.Content -match 'name="email"'

# POST a form
$body = @{ name = "Test User"; email = "test@example.com"; message = "Hello"; _token = $token }
$r = Invoke-WebRequest "$dev/contact" -Method Post -Body $body -SkipHttpErrorCheck
$r.StatusCode
$r.Headers.Location

# JSON API
Invoke-RestMethod "$dev/api/contacts" -Headers @{ Accept = "application/json" }
```

For a CSRF-protected form, fetch the page first and extract the token — that is
part of proving the form works.

### Cover all of it

1. **Each acceptance criterion**, individually.
2. **The happy path**, end to end, as a user would.
3. **Failure paths** — empty, missing, wrong type, too long, unauthorised.
4. **Regressions** — the behavior adjacent to what changed. If you touched a
   shared validator, exercise every form that uses it.
5. **Data effects** — confirm the row exists and holds the right values.
6. **Errors** — check the DEV log and the browser console for anything new.

For UI changes, also: 375px, 768px, and 1280px widths; keyboard reach of new
controls; no console errors; no failed network requests; and the **after**
captures — next section.

For API changes, also: status codes for success, validation failure, auth
failure, not-found; response shape; backwards compatibility.

### Confirming data effects

```powershell
Invoke-RestMethod "$dev/__dev/diagnostics?check=db" -Headers @{ "X-Dev-Token" = $token }
```

Diagnostics never returns raw customer data. Use counts, existence checks, and
column metadata. If the project's diagnostics cannot answer the question, say so
in the evidence rather than assuming.

### Capture what the user sees

For every criterion about a screen, take the **after** capture now — DEV at
the probe's commit, the same screens and viewports as the **before** that
`build` took just before the merge (`policies/testing.md`, "Captures"):

```powershell
atlas capture "$dev/contact" captures/31-contact-desktop-after.png
atlas capture "$dev/contact" captures/31-contact-mobile-after.png -Width 390 -Height 844 -Mobile
```

Each line it prints names the width the page reported - quote them, and never
report a mobile capture whose `innerWidth` is not 390. Then look at every
file. Reject a login page, an error page, a blank or
unstyled frame, the wrong screen. Compare with the before:

```powershell
(Get-FileHash captures\31-contact-desktop-before.png).Hash -eq (Get-FileHash captures\31-contact-desktop-after.png).Hash
```

`True` where the criterion expected a change means the change did not land or
you captured the wrong thing — a finding, not a pass. `True` on the adjacent
screen the change must not touch is a regression check passed.

If the before files are not in this worktree — another session built the
Issue — use the images from its hand-off comment; they are already on GitHub.
A screen with no before is posted as **Preview**. A screen you cannot capture
— behind a login, no entry URL — is stated as **not captured**, with the
reason, and proven the usual way.

## 5. Read the log — the primary evidence

The log is where a criterion is actually proven: the response says what came
back, the log says what happened. Read it in this order.

```powershell
# Negative first: anything that went wrong in the window is a finding.
Invoke-RestMethod "$dev/__dev/diagnostics?check=errors&since=15m" -Headers @{ "X-Dev-Token" = $token }

# Then positive: the lines that say the thing you tested happened.
Invoke-RestMethod "$dev/__dev/diagnostics?check=log&since=15m&ch=app" -Headers @{ "X-Dev-Token" = $token }
Invoke-RestMethod "$dev/__dev/diagnostics?check=log&since=15m&ch=audit" -Headers @{ "X-Dev-Token" = $token }

# A failed request: everything it did, by its request id.
Invoke-RestMethod "$dev/__dev/diagnostics?check=log&rid=3f9a1c02" -Headers @{ "X-Dev-Token" = $token }
```

For every criterion, find the line that shows its outcome — `item added`
with the id, `item rejected` with the reason, the audit entry for the write —
and quote it. A criterion whose response looked right but which left no line
is not proven: either the code does not log (a finding for review) or the
code path you think ran did not run.

Any `warn` or `error` in the window is a finding, even if every criterion
passed. Report it with its `rid`. See `policies/logging.md`.

## 6. Post the evidence

Keep it to what proves the result: 600 characters for a small change, 2,000 for
a standard one, as long as it needs for a heavy one or for any failure
(`policies/proportion.md`). One line per criterion, with the number or the
string that settles it. No restating of the criteria, no whole log windows, no
sections that do not apply. A documentation-only Issue deploys nothing: say
"documentation only - nothing deployed, nothing to prove on DEV" and go on.

A small change is validated with whatever rode to DEV with it; one comment can
carry two or three of them, each named with its Issue number.

```markdown
## DEV validation — PASS

**Environment**   https://exceedlimits.site/atlas/landingpage
**Commit**        `a1b2c3d` — probe matches `origin/dev`
**Probe**         environment=development · health=ok · database=reachable ·
                  storage=writable · migration_status=current
**Window**        2026-09-10 14:02Z – 14:11Z

### AC1 — Valid submission stores a row and shows "Thanks"
- `GET /contact` → 200, form present with CSRF token
- `POST /contact` (name="Test User", email="test@example.com", message="Hello")
  → 302 → `/contact/thanks`
- `GET /contact/thanks` → 200, body contains "Thanks"
- `diagnostics?check=db` → `contacts` count 0 → 1
- **PASS**

### AC2 — Empty email shows a field error
- `POST /contact` with `email=""` → 422
- Body contains "Email is required"
- `contacts` count still 1 — no row written
- **PASS**

### AC3 — Submitted content is escaped on display
- `POST /contact` with name `<script>alert(1)</script>` → 302
- `GET /admin/contacts` → 200, renders `&lt;script&gt;alert(1)&lt;/script&gt;`
- No script executed; console clean
- **PASS**

### Regressions
- `GET /` → 200, unchanged
- `GET /about` → 200, unchanged
- Newsletter signup (shares `Validator`) → still rejects empty email with 422

### Log (`check=log&since=10m`)
- `14:04:12 info app "contact stored" {"id":42} rid=3f9a1c02` — AC1's positive event
- `14:04:12 info audit "Insert Contact" {"id":42} rid=3f9a1c02` — the write
- `14:05:01 warn app "contact rejected" {"reason":"email required"} rid=7b2e0c11` — AC2's refusal, by design
- No `error` entries 14:02Z–14:11Z; `log.errors_recent` was 0 before testing
- Browser console: clean; no failed network requests

### Captures (DEV, 1280×900 and 390×844)
| Contact form — before `9f8e7d6` | after `a1b2c3d` |
| --- | --- |
| ![before](captures/31-contact-desktop-before.png) | ![after](captures/31-contact-desktop-after.png) |
| ![before](captures/31-contact-mobile-before.png) | ![after](captures/31-contact-mobile-after.png) |

Home page (`/`), which the change must not touch: before and after identical
by hash.

### Not verified
- Email notification to the site owner — DEV mail is sandboxed. Out of scope
  for this Issue (#33 covers it).

**Result: PASS — 3 of 3 acceptance criteria proven.**
```

Post it, attaching the captures — `gh` uploads each file and rewrites the
reference in the body to the uploaded asset (GitHub CLI 2.99 or later):

```powershell
gh issue comment 31 --body-file validation.md `
  --attach captures/31-contact-desktop-before.png --attach captures/31-contact-desktop-after.png `
  --attach captures/31-contact-mobile-before.png  --attach captures/31-contact-mobile-after.png
```

Sanitise first — no tokens, no session IDs, no real personal data, no full
server paths — and that includes what is visible in the captures.

## 7. Set the label

**PASS** →

```powershell
gh issue edit 31 --add-label needs-review
```

`validated` is set only after `review` also passes. Validation proves it works;
review proves it was built correctly. Both are required.

**FAIL** →

```powershell
gh issue edit 31 --add-label needs-fix --remove-label needs-review
```

Post the same evidence structure with the failing observation stated exactly:

```markdown
### AC2 — Empty email shows a field error
- `POST /contact` with `email=""` → **500 Internal Server Error**
- Expected 422 with "Email is required"
- DEV log: `TypeError: strlen(): Argument #1 must be of type string, null given
  in src/Contact/ContactService.php:34`
- **FAIL**
```

Then run `fix`, targeting exactly that.

## Handling the unverifiable

Some things cannot be proven on DEV — real payment capture, live SMS, a
third-party production API.

- State it under a `Not verified` heading.
- Say what you verified instead.
- If an **acceptance criterion** is unverifiable, the Issue cannot be
  `validated`. Label `needs-human` and say exactly what a person must check.

Never mark a criterion PASS because it "should" work.

## Never

- Validate against local WAMP
- Validate without matching the probe commit
- Report a criterion as passing without an observation
- Skip regression checks because the change was small
- Omit an error you saw because the criterion still passed
- Paste a token, session ID, or real personal data into the evidence
- Report a criterion as passing without the log line that shows it happened
- Retry a failed deployment more than once without diagnosing it
- Post a capture you have not looked at, or one without its commit
- Report a visual criterion as passing with no capture and no stated reason
