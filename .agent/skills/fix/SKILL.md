---
name: fix
description: Repair exactly the findings from a failed review or a failed DEV validation, then re-validate. Use on an Issue labelled needs-fix. Enforces the three-attempt limit and escalates rather than looping.
---

# Skill: fix

Repairs the specific findings from a FAIL. Nothing else.

`fix` is not "have another go". It addresses a known, listed set of findings.

## 1. Count the attempt

```powershell
gh issue view 31 --json comments
```

Count previous FAIL verdicts and repair attempts.

| Attempts so far | Action |
| --- | --- |
| 0, 1 | Proceed |
| 2 | Proceed — this is the last one. Say so in your comment. |
| 3 | **Stop. Escalate.** Jump to *Escalation*. |

Do not start a fourth attempt. Ever. Three failures means the Issue, the
architecture, or the approach is wrong — not that you should try harder.

## 2. Read the findings

```powershell
gh issue view 31 --json body,comments
```

Extract the exact findings — the FAIL comment from `review` or `validate-dev`.
List them explicitly before starting:

```
Findings to fix:
1. ContactService.php:34 — email dereferenced before validation → 500 not 422
2. ContactRepository.php:47 — SQL injection, concatenated $email
3. Str.php:12-38 — unrelated slugify() rewrite, revert
4. 0007_create_contacts.php — missing down()
5. (non-blocking) unused Carbon import
```

If a finding is unclear, ask on the Issue rather than guessing what was meant.

## 3. Return to the worktree

```powershell
cd C:\wamp64\www\<project>\worktrees\issue-31-claude
git rev-parse --abbrev-ref HEAD
git fetch origin
git rebase origin/dev
```

If the branch was already merged and deleted, create a new one from `origin/dev`
via `create-worktree`, using the same Issue number with a `-fix` suffix on the
slug.

## 4. Understand the root cause

Start from the log. The FAIL comment carries a request id for every failure it
observed; that id finds the exact lines:

```powershell
$dev   = (Get-Content .agent/project.json -Raw | ConvertFrom-Json).urls.dev
$token = atlas token
Invoke-RestMethod "$dev/__dev/diagnostics?check=log&rid=3f9a1c02" -Headers @{ "X-Dev-Token" = $token }
```

Read what ran before the failure, in order, with timing. The root cause is
usually visible there before you open a single file. See `policies/logging.md`.

For each finding, before changing anything, answer: **why did this happen?**

| Finding | Symptom | Root cause |
| --- | --- | --- |
| 500 instead of 422 | Empty email crashes | Validation runs after the value is used — ordering error in `submit()` |

Fix the cause, not the symptom. Wrapping the crash in a `try/catch` to turn a
500 into a 422 would satisfy the test and leave the defect in place. That is the
most common failure mode of an agent repair loop.

If you cannot explain why a finding occurred, you cannot fix it correctly. Say
so and escalate.

## 5. Fix, one finding at a time

Address them in order. Commit each separately:

```powershell
git add src/Contact/ContactService.php
git commit -m "fix(contact): validate before dereferencing email

Validation ran after \$data['email'] was read, so an empty email
raised a TypeError instead of a field error. Moved the check above
the first use.

Refs #31
Agent: claude-code"
```

Separate commits make it verifiable that each finding was addressed.

### Reverting an out-of-scope change

For a "revert this unrelated change" finding:

```powershell
git checkout origin/dev -- src/Support/Str.php
git commit -m "revert(support): restore slugify to origin/dev state

Rewritten out of scope for #31 and used in 6 other call sites.
Filed separately as #79.

Refs #31"
```

Then file the Issue you promised.

### Scope discipline still applies

Fix the findings. Do not:

- Refactor while you are in there
- Fix an unrelated problem you notice
- Improve something a finding did not mention
- Rewrite a component to avoid the finding

A repair diff that touches files no finding named will fail review again.

## 6. Prove each fix locally

