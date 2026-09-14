---
name: peer-review
description: Review a review — judge whether another agent's PASS or FAIL verdict was correct. Use when a verdict is disputed, when a PASS looks thin, before a high-risk production release, or when calibrating a new model in the review role.
---

# Skill: peer-review

Reviews the **review**, not the code — although you must read the code to judge
the review.

Use when:

- A builder disputes a FAIL
- A PASS looks thin for the size or risk of the change
- The change is high-risk: auth, payments, personal data, schema
- A new model is being calibrated in the review role
- Two reviews disagree

Prefer a third model, or at minimum a session that produced neither the code nor
the review.

## 1. Gather

```powershell
gh issue view 31 --json body,comments,labels
gh pr view 52 --json body,files,additions,deletions
gh pr diff 52
gh pr view 52 --json reviews
```

You need: the Issue and its comments, the diff, the validation evidence, the
review verdict, and any dispute.

Read the review **before** forming your own opinion of the code — you are
judging the review's reasoning, and you cannot assess reasoning you have already
replaced with your own.

Then form your own view independently, and compare.

## 2. Assess the review

### Coverage

`policies/review.md` requires twelve checks. Which did the review actually
perform? A review that says "looks good" has performed none.

| Check | Evidenced in the review? |
| --- | --- |
| Requirement completeness | |
| Correctness | |
| Regressions | |
| Architecture | |
| Security | |
| Unnecessary complexity | |
| Code quality | |
| Test coverage | |
| Database | |
| Production risk | |
| Logging | |
| Documentation and history | |

A PASS that skipped the security check is not a valid PASS, whatever the code
turned out to be.

### Findings

For each finding the review raised:

- **Real?** Reproduce it. A finding that does not reproduce is a false positive
  and wasted a repair cycle.
- **Correctly located?** Right file, right line?
- **Correctly diagnosed?** Is the stated cause the actual cause?
- **Correctly classified?** Blocking versus non-blocking. Style opinions
  classified as blocking are a calibration error. A security defect classified
  as non-blocking is a serious one.
- **Actionable?** Does it say what would fix it?

### Misses

The harder half. Read the diff yourself and look for what the review did not
mention:

- An unmet acceptance criterion
- A security defect
- A regression in a shared component
- An irreversible migration
- Out-of-scope changes
- Validation evidence with no observations

A missed security defect in a PASS is the most serious outcome peer review
exists to catch.

### Validation trust

Did the review verify the validation evidence, or take it on trust? Check the
evidence yourself:

- Does the probe commit match the merged commit?
- Are the observations concrete, or assertions?
- Spot-check one criterion against DEV directly.

## 3. Verdict

### Review upheld

```markdown
## Peer review — verdict UPHELD

Peer reviewer: kimi-cli
Reviewing: codex-cli's FAIL on PR #52

All 3 blocking findings reproduce:

1. **AC2 / 500 error** — confirmed on DEV at `a1b2c3d`. `POST /contact` with
   `email=""` returns 500. Diagnosis correct: `ContactService.php:34`
   dereferences before validation.
2. **SQL injection** — confirmed at `ContactRepository.php:47`. Reproduced with
   `email=' OR '1'='1`, which returned all rows.
3. **Out-of-scope `slugify()`** — confirmed, 6 other call sites via
   `git grep slugify`.

Coverage: all 12 checks evidenced.

**Additional finding the review missed (blocking):**
`resources/views/pages/admin-contacts.php:23` renders `<?= $contact->name ?>`
unescaped. AC3 passed only because the tested payload was escaped at insert,
not at output. A payload containing a quote still breaks out. This is the same
defect class as finding 2 and should be fixed in the same repair.

FAIL is correct, and the fix list is now 4 items.
```

### Review overturned

```markdown
## Peer review — verdict OVERTURNED

Peer reviewer: kimi-cli
Reviewing: codex-cli's FAIL on PR #52

**Finding 1 does not reproduce.**
The review reports `POST /contact` with `email=""` returning 500 at `a1b2c3d`.
I tested the same endpoint at the same commit: it returns 422 with
"Email is required".

The review appears to have tested at `9f8e7d6` — the commit before the fix
merged. The probe output quoted in the review shows `9f8e7d6`, not `a1b2c3d`.

**Findings 2 and 3 stand.** Both reproduce and are correctly diagnosed.

**Corrected verdict: FAIL**, on findings 2 and 3 only. Finding 1 should be
withdrawn — repairing it would change working code.

**Process note:** the commit mismatch was visible in the review's own quoted
probe output. `validate-dev` step 2 requires asserting the probe commit before
testing. Worth reinforcing.
```

### Review inadequate

```markdown
## Peer review — verdict INADEQUATE

Peer reviewer: kimi-cli
Reviewing: codex-cli's PASS on PR #52

The PASS is not supported.

**Coverage:** 3 of 12 checks evidenced. No security check, no regression
analysis, no database review, no scope check — on a 340-line diff adding a
public form and a table.

**Missed, blocking:**
1. **SQL injection** — `ContactRepository.php:47`, `$email` concatenated.
   Reproduced with `' OR '1'='1`.
2. **Migration has no `down()`** — required by `policies/database.md`.
3. **Out of scope** — `src/Support/Str.php` rewritten, 6 other call sites,
   unrelated to #31.

**Validation not verified:** the review accepted a validation comment containing
no observations — three ticked boxes and the words "all criteria met".

**Corrected verdict: FAIL** on the three findings above.

Issue returned to `needs-fix`. The validation also needs redoing with real
evidence.
```

## 4. Apply the outcome

```powershell
gh issue comment 31 --body-file peer-review.md
```

| Verdict | Action |
| --- | --- |
| Upheld (FAIL) | Label stays `needs-fix`. Add any findings you found. |
| Upheld (PASS) | Label stays `validated`. |
| Overturned to PASS | `gh issue edit 31 --add-label validated --remove-label needs-fix` |
| Overturned to FAIL | `gh issue edit 31 --add-label needs-fix --remove-label validated` |
| Inadequate | Set the corrected label; state what the review must redo. |

A peer review does not consume a repair attempt. It corrects the record.

## 5. Calibration notes

When a pattern emerges across peer reviews, record it in `DECISIONS.md`:

```markdown
## 2026-09-10 — Reviewers must assert the probe commit before testing

Two FAIL verdicts in a fortnight tested the wrong commit, both times because the
reviewer hit DEV without checking the probe against the merged SHA. Cost three
unnecessary repair cycles.

`validate-dev` and `review` both now require quoting the probe commit and the
merged commit side by side in the verdict.
```

That is how the system improves. A peer review that only fixes one Issue has
done half its job.

## Never

- Peer review a review of your own code
- Re-review the code from scratch and ignore the review's reasoning
- Overturn a finding without reproducing the disagreement
- Uphold a PASS whose checks were not performed
- Treat a style disagreement as a calibration error — reviewers are allowed
  judgement within policy
