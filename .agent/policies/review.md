# Policy: Review

Centrally managed by ATLAS.

## Independence

Review must be independent of implementation: the reviewer must not carry the
builder's reasoning. In order of preference:

1. **A different model.** Claude builds, Codex reviews. Codex builds, Kimi
   reviews. This catches the largest class of blind spots.
2. **A fresh context of the same model** — a new session, or a subagent the
   building session starts with nothing but the Issue number, the PR number
   and the instruction to run `review`. It reads the Issue, the diff, the
   documents and DEV for itself, exactly as a new session would. This is what
   lets one prompt carry an Issue from claim to `validated`.
3. **The same context** — only permitted for trivial changes (typo, copy edit,
   comment) and must be declared as such in the review comment.

A reviewer states which of the three it was, in the first line of the verdict.

A reviewer must not modify the code. Reviewers read the diff, the Issue, and the
DEV environment, and produce a verdict. Fixes go back to the builder via the
`fix` skill.

## Review inputs

The reviewer reads all of:

- The Issue body **and every comment**
- The acceptance criteria
- The full diff (`gh pr diff <n>`)
- `ARCHITECTURE.md` and `DECISIONS.md`
- `.agent/framework/RULES.md`
- The applicable policies
- The validation evidence already posted, captures included
- The DEV environment itself, at the commit under review

A review that only reads the diff is incomplete. The diff cannot tell you
whether the requirement was met.

For a visual criterion the reviewer looks at the posted before/after pair —
right screen, right commits, a real change where one was expected, none where
none was — and takes its own *after* capture of DEV to compare with it
(`policies/testing.md`, "Captures"). A visual criterion with no capture and no
stated reason is unproven.

## What review examines

Work through all twelve. Do not skip a heading because the diff looks small.

**1. Requirement completeness**
Does the change satisfy every acceptance criterion? Is anything only partially
done? Did the Issue's comments add a requirement the implementation missed?

**2. Correctness**
Walk the logic. Off-by-one, null handling, empty collections, boundary values,
timezone and date handling, integer/float conversion, encoding. Trace at least
one failure path fully, not just the happy path.

**3. Regressions**
What else calls this code? What shares the changed function, template, route,
or table? Was a shared component altered for one caller's benefit?

**4. Architecture**
Does the change fit the documented architecture, or does it quietly introduce a
new pattern? Is logic in the right layer? Does it create a circular or
inappropriate dependency?

**5. Security**
Run the checklist in `policies/security.md` section "Reviewing for security".
Every review, without exception.

**6. Unnecessary complexity**
Is there an abstraction with one caller? A configuration option nobody asked
for? A layer that only forwards? Could this be materially shorter and clearer?

**7. Code quality**
Naming, error handling, dead code, commented-out code, debug output, `TODO`
without an Issue number, comment density inconsistent with the file.

**8. Test coverage**
Does a bug fix have a test that fails without the fix? Does new branching logic
have tests? Are the tests asserting behavior or asserting mocks?

**9. Database effects**
Is a migration reversible? Correctly ordered? Does the change introduce an
unindexed filter, an N+1 query, or an unbounded result set?

**10. Production risk**
What happens if this is wrong in production? Is the blast radius bounded? Is
there a rollback? Does it touch payments, auth, permissions, or personal data?

**11. Logging**
Does every new handler, job, action, or write record its outcome — success and
refusal both — on the right channel, at the right level, with the ids involved
and nothing sensitive in the context? Is the log how this change would be
diagnosed at three in the morning? A change that leaves no trace fails. See
`policies/logging.md`.

**12. Documentation and history**
`HISTORY.md` has an entry for this change — what changed, where, why, what is
now true, where the evidence is. Every description document the change
touched — `PRODUCT.md`, `ARCHITECTURE.md` and its Map, `PLAN.md`, `docs/*` —
was rewritten to describe the present: no appended "update" paragraph, no
sentence that is no longer true, nothing the Map cannot locate. A change that
leaves the documents stale fails: the next agent reads them instead of the
code. See `policies/documentation.md`.

## Scope check

Reject any diff that contains changes unrelated to the Issue — even improvements.
Unrelated changes make review unreliable and rollback dangerous. The correct
response is a new Issue.

## Verdict

The review ends in exactly one of two words.

### PASS

```markdown
## Review — PASS

Reviewer: codex-cli (fresh session)
Commit reviewed: abc1234
PR: #52

All 3 acceptance criteria satisfied and evidenced on DEV.
Scope is clean — 4 files, all related to the Issue.
Security checklist: no findings.
Tests: 2 added, both fail without the fix.
Logging: both handlers record their outcome; nothing sensitive in context.
History: entry present; ARCHITECTURE Map and data model rewritten.

Notes (non-blocking):
- `ContactController::store` is now 45 lines. Worth extracting validation later.
  Not blocking; filed as #78.
```

### FAIL

```markdown
## Review — FAIL

Reviewer: codex-cli (fresh session)
Commit reviewed: abc1234
PR: #52

### Blocking

1. **AC2 not met** — `src/Http/ContactController.php:61`
   Empty email returns 500, not a field error. The validator runs after
   `$data['email']` is dereferenced.
   Expected: 422 with "Email is required".
   Fix: move the validation call above line 58.

2. **SQL injection** — `src/Repository/ContactRepository.php:34`
   `$email` is concatenated into the WHERE clause.
   Fix: use a bound parameter.

3. **Unrelated change** — `src/Support/Str.php`
   `slugify()` was rewritten. Not referenced by this Issue and used in 6 other
   places. Revert it here; open a separate Issue if the change is wanted.

### Non-blocking

4. `ContactController` imports `Carbon` but does not use it.
```

Every blocking finding must have: the file and line, what is wrong, what was
expected, and what would fix it. "Improve error handling" is not a finding.

## After the verdict

**PASS** → the reviewer labels the Issue `validated` (if DEV validation already
passed) and leaves the PR ready to merge. The reviewer does not merge unless
project policy grants it.

**FAIL** → the reviewer labels `needs-fix`, removes `needs-review`, and posts
the findings. The builder runs the `fix` skill against exactly those findings —
no more, no less.

## Repair limit

Three review-fix cycles on one Issue is the limit. On the third FAIL:

- Comment summarising all three attempts and why each failed.
- Label `needs-human`. Remove `needs-fix`.
- Stop.

Repeated failure means the Issue is wrong, the architecture is wrong, or the
task needs a person. It never means "try harder".

## Reviewing another agent's review

A human may ask for `peer-review` — a second reviewer checking the first
review's judgement rather than the code. Useful when a FAIL is disputed or a
PASS looks thin. See `skills/peer-review/SKILL.md`.
