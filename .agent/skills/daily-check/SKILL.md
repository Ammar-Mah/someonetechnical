---
name: daily-check
description: The main autonomous work loop. Survey GitHub for actionable Issues, claim one, implement it, validate on DEV, review, repair, and update the Issue — then repeat until work runs out, a human is needed, or the session limit is reached. Use when asked to work the backlog, do the daily check, or pick up whatever is ready.
---

# Skill: daily-check

The entry point for autonomous work. Everything else is invoked from here.

Read `AGENTS.md` before starting. It overrides this skill.

## Session limits

Stop when any of these is true:

| Limit | Default |
| --- | --- |
| Issues completed this session | 3 |
| Repair attempts on one Issue | 3 |
| An Issue needs a human | immediate stop on that Issue |
| No actionable Issues remain | stop |

Override in `.agent/project.json` under `"dailyCheck": { "maxIssues": 3 }`.

Stopping early is correct. Working through a limit is not.

## Phase 1 — Survey

### 1.1 Sync

```powershell
cd C:\wamp64\www\<project>\repo
git fetch --all --prune
git pull --ff-only origin dev
git worktree list
```

Report any stale worktrees. Do not delete them without checking for unpushed
work — see `create-worktree`.

Then read `HISTORY.md` — the entries since your last session — and the Map in
`ARCHITECTURE.md`. That is what changed while you were away, at a fraction of
the cost of rediscovering it from the code, and it is why the documents are
kept current. See `policies/documentation.md`.

### 1.2 Read the board

```powershell
gh issue list --state open --limit 100 --json number,title,labels,assignees,updatedAt
gh pr list --state open --json number,title,headRefName,isDraft,statusCheckRollup
gh run list --limit 10 --json workflowName,status,conclusion,headSha,createdAt
```

### 1.3 Classify

Build the picture before touching anything:

| Bucket | Meaning | Action |
| --- | --- | --- |
| `needs-human` | Stopped, awaiting a person | Report. Never touch. |
| `blocked` | Waiting on a dependency | Check whether the blocker cleared. |
| `working` | Another agent has it | Leave alone unless stale (>24h, no branch activity). |
| `needs-fix` | Failed review or validation | **Highest priority.** |
| `needs-review` | Awaiting review | Second priority. |
| `validated` | Proven, awaiting release | Report as release-ready. |
| `ready` | Available | Third priority. |

### 1.4 Read the DEV log

The log is the primary instrument; it is read before the board is acted on.

```powershell
$dev   = (Get-Content .agent/project.json -Raw | ConvertFrom-Json).urls.dev
$token = atlas token
Invoke-RestMethod "$dev/__dev/probe"
Invoke-RestMethod "$dev/__dev/diagnostics?check=errors&since=24h" -Headers @{ "X-Dev-Token" = $token }
```

- The probe's `log` block must say `enabled` and `writable`, with a recent
  `last_entry_at`. If not, DEV cannot be observed: that is the first Issue,
  labelled `high-priority`, and nothing else is validated until it is fixed.
- Every `error` since the last session is a finding. If an open Issue
  explains it, note the `rid` there; otherwise create an Issue with the line
  (sanitised), its `rid` and the handler or channel it came from.
- Warnings are read, not necessarily filed: a slow query worth an Issue, a
  refused request worth a look.

See `policies/logging.md`.

### 1.5 Check the DEV lane

Only one Issue integrates into `dev` at a time — see `policies/deployment.md`.

```powershell
gh run list --workflow=deploy-dev.yml --limit 3 --json status,conclusion,headSha,createdAt
```

If a deployment is running, or an Issue is merged but not yet validated, the
lane is occupied. You may still build in a worktree; you may not merge.

### 1.6 Report the survey

Post a short summary to the human before acting:

```
Board: 12 open · 2 needs-fix · 1 needs-review · 5 ready · 1 blocked · 3 needs-human
DEV lane: free. Last deploy a1b2c3d, 40 minutes ago, success.
DEV log since last session: 0 errors, 2 warnings (slow query in ItemsScreen) — filed #61.
Plan: #47 (needs-fix), then #31 (ready), then #33 (ready).
```