For every finding, exercise the exact failing case:

```powershell
php -l src/Contact/ContactService.php
php tests/run.php contact          # the framework's test command — see .agent/framework/RULES.md
```

Add a test that fails without the fix, for every behavioral finding. That is
what stops a third attempt.

For the migration finding, run down and up again to prove it reverses.

## 7. Self-check

```powershell
git diff origin/dev...HEAD
```

- [ ] Every blocking finding addressed
- [ ] Nothing changed that no finding named
- [ ] Root causes fixed, not symptoms
- [ ] A test covers each behavioral finding
- [ ] No secrets, no debug output
- [ ] The original acceptance criteria still satisfied

That last one matters: a fix that breaks a previously-passing criterion is a
new failure.

## 8. Push and re-open the loop

```powershell
git push
gh issue comment 31 --body-file fix.md
gh issue edit 31 --add-label needs-review --remove-label needs-fix
```

Once the repair is on DEV, `validate-dev` takes fresh **after** captures; the
befores already on the Issue stand. A finding about a screen is answered with
a picture of the repaired screen, not a sentence.

Comment:

```markdown
## Repair attempt 1 of 3

| # | Finding | Fix | Commit |
| --- | --- | --- | --- |
| 1 | Email dereferenced before validation | Moved `check()` above first use. Test `testEmptyEmailReturns422` fails without it. | `b2c3d4e` |
| 2 | SQL injection in `findByEmail` | Bound parameter, named columns. | `c3d4e5f` |
| 3 | Unrelated `slugify()` rewrite | Reverted to `origin/dev`. Filed #79. | `d4e5f6a` |
| 4 | Migration missing `down()` | Added; ran down/up locally to confirm. | `e5f6a7b` |
| 5 | Unused import | Removed. | `e5f6a7b` |

Root cause of 1: `submit()` read `$data['email']` for the duplicate check before
calling the validator. Reordered rather than guarding the crash.

Original acceptance criteria re-checked locally — all 3 still satisfied.
Ready for DEV validation.
```

If merging to `dev` again, wait for the lane per `policies/deployment.md`. Then
run `validate-dev`, then `review`.

## Escalation

On the third failure:

```powershell
gh issue edit 31 --add-label needs-human --remove-label needs-fix,working
gh issue comment 31 --body-file escalation.md
```

```markdown
## Escalating — 3 repair attempts failed

**Attempt 1** — Reordered validation in `submit()`.
Result: AC2 passed, AC1 broke. Duplicate-email check needs the value before
validation.

**Attempt 2** — Split into `validateShape()` then `checkDuplicate()`.
Result: AC1 and AC2 passed, AC3 broke. The duplicate check now runs before
escaping, and a name containing quotes breaks the admin list rendering.

**Attempt 3** — Escaped at the repository boundary.
Result: AC3 passed, AC1 broke again. Escaped values no longer match stored
values on the duplicate lookup.

**Assessment**
The Issue requires escaping on storage and matching on raw value. These conflict.
`policies/coding.md` says store raw, escape at output — the admin list at
`resources/views/pages/admin-contacts.php:23` renders with `<?= $contact->name ?>`
and does not escape. Fixing that view would resolve all three, but it is outside
this Issue.

**Recommendation**
Fix the admin view's escaping as a separate Issue, then AC3 becomes trivially
satisfiable here. Filed as #81.

**State**
Branch `feature/31-contact-form` pushed at `f6a7b8c`. Attempt 3 code is present
and AC1 is failing. Worktree at
`C:\wamp64\www\ideals-website\worktrees\issue-31-claude`.
```

Then stop working the Issue. Move to another only if the session limit allows.

## Never

- Start a fourth attempt
- Fix something no finding named
- Suppress a symptom instead of fixing the cause
- Delete or skip a failing test to go green
- Re-validate without re-deploying to DEV
- Report a fix as working without DEV evidence
