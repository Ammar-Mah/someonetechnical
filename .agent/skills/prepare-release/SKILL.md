---
name: prepare-release
description: Assemble validated Issues into a release — verify each one is genuinely proven, write the release notes, open the dev-to-main pull request, and state the rollback. Use when validated work is ready to go to production. Does not deploy.
---

# Skill: prepare-release

Assembles a release. **Does not deploy it.** Deployment is a human approval on
the `production` environment — see `promote-production`.

## 1. Find what is ready

```powershell
gh issue list --label validated --state open --json number,title,labels
git log origin/main..origin/dev --oneline
```

Every commit on `dev` and not on `main` goes out with this release. Reconcile
the two lists — a commit on `dev` with no `validated` Issue behind it is a
problem. Find out what it is before continuing.

## 2. Verify each Issue is genuinely validated

Do not trust the label. Check the thread:

```powershell
gh issue view 31 --json comments,labels
```

For each Issue:

- [ ] A validation comment exists with **per-criterion observations**
- [ ] Its probe commit matches what actually merged
- [ ] A review comment exists with a PASS verdict
- [ ] The review was independent — a different model or a fresh session
- [ ] Every acceptance criterion is ticked with evidence on the line
- [ ] Nothing is marked "not verified" that is an acceptance criterion

If an Issue fails any of these, remove it from the release:

```powershell
gh issue edit 31 --add-label needs-review --remove-label validated
gh issue comment 31 --body "Removed from the pending release: the validation comment records no per-criterion observations. Needs a real validate-dev pass."
```

A release is only as trustworthy as its weakest validation. This step is the
last place to catch a rubber-stamped PASS.

## 3. Assess release risk

```powershell
git diff origin/main...origin/dev --stat
gh issue list --label validated --json number,title,labels
```

| Present in the release | Consequence |
| --- | --- |
| `schema-change` | Human approval required, always. Rollback plan mandatory. |
| `security` | Human approval required, always. |
| A new or upgraded dependency | Human approval required. |
| Change to auth, permissions, payment, or personal data | Human approval required. |
| Change to a workflow or `.agent/` file | Human approval required. |
| None of the above | Eligible for auto-promotion if the project enables it. |

Per `policies/production.md`, the default is human approval regardless.

## 4. Confirm DEV is clean

The release ships what DEV is running. Confirm DEV is actually running it:

```powershell
$dev   = (Get-Content .agent/project.json -Raw | ConvertFrom-Json).urls.dev
$token = atlas token
Invoke-RestMethod "$dev/__dev/probe"
git rev-parse origin/dev
```

The probe commit must equal `origin/dev`. If DEV is behind, something did not
deploy — do not release code that has never run anywhere.

Check nothing is mid-flight:

```powershell
gh issue list --label working --json number,title
gh issue list --label needs-fix --json number,title
```

An Issue merged to `dev` but not yet validated must not go out. Either wait for
its validation or revert it from `dev` first.

And the DEV log for the last 24 hours carries no unexplained `error`:

```powershell
Invoke-RestMethod "$dev/__dev/diagnostics?check=errors&since=24h" -Headers @{ 'X-Dev-Token' = $token }
```

An error the release would carry to production is a blocker until it is
explained in an Issue or fixed. Quote the `rid`s in the release notes either
way.

## 5. Choose the version

Semantic versioning:

| Change | Bump | Example |
| --- | --- | --- |
| Breaking change to a public contract | major | 1.4.2 → 2.0.0 |
| New feature, backwards compatible | minor | 1.4.2 → 1.5.0 |
| Bug fix only | patch | 1.4.2 → 1.4.3 |

```powershell
gh release list --limit 5
```

A schema change that is not backwards compatible with the running production
code is a **major** bump, even if the feature is small.

## 6. Write the release notes

```markdown
# v1.5.0

Released from `dev` at `a1b2c3d`. Previous production release: `9f8e7d6` (v1.4.2).

## Features
- **Contact form** (#31) — public form at `/contact`, submissions stored and the
  owner notified.
- **Newsletter signup** (#32) — footer signup with double opt-in.

## Fixes
- **Session expiry** (#47) — sessions moved to the database; users are no longer
  logged out when the host rotates the session directory nightly.

## Database changes
**This release contains a schema change. Read before approving.**

`0007_create_contacts.php` — creates `contacts`.
- Additive only. No existing table is altered. No data is migrated.
- Reverses cleanly: `down()` drops the table. Verified on DEV.
- Estimated duration: under 1 second — new empty table.

`0008_create_sessions.php` — creates `sessions`, changes the session driver.
- Additive. Existing file-based sessions are not migrated: **all users will be
  logged out once** on release. Intended, see #47.
- Reverses cleanly, but reverting logs everyone out again.

## Configuration
No new environment variables. No secret changes.

## Validation
All 3 Issues validated on DEV at `a1b2c3d` and independently reviewed:

| Issue | Validated | Reviewed by | Evidence |
| --- | --- | --- | --- |
| #31 | 3/3 criteria | codex-cli | [comment](../../issues/31#issuecomment-1) |
| #32 | 2/2 criteria | codex-cli | [comment](../../issues/32#issuecomment-2) |
| #47 | 2/2 criteria | kimi-cli | [comment](../../issues/47#issuecomment-3) |

## Rollback
```powershell
gh workflow run deploy-prod.yml -f ref=9f8e7d6
```
Then reverse the schema changes, newest first, with the framework's mechanism:
```powershell
# Laravel:   php artisan migrate:rollback --step=2
# Baustein:  apply database/0008_*.down.sql, then database/0007_*.down.sql
```
Reversing `0008` logs all users out. `contacts` rows created after release are
lost when `0007` reverses — export first if any exist.

## Approval required
Yes. This release contains a schema change and alters session handling.

## Risk
Medium. Both migrations are additive and reversible. The session driver change
affects every logged-in user at the moment of release. Prefer a low-traffic
window.
```

The rollback section is the one an approver reads first. Write it as a runbook
somebody can follow under pressure.

## 7. Open the release PR

```powershell
gh pr create --base main --head dev `
  --title "release: v1.5.0" `
  --body-file RELEASE-1.5.0.md `
  --label "release"
```

Do **not** merge it. Merging `dev` into `main` triggers the production
deployment, which is a human decision.

## 8. Mark the Issues

```powershell
gh issue edit 31 --add-label production-ready
gh issue edit 32 --add-label production-ready
gh issue edit 47 --add-label production-ready
```

Keep `validated`. Do not close — Issues close when they reach production.

## 9. Report

```
Release v1.5.0 prepared — PR #61, dev → main.

Contents      #31 contact form · #32 newsletter signup · #47 session expiry
Commits       12 on dev, not on main
Diff          +892 / −114 across 23 files

Schema        2 migrations, both additive, both verified reversible on DEV
              #47 logs every user out once on release

Approval      REQUIRED — schema change and session handling

DEV           a1b2c3d, probe confirms, matches origin/dev
Rollback      documented in the release notes, previous good commit 9f8e7d6

Excluded from this release:
  #33  newsletter double opt-in email — validation comment had no observations.
       Returned to needs-review.

Next: a human approves PR #61 and the production environment gate.
Recommend a low-traffic window because of the session change.
```

## Never

- Merge the release PR
- Include an Issue whose validation you did not verify
- Release code DEV has never run
- Release while an unvalidated change sits on `dev`
- Write release notes without a rollback section
- Understate a schema change or a user-visible disruption
