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

Then check the rules are current. `.agent/project.json` records the ATLAS
commit this project last received:

```powershell
(Get-Content .agent/project.json -Raw | ConvertFrom-Json).atlas   # the commit this project has
atlas check                                                      # reports: <owner>/atlas at <commit>
```

If they differ, sync before working. It merges on green checks
(`policies/git.md`), and it keeps every project on the rules, workflows and
framework files ATLAS has proven.

Another session may be syncing already. If a sync PR is open, wait for it to
merge instead of opening a second, then pull and read the rules again:

```powershell
gh pr list --head chore/sync-atlas --state open --json number   # [] means nobody is syncing
```

Otherwise:

```powershell
git worktree add -b chore/sync-atlas ..\worktrees\sync-atlas origin/dev
cd ..\worktrees\sync-atlas
atlas sync .
```

Add a `HISTORY.md` entry for what the sync brought - it is a change that
reached `dev`, and the next session reads HISTORY before anything else. A sync
can bring framework code as well as rules (`src/core/`, `__dev/`); the checks
run the project's own tests against it before the merge. Then:

```powershell
git add -A    # the worktree is fresh: everything in it is the sync's
git commit -m "chore: sync ATLAS rules (<commit>)"
git push -u origin chore/sync-atlas
gh pr create --base dev --title "chore: sync ATLAS rules" --body "Brings ATLAS <commit> into the project: rules, workflows and framework files."
gh pr checks --watch
gh pr merge --squash --delete-branch
cd ..\..\repo
git worktree remove ..\worktrees\sync-atlas
git pull --ff-only origin dev
```

Wait for the DEV deployment that merge starts, so the lane is free. If the sync
changes nothing, there is no commit to make and nothing to merge.

**Then read `AGENTS.md` and this skill again, from `.agent/skills/` in `repo`.**
The text you started from is the copy the project had before the sync; a sync
exists to change it. Follow the new one from here on, and read every other
skill from disk when you reach it, never from memory of an earlier session.

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
| `needs-human` | Stopped, awaiting a person | Report. Never touch - unless a person has answered: the newest comment begins `Decision:` and follows the escalation. Then do what it says - `needs-fix` to repair, `ready` to rebuild, or close - and say so on the Issue. A decision to repair allows one more attempt than the limit. |
| `blocked` | Waiting on a dependency | Check every `Depends on #N`. If each is `validated` or `done`, swap `blocked` for `ready` and say so - then it is available this session. |
| `working` | Another agent has it | Leave alone unless stale (>24h, no branch activity). |
| `needs-fix` | Failed review or validation | **Highest priority.** |
| `needs-review` | Awaiting review | Second priority. |
| `validated` | Proven, awaiting release | Report as release-ready. |
| `ready` | Available | Third priority. |

Sweep **every** `blocked` Issue, not only the one you mean to work on. The
survey is the only place a blocker is ever cleared: an Issue still labelled
`blocked` after its dependency reached `validated` is invisible to the next
session as well, and the backlog silently stops moving. Report the count:
`Cleared: #12, #13, #14 (blocked on #6, validated)`.

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

If you wrote it, do not review it in this context. Start a subagent with
nothing but the Issue and PR numbers and have it run `review`; it reads the
Issue, the diff, the documents and DEV for itself. That is a fresh context, and
it is what carries an Issue from claim to `validated` on one prompt. If you
cannot start one, leave the Issue `needs-review` and say so.
See `policies/review.md`.

## Phase 3 — Claim

Per `AGENTS.md` section 3 and `policies/issues.md`:

```powershell
gh issue view 31 --json number,title,body,labels,comments
```

Read **every comment**. Comments are specification.

Then:

```powershell
$claim = [guid]::NewGuid().ToString('N').Substring(0, 8)   # this session's mark
gh issue comment 31 --body "Claimed by claude-code on $env:COMPUTERNAME at $((Get-Date).ToUniversalTime().ToString('u')) (claim $claim).`nBranch: feature/31-contact-form"
gh issue edit 31 --add-label working --remove-label ready
```

Two sessions can post as one account from one machine, so the claim's mark is
how you know yours. Re-read the comments: of the claims made since the Issue was
last `ready`, the one GitHub dated earliest (`createdAt`) wins. If it is not
yours, back out: delete your comment, leave the label to the winner, say so in
one line, and pick another Issue.

## Phase 4 — Work the Issue

Run these skills in order. Each has its own SKILL.md. Follow them literally.

```
create-worktree   → branch and worktree
plan              → post the plan as an Issue comment
build             → implement, commit, push, open the PR
                    (merge to dev once checks pass and the DEV lane is free)
validate-dev      → prove it on remote DEV
review            → independent verdict, in a subagent when you built it
fix               → only if validation or review failed
update-issue      → record the outcome and set the label
```

Run all of them. An Issue you leave at `needs-review` costs a person a prompt;
finish it unless `policies/review.md` genuinely leaves you no way to.

### The repair loop

```
build → FAIL → repair 1 → FAIL → repair 2 → FAIL → repair 3 → FAIL → STOP
```

A FAIL is DEV validation's or the review's. Count repairs; the build is not
one. When the third repair fails:

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
