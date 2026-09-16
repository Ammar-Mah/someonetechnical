---
name: review
description: Independently review a pull request against its Issue, the architecture, and the policies, and return a PASS or FAIL verdict with exact findings. Use on any Issue labelled needs-review that you did not implement yourself. Prefer a fresh session or a different model.
---

# Skill: review

Produces a verdict on somebody else's work. The reviewer does not fix the code —
it reads, judges, and reports.

## Independence

Check before starting:

- **Best:** a different model reviews (Claude builds, Codex reviews).
- **Acceptable:** a fresh context of the same model — a new session, or a
  subagent started with only the Issue and PR numbers, which reads everything
  itself.
- **Only for trivial changes:** the same context, declared as such.

Say which you are, in the first line of the verdict.

If you wrote this code in the context you are reading from, do not review it
here. Start a subagent with nothing but the numbers and this instruction —

```
Run the review skill on Issue #31, PR #52. You did not write this change.
Read the Issue and every comment, the diff, the documents, the validation
evidence and DEV itself, and post the verdict.
```

— or, if you cannot start one, stop and say so:

```
I implemented #31 in this context and cannot start a subagent. Reviewing my own
work would not be independent. Leaving it labelled needs-review for another
session or model.
```

## 1. Gather everything

```powershell
gh pr view 52 --json number,title,body,headRefName,files,additions,deletions
gh pr diff 52
gh issue view 31 --json body,labels,comments
gh pr checks 52
```

Read all of:

- The Issue body and **every comment**
- The acceptance criteria
- The complete diff
- `ARCHITECTURE.md`, `DECISIONS.md`
- `.agent/framework/RULES.md`
- The DEV validation evidence already posted, captures included
- The DEV environment itself, at the commit under review

Reviewing the diff alone is not a review. The diff cannot tell you whether the
requirement was met, or what else calls the code that changed.

## 2. Verify the validation actually happened

Before assessing the code, check the validation evidence is real:

- Is there a validation comment with per-criterion observations?
- Does the probe commit in that evidence match the merged commit?
- Are the observations concrete, or are they assertions like "works correctly"?
- Were regressions checked?
- Does the evidence quote the log — the positive lines for each criterion and
  a clean error window — or only responses?
- For a criterion about what the user sees: is there a before/after pair of
  the right screen, labelled with the right commits — the before from DEV
  before the merge, the after at the merged commit? Do they differ where a
  change was expected and match where none was? Is the frame a real page, not
  a login, an error or an unstyled shell? A missing capture with no stated
  reason is a missing observation.

A validation comment without observations is not a validation. FAIL on that
alone and say so.

Spot-check it. Hit DEV yourself for at least one criterion. Validators make
mistakes, and a review that trusts a bad validation propagates it. For a
visual criterion the spot-check is your own *after* capture, taken with the
command in `policies/testing.md` ("Captures") and compared with the posted
one — look at both.

## 3. Work the twelve checks

All twelve, every time. Do not skip a heading because the diff is small.

**1. Requirement completeness**
Every acceptance criterion, from body and comments. Anything partially done?

**2. Correctness**
Trace the logic. Null handling, empty collections, off-by-one, boundaries,
dates and timezones, numeric conversion, encoding. Follow at least one failure
path all the way through, not just the happy path.

**3. Regressions**
What else calls this? Search:

```powershell
git grep -n "changedFunction"
git grep -n "changed_table"
```

Was a shared component changed to suit one caller?

**4. Architecture**
Does it fit the documented architecture, or quietly introduce a new pattern? Is
logic in the right layer? Any inappropriate or circular dependency?

**5. Security**
Run the checklist in `policies/security.md` — "Reviewing for security". Every
review. No exceptions.

**6. Unnecessary complexity**
An abstraction with one caller? A config option nobody asked for? A layer that
only forwards? Could this be materially shorter?

**7. Code quality**
Naming, error handling, dead code, commented-out code, debug output, `TODO`
without an Issue number, comment density out of keeping with the file.

**8. Test coverage**
Does the bug fix have a test that fails without the fix? Verify that claim — do
not take it on trust. Do the tests assert behavior or assert mocks?

**9. Database**
Migration reversible? Correctly ordered? Unindexed filter, N+1, unbounded
result set?

**10. Production risk**
What if this is wrong in production? Is the blast radius bounded? Rollback
available? Does it touch auth, payments, permissions, or personal data?

**11. Logging**
Every new handler, job, action, or write records its outcome — success and
refusal both — on the right channel, at the right level, with the ids
involved. Nothing sensitive in any context array. Read the DEV log for the
validation window yourself: the positive lines are there, no `error` is. A
change that leaves no trace fails. See `policies/logging.md`.

