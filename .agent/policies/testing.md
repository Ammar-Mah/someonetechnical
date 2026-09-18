# Policy: Testing and Validation

Centrally managed by ATLAS.

## The governing rule

> Rules are not evidence. Passing locally is not validation.

Remote DEV is the only authority. An agent may only report a behavior as working
if it observed that behavior on remote DEV, at the commit under test.

## Three distinct activities

| Activity | Where | Proves |
| --- | --- | --- |
| **Checks** | GitHub Actions, on the PR | The code is syntactically valid, formatted, and unit-tested |
| **Local run** | WAMP | Fast iteration while writing. Proves nothing. |
| **Validation** | Remote DEV | The change actually works in a real environment |

An Issue reaches `validated` on the strength of the third only.

## Validation gate

Before validating anything, confirm the environment is the one you think it is:

1. `GET <urls.dev>/__dev/probe` returns HTTP 200 — `urls.dev` in `.agent/project.json`, always `https://<domain>/atlas/<project>`.
2. `environment` is `development`.
3. `git_commit` equals the commit you expect on DEV.
4. `health` is `ok`.
5. `database` is `reachable`.
6. `storage` is `writable`.
7. `migration_status` is `current` when the change touched the schema. A
   framework without a migrator reports `not-applicable`; then confirm the
   schema through `/__dev/diagnostics?check=db` instead.
8. `log.enabled` and `log.writable` are true, `log.last_entry_at` is recent,
   and `log.errors_recent` is `0` — the probe reports the log's own state. A
   deployment that booted with errors is not a baseline; read them with
   `/__dev/diagnostics?check=errors&since=15m` before testing anything.

If the commit does not match, **stop**. You are testing old code. Check the
GitHub Actions run, wait for it, or investigate the deployment. Reporting
results from the wrong commit is the single most damaging mistake an agent can
make in this system.

## What validation must cover

For every Issue:

1. **Each acceptance criterion**, individually, with the observed result.
2. **The happy path**, end to end.
3. **Failure paths** — invalid input, missing input, wrong type, oversized
   input, unauthorised access.
4. **Regressions** — the behavior adjacent to what you changed. If you touched
   the login form, check registration and password reset.
5. **Data effects** — if the change writes, confirm the row/file/record exists
   and holds the right values; if it deletes, confirm the right thing went.
6. **The log** — the primary evidence. For the test window: the positive lines
   you expected (handler outcome, audit entry, request summary) are present,
   and no `warn` or `error` entry appeared. Quote them with their request ids.
   Then the browser console. See `policies/logging.md`.

For UI changes, additionally:

7. Rendering at mobile (375px), tablet (768px), and desktop (1280px) widths.
8. Keyboard reachability of any new interactive control.
9. No new console errors and no failed network requests.
10. **Captures** — what the user sees, which the log cannot show: each changed
    screen photographed on DEV before the change and after it, at desktop and
    mobile widths, posted with the evidence. See "Captures" below.

For API changes, additionally:

11. Status codes for success, validation failure, auth failure, and not-found.
12. Response shape matches the documented contract.
13. Backwards compatibility for existing consumers, or an explicit note that the
    contract changed.

## Evidence format

Record every criterion like this:

```markdown
### Validation — commit `abc1234` on https://exceedlimits.site/atlas/landingpage

**AC1: Submitting a valid form stores a row and shows "Thanks"**
- POST /contact with valid fields → 302 to /contact/thanks
- /contact/thanks rendered, contains "Thanks"
- /__dev/diagnostics?check=db reports contact_submissions count 0 → 1
- PASS

**AC2: Submitting an empty email shows a field error**
- POST /contact with email="" → 422, body contains "Email is required"
- No row added (count still 1)
- PASS

**Regressions checked**
- GET / → 200, unchanged
- GET /about → 200, unchanged
- Newsletter signup (shares the validator) → still rejects empty email

**Log** (`diagnostics?check=log&since=10m&ch=app`)
- `14:04:12 info app "contact stored" {"id":42} rid=3f9a1c02` — AC1's positive event
- `14:05:01 warn app "contact rejected" {"reason":"email required"}` — AC2's refusal, as designed
- `14:04:12 info audit "Insert Contact" {"id":42}` — the write
- No `error` entries between 14:02Z and 14:09Z
- Browser console: clean

**Captures** (DEV, 1280×900 and 390×844)
| Contact form — before `9f8e7d6` | after `abc1234` |
| --- | --- |
| ![before](captures/31-contact-desktop-before.png) | ![after](captures/31-contact-desktop-after.png) |
| ![before](captures/31-contact-mobile-before.png) | ![after](captures/31-contact-mobile-after.png) |

**Result: PASS**
```

A validation comment without per-criterion observations is not a validation.
Reviewers must reject it.

## Captures

The log shows what ran. It cannot show what the user saw. A criterion about
what the user sees — a screen, a layout, a state, a message — is proven with a
**capture**: a screenshot of DEV taken **before** the change and **after** it,
at the commits the probe reported, posted side by side with the rest of the
evidence. The human who approves a release sees the change in seconds; the
reviewer judges a visual criterion without driving DEV again.

Nothing is installed for this. The browser already on every Windows machine
takes the picture headless, and `gh` — already required — uploads it.

### When

- Every Issue whose acceptance criteria describe something a user sees.
- A `fix/*` Issue about a visual defect: the *before* is the reproduction.
- Not for API, job, schema or refactoring work. There the log is the whole
  proof, and a capture would be decoration.

