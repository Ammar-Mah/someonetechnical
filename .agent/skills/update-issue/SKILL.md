---
name: update-issue
description: Record the outcome of work on a GitHub Issue — post the evidence summary, set the correct state label, and clean up the worktree. Use at the end of every unit of work, and whenever an Issue's state must change.
---

# Skill: update-issue

Records an outcome on an Issue: the evidence, the label, and the cleanup.

The Issue thread is the permanent record. Six months from now it is the only
account of what happened. Write it for that reader.

## 1. Establish the true state

Do not assume. Check:

```powershell
gh issue view 31 --json number,title,labels,comments,state
gh pr list --search "31 in:body" --state all --json number,state,mergedAt
gh run list --workflow=deploy-dev.yml --limit 3 --json status,conclusion,headSha
```

Determine which is actually true:

| Situation | Label |
| --- | --- |
| Implemented, PR open, not yet reviewed | `needs-review` |
| Review or validation failed | `needs-fix` |
| DEV validation passed **and** review passed | `validated` |
| Waiting on another Issue | `blocked` |
| Autonomous work stopped | `needs-human` |
| Released to production | `done` + close |

`validated` requires **both** proofs. One without the other is not validated.

## 2. Write the outcome comment

Post one comment recording what happened. Match the situation, and keep it to
the outcome: a small change gets two lines, a standard one at most 800
characters (`policies/proportion.md`). The Issue already holds the evidence;
this comment says where it ended, not what happened again.

### Completed

```markdown
## Complete — validated on DEV

**Issue** #31 contact form
**PR** #52, squashed to `dev` as `a1b2c3d`
**DEV** https://exceedlimits.site/atlas/landingpage at `a1b2c3d` — probe confirms

### Acceptance criteria
- [x] Valid submission stores a row and shows "Thanks" — `POST /contact` → 302
      → `/contact/thanks`; `contacts` count 0 → 1
- [x] Empty email shows a field error — → 422 "Email is required"; no row added
- [x] Submitted content is escaped on display — `<script>` rendered as entities

### Log
Outcome lines present for both handlers (`app`, rid `3f9a1c02` / `7b2e0c11`);
audit entries for the writes; no `error` in the validation window.

### Captures
Contact form before `9f8e7d6` / after `a1b2c3d`, desktop and mobile — in the
validation comment above. Home page untouched, identical by hash.

### Review
PASS by codex-cli, fresh session. No blocking findings. One non-blocking note
filed as #78.

### Changes
9 files, +247 / −3. New `contacts` table (`0007_create_contacts.php`,
reverses cleanly).

### Documents
HISTORY entry added. ARCHITECTURE (Map, data model) and PLAN rewritten to the
present; PRODUCT unchanged.

### Follow-ups
- #78 — split `ContactService::submit` when a second caller appears
- #32 — admin listing (already planned)

Ready for release.
```

Every box is ticked **because it was proven**, and the proof is on the same
line. A ticked box without evidence is a false claim.

### Blocked

```markdown
## Blocked

Cannot proceed: #30 adds the `contacts` table this Issue writes to, and #30 is
still `needs-fix`.

Nothing implemented. No branch created.

Unblocks when #30 reaches `validated`.
```

### Escalated

Use the format in the `fix` skill. Attempts, failures, evidence, recommendation,
where the code is.

### Partially complete

Be exact about what is and is not done:

```markdown
## Partially complete — needs-human

**Done and validated on DEV** (commit `a1b2c3d`)
- [x] AC1 — valid submission stores a row
- [x] AC2 — empty email shows a field error

**Not done**
- [ ] AC3 — owner receives an email notification

DEV SMTP rejects the sandbox sender: `550 sender address rejected`. This is an
environment configuration matter, not a code defect — the mail call is
implemented at `src/Contact/ContactService.php:58` and unreachable to prove.

**Needs** a working DEV mail configuration, or a decision to accept AC3 as
verifiable in production only.

Code is merged and safe: the failure is caught and logged; submissions still
save.
```

Never report a partially complete Issue as complete. Never quietly drop a
criterion.

## 3. Set the label

Exactly one state label at a time:

```powershell
gh issue edit 31 --add-label validated --remove-label needs-review,working
```

Remove every stale state label in the same command. Two state labels at once
makes the board unreadable and causes another agent to pick up work that is not
available.

Verify:

```powershell
gh issue view 31 --json labels
```

## 4. File the follow-ups you promised

Anything you said you would file, file now — before you move on:

```powershell
gh issue create --title "refactor: split ContactService::submit" `
  --body-file followup.md --label "ready,backend"
```

Then reference it back. A promised follow-up that was never filed is lost work.

## 5. Clean up

Only when the PR merged and the Issue is `validated`:

```powershell
cd C:\wamp64\www\<project>\repo
git worktree remove ..\worktrees\issue-31-claude
git worktree prune
git fetch --prune
git branch -D feature/31-contact-form
```

`-D`, not `-d`: the squash merge put the work on `dev` as one new commit, so
git never sees the branch's own commits as merged and `-d` refuses. Check the
PR merged first - `gh pr view 52 --json state,mergedAt`.

`gh pr merge --delete-branch` in `build` may have removed the remote branch,
the local branch and the worktree already. `not a working tree` and `branch not
found` here mean the cleanup is done, not that something is wrong.

The worktree's `captures/` goes with it. What was posted stays on the Issue —
that is why captures are attached, never kept.

Do not remove a worktree with uncommitted or unpushed work. Check first:

```powershell
git -C ..\worktrees\issue-31-claude status --porcelain
git -C ..\worktrees\issue-31-claude log origin/dev..HEAD
```

For `needs-human`, **leave the worktree in place** — the human will open it.
Say where it is in the escalation comment.

## 6. Closing

Do not close an Issue at `validated`. Validated means proven on DEV, not
released. Closing hides it from the release process.

An Issue closes when it is released to production, by `promote-production` or a
human:

```powershell
gh issue edit 31 --add-label done --remove-label validated,production-ready
gh issue close 31 --comment "Released in v1.4.0 to https://example.com. Production smoke checks passed."
```

## Sanitising

Before posting any command output: remove passwords, tokens, keys, connection
strings, session IDs, authorization headers, real customer data, and full server
paths. Replace with `[redacted]`.

Truncate long logs to the relevant lines. A 400-line stack trace pasted into an
Issue makes the thread unusable — quote the 5 lines that matter and say where
the rest is.

## Never

- Tick an acceptance criterion without evidence on the same line
- Leave two state labels applied at once
- Close an Issue that has not reached production
- Report partial work as complete
- Delete a worktree containing unpushed work
- Post progress narration — comment at the moments listed in
  `policies/issues.md` and no others
- Forget the follow-ups you promised