**12. Documentation and history**
`HISTORY.md` has an entry for this change that says what changed, where, why,
what is now true and where the evidence is. Every description document the
change touched — `PRODUCT.md`, `ARCHITECTURE.md` and its Map, `PLAN.md`,
`docs/*` — was rewritten to describe the present: no appended "update"
paragraph, no sentence that is no longer true. Open the Map and check it
locates every file in the diff. A change that leaves the documents stale
fails; the next agent reads them instead of the code. See
`policies/documentation.md`.

## 4. Check the scope

```powershell
gh pr diff 52 --name-only
```

Every file must be explicable by the Issue. An unrelated change fails review
even when it is an improvement — it makes review unreliable and rollback
dangerous.

## 5. Verdict

Exactly one of two words.

### PASS

```markdown
## Review — PASS

Reviewer: codex-cli, fresh session
PR: #52 · Commit: `a1b2c3d` · Issue: #31

**Requirements** All 3 acceptance criteria met and evidenced on DEV.
Spot-checked AC2 independently: `POST /contact` with `email=""` → 422,
"Email is required". Confirmed.

**Scope** 9 files, all explicable by the Issue. Clean.

**Security** Parameterised queries throughout. Output escaped with `e()` in both
views. CSRF token present. No findings.

**Tests** 4 cases. Removing the validation call makes 2 fail — verified.

**Logging** Both handlers write their outcome on `app`; the audit hook covers
the writes. DEV log for the validation window: the two positive lines present,
no errors.

**Documents** HISTORY entry present and accurate. ARCHITECTURE Map and data
model rewritten; PLAN milestone updated; PRODUCT untouched, correctly.

**Database** `0007_create_contacts.php` reverses cleanly; ran down and up on a
local copy. `email` indexed.

**Notes (non-blocking)**
- `ContactService::submit` is 38 lines and does validation plus persistence.
  Worth splitting when the second caller arrives. Not now. Filed #78.
```

### FAIL

```markdown
## Review — FAIL

Reviewer: codex-cli, fresh session
PR: #52 · Commit: `a1b2c3d` · Issue: #31

### Blocking

**1. AC2 not met — `src/Contact/ContactService.php:34`**
`$data['email']` is dereferenced before validation runs, so an empty email is a
500, not a field error.
Expected: 422 with "Email is required".
Fix: move the `$this->validator->check()` call above line 31.
Note: the validation evidence claims this passes. It does not — I reproduced the
500 on DEV at `a1b2c3d`.

**2. SQL injection — `src/Contact/ContactRepository.php:47`**
```php
$sql = "SELECT * FROM contacts WHERE email = '{$email}'";
```
User input concatenated into SQL. Also `SELECT *`.
Fix: `'SELECT id, name, email, created_at FROM contacts WHERE email = ?'` with a
bound parameter.

**3. Unrelated change — `src/Support/Str.php:12-38`**
`slugify()` was rewritten. Not referenced by #31 and used in 6 other places.
Fix: revert here. Open a separate Issue if the change is wanted.

**4. Migration is not reversible — `migrations/0007_create_contacts.php`**
No `down()`. Required by `policies/database.md`.

### Non-blocking

**5.** `ContactController` imports `Carbon`, unused. Remove.

### Assessment
Finding 2 is a security defect and finding 1 means the feature does not work.
The validation evidence for AC2 was incorrect — worth checking how that was
recorded.
```

Every blocking finding needs: file and line, what is wrong, what was expected,
and what would fix it. "Improve error handling" is not a finding.

## 6. Record the verdict

**PASS:**

```powershell
gh pr review 52 --approve --body-file review.md
gh issue comment 31 --body-file review.md
gh issue edit 31 --add-label validated --remove-label needs-review
```

`validated` requires **both** a passing `validate-dev` and a passing review. If
DEV validation has not passed, do not apply it.

**FAIL:**

```powershell
gh pr review 52 --request-changes --body-file review.md
gh issue comment 31 --body-file review.md
gh issue edit 31 --add-label needs-fix --remove-label needs-review
```

Do not merge. Do not fix it yourself. Hand it to `fix`.

## 7. Repair limit

Count the FAIL verdicts on this Issue. On the third:

```powershell
gh issue edit 31 --add-label needs-human --remove-label needs-fix
```

Comment summarising all three cycles and what each failed on. Stop.

## Calibration

Be exacting about: correctness, security, scope creep, missing requirements,
irreversible migrations, and unverified claims.

Do not block on: style preference the project does not share, a refactor you
would have done differently, missing tests for trivial code, or an abstraction
you would have designed another way. Those are non-blocking notes, or new
Issues.

A review that finds nothing on a large diff is usually a review that was not
done. A review that blocks on twelve stylistic points is noise. Aim for the
findings that matter.

## Never

- Review your own work from the same session
- Modify the code you are reviewing
- Pass a change whose validation evidence has no observations
- Skip the security checklist
- Merge as part of a review, unless project policy grants it
- Give a verdict without reading the Issue comments