## Phase 2 — Select

Work in this order:

1. **`needs-fix`** — something is already half-done and failing. Finish it.
2. **`needs-review`** — a PR is waiting. Review it (see below).
3. **`ready`** — new work.

Within a bucket, order by: `high-priority`, then `bug` over `feature`, then
blocking-others, then oldest.

Skip an Issue when:

- Its dependencies are not `validated` or `done`
- It is labelled `schema-change` and another `schema-change` Issue is in flight
- Its acceptance criteria are not observable — comment asking for clarification
  and label `needs-human`
- Its scope is unclear enough that two readings give different implementations

### Reviewing rather than building

If an Issue is `needs-review` and **you did not write the code**, run the
`review` skill on it. That is the most valuable thing you can do.

If you wrote it, do not review your own work — leave it and report that it needs
another session or model. See `policies/review.md`.

## Phase 3 — Claim

Per `AGENTS.md` section 3 and `policies/issues.md`:

```powershell
gh issue view 31 --json number,title,body,labels,comments
```

Read **every comment**. Comments are specification.

Then:

```powershell
gh issue comment 31 --body "Claimed by claude-code on $env:COMPUTERNAME at $((Get-Date).ToUniversalTime().ToString('u')).`nBranch: feature/31-contact-form"
gh issue edit 31 --add-label working --remove-label ready
```

Re-read the labels. If another agent claimed it first — its claim comment is
earlier — back out: delete your comment, restore the label, pick another Issue.

## Phase 4 — Work the Issue

Run these skills in order. Each has its own SKILL.md. Follow them literally.

```
create-worktree   → branch and worktree
plan              → post the plan as an Issue comment
build             → implement, commit, push, open the PR
                    (merge to dev once checks pass and the DEV lane is free)
validate-dev      → prove it on remote DEV
review            → independent verdict
fix               → only if validation or review failed
update-issue      → record the outcome and set the label
```

### The repair loop

```
build → validate-dev → FAIL → fix → validate-dev → FAIL → fix → validate-dev → FAIL → STOP
```

Count attempts. On the third failure:

1. Commit and push whatever safe work exists.
2. Comment with all three attempts, what each changed, and what each failed on.
3. Label `needs-human`, remove `working` and `needs-fix`.
4. Stop that Issue. Do not start a fourth attempt.

### Waiting for DEV

After merging to `dev`:

```powershell
gh run list --workflow=deploy-dev.yml --limit 1 --json databaseId,status
gh run watch <databaseId> --exit-status
```

If the deployment fails, read the log:

```powershell
gh run view <databaseId> --log-failed
```

A deployment failure is a real failure. Do not proceed to validation and do not
report the Issue as done. Diagnose it, or escalate.

## Phase 5 — Close out and continue

After `update-issue`:

1. Clean up the worktree if the branch merged.
2. Increment the session counter.
3. If the limit is not reached and actionable work remains, return to Phase 2.
4. Otherwise, report and stop.

## Final report

Always end with an honest summary:

```
Session complete. 2 Issues finished, 1 escalated.

#47  fix: session expiry on DEV       → validated  (2 repair attempts)
#31  feat: contact form               → validated  (clean)
#33  feat: newsletter signup          → needs-human
     Third attempt failed: DEV SMTP rejects the sandbox sender.
     Needs a working DEV mail configuration. Evidence in the Issue.

DEV is at 9f8e7d6. Both validated Issues are ready for release.
Not touched: #52, #58 (blocked on #33), 3 needs-human Issues.
```

Report what failed as plainly as what succeeded. A session that escalates two
Issues and finishes one has done its job correctly.

## Never

- Work an Issue labelled `working` by another agent
- Skip validation because the change looks trivial
- Report an Issue as done without DEV evidence
- Merge to `dev` while another Issue holds the lane
- Push to `main`
- Start a fourth repair attempt
- Delete another agent's worktree or branch
- Continue past the session limit because there is "just one more"