### What

| Capture | Taken | Of |
| --- | --- | --- |
| **before** | In `build`, immediately before the merge into `dev` — DEV still runs the previous commit, and after the merge that state is gone | Each screen the criteria name, at its entry URL, plus the adjacent screen the change must not touch |
| **after** | In `validate-dev`, once the probe reports the new commit | The same screens, same URLs |

Two fixed viewports: **desktop 1280×900** and **mobile 390×844**. Fixed sizes
make every pair the same height, so the two images align in a table with no
processing. A long page is captured at a taller window, not stitched.

A new screen has no before: post the after alone, labelled **Preview**.

### How

```powershell
$dev = (Get-Content .agent/project.json -Raw | ConvertFrom-Json).urls.dev
atlas capture "$dev/contact" captures/31-contact-desktop-after.png
atlas capture "$dev/contact" captures/31-contact-mobile-after.png -Width 390 -Height 844 -Mobile
```

`atlas capture` drives the browser already on the machine through its devtools
protocol, in a profile of its own — never your browser session — and prints the
width the page reported:

```
[ok]   ...captures\31-contact-mobile-after.png - viewport 390x844, page reported innerWidth 390, scrollWidth 390, scrollHeight 844, 29 KB
```

Quote that line in the evidence: it is the proof the picture is a 390px layout.
The command refuses to leave a file whose layout width is not the one asked
for, and warns when `scrollWidth` exceeds the viewport - the page overflows
sideways at that size.

Never capture with the browser's own `--window-size ... --screenshot`.
Headless Chromium will not make a window narrower than about 492px, so it lays
the page out wide and crops the image to the size you asked for: the file looks
right and the layout in it is wrong.

Files are named `captures/<issue>-<screen>-<desktop|mobile>-<before|after>.png`
— no spaces — and live in `captures/` at the root of the worktree. That
directory is git-ignored, never committed, never deployed, and disappears with
the worktree; GitHub keeps what was posted.

### Look before you post

Open every file — an agent whose file reader shows images opens the PNG
directly; otherwise open it in the browser you have — and reject it if it
shows:

- a login page, an error page, a blank or half-loaded frame;
- an **unstyled** page — the application's base URL is wrong for that host,
  not the CSS;
- the wrong screen or the wrong state;
- **before and after identical** where the criterion expected a change. Two
  files with the same `Get-FileHash` are one picture: the change did not
  land, or you captured the wrong thing. Conversely, a screen the change
  should *not* have touched, identical before and after, is a regression
  check passed.

A capture you have not looked at is not evidence.

### Post

Reference the files in the evidence and attach them; `gh` uploads each one
and rewrites the reference to the uploaded asset (GitHub CLI 2.99 or later —
`atlas check` verifies):

```powershell
gh issue comment 31 --body-file validation.md `
  --attach captures/31-contact-desktop-before.png --attach captures/31-contact-desktop-after.png `
  --attach captures/31-contact-mobile-before.png  --attach captures/31-contact-mobile-after.png
```

Label every image with the commit it was taken at. A picture without its
commit proves nothing — the same rule as the probe gate.

Captures are sanitised like any other output: no token in a URL, no session
identifier, no real personal data on screen. DEV holds test data; if a screen
shows anything else, do not post it.

### Limits, stated rather than worked around

- A one-shot capture carries no session. It shows what a signed-out visitor
  sees, or what a starter shows while it signs everyone in. A screen behind a
  real login is captured with the browser you have, if it can save a file;
  otherwise the criterion is proven the usual way and the evidence says
  **not captured — behind login**.
- A screen reached only by interaction, with no entry URL, is the same case.
  Give screens entry URLs when you can; it is what makes them capturable.
- The reviewer may not have your files. It takes its own *after* with the
  same command and compares it with what you posted.

## Automated tests

Write a test when:

- The change fixes a bug — write the test that would have caught it.
- The change adds logic with branches, parsing, calculation, or state.
- The change touches money, permissions, dates, or data integrity.

Do not write a test when:

- It only asserts that the framework works.
- It only asserts that a getter returns what the setter set.
- It requires mocking so much that the test asserts the mocks.

Tests run in `checks.yml` on every PR. A failing test blocks the merge. Never
delete or skip a failing test to go green — fix the code, or if the test is
genuinely wrong, fix the test and say so explicitly in the PR.

## When validation cannot be done

Some things cannot be verified on DEV: real payment capture, real SMS delivery,
third-party production APIs. When you hit one:

- Say so explicitly in the validation comment, under a heading `Not verified`.
- State what you did verify instead (for example, the sandbox response).
- Do not mark the Issue `validated` if an acceptance criterion is unverifiable.
  Label `needs-human` and explain what a person must check manually.

## Failure handling

How much evidence a result carries is `policies/proportion.md`: at most 600
characters for a small change, 2,000 for a standard one, as long as it needs
for a heavy one - and no cap at all on a failure. A documentation-only change
deploys nothing and proves nothing on DEV: say that in one line and go on.

A FAIL result means:

1. Comment on the Issue with the full evidence, including the failing
   observation.
2. Label `needs-fix`, remove `needs-review`.
3. Hand to the `fix` skill.

Track the attempt count. When the third repair of the same Issue fails -
in validation or in review - escalate per `AGENTS.md` section 9. Do not start
a fourth.
