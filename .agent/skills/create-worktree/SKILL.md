---
name: create-worktree
description: Create an isolated Git worktree and branch for one GitHub Issue. Use before implementing any Issue, and whenever a task needs a working directory separate from the primary checkout. Never implement an Issue in the primary checkout.
---

# Skill: create-worktree

Creates one branch and one worktree for one Issue, so that multiple agents can
work the same repository concurrently without colliding.

**Never implement an Issue in the primary checkout.** The primary checkout stays
on `dev` and is never edited.

## Inputs

| Input | Required | Example |
| --- | --- | --- |
| Issue number | yes | `31` |
| Kind | yes | `feature` or `fix` |
| Slug | yes | `contact-form` (lowercase, hyphens, max 4 words) |
| Agent name | yes | `claude`, `codex`, `kimi` |

## Layout

```
C:\wamp64\www\<project>\
├── repo\                       primary checkout — stays on dev
└── worktrees\
    ├── issue-31-claude\        feature/31-contact-form
    └── issue-34-codex\         feature/34-seo-metadata
```

## Procedure

### 1. Confirm you are in the primary checkout

```powershell
git rev-parse --show-toplevel
git rev-parse --abbrev-ref HEAD
```

If the branch is not `dev`, stop. You are in a worktree or on the wrong branch.
Move to the primary checkout first.

### 2. Refuse to proceed with a dirty tree

```powershell
git status --porcelain
```

If this outputs anything, stop. Report the dirty files and ask the human. Never
stash, reset, or discard another agent's work.

### 3. Sync

```powershell
git fetch --all --prune
git pull --ff-only origin dev
```

If `--ff-only` fails, the primary checkout has diverged. Stop and escalate — do
not merge or rebase the primary checkout.

### 4. Check the branch is not already taken

```powershell
git ls-remote --heads origin "feature/31-contact-form"
git worktree list
```

If the remote branch exists, another agent is on this Issue. Stop and pick a
different Issue. If a local worktree exists for this Issue but the remote branch
does not, the previous attempt was abandoned — see *Recovering a stale worktree*.

### 5. Create the branch and worktree in one step

```powershell
$issue  = 31
$slug   = "contact-form"
$agent  = "claude"
$branch = "feature/$issue-$slug"
$path   = "..\worktrees\issue-$issue-$agent"

git worktree add -b $branch $path origin/dev
```

`origin/dev` — not `dev`, not `HEAD`. The branch must start from the current
remote state.

### 6. Publish the branch immediately

```powershell
git -C $path push -u origin $branch
```

Pushing an empty branch straight away is how other agents and machines see the
Issue is taken. Do this before writing any code.

### 7. Prepare the worktree

Change into it and install whatever the project needs:

```powershell
cd $path
git rev-parse --abbrev-ref HEAD    # confirm the branch
```

Then, per framework:

| Framework | Setup |
| --- | --- |
| microframework (Baustein) | Nothing to install. Copy `runtime.local.php` from the primary checkout if one exists there. |
| laravel | `composer install`, copy `.env` from the primary checkout, `php artisan key:generate` if absent |
| wordpress | `composer install` if used |
| generic | Whatever `ARCHITECTURE.md` documents |

Copy git-ignored local config from the primary checkout — `runtime.local.php`,
`.env` — so the worktree can run locally. Never create new credentials.

### 8. Report

```
Worktree ready.
Issue:    #31
Branch:   feature/31-contact-form
Path:     C:\wamp64\www\ideals-website\worktrees\issue-31-claude
Base:     origin/dev at a1b2c3d
```

All subsequent work for this Issue happens in that path.

## Recovering a stale worktree

A worktree left behind by a crashed session:

```powershell
git worktree list                    # find it
git -C <path> status --porcelain     # is there unpushed work?
git -C <path> log origin/dev..HEAD   # are there unpushed commits?
```

If there is uncommitted or unpushed work, **do not delete it**. Report it and
ask the human.

If it is genuinely empty:

```powershell
git worktree remove <path>
git worktree prune
git branch -D <branch>
git push origin --delete <branch>    # only if you created it and it is empty
```

## Cleaning up after a merge

Once the PR is merged and the Issue is validated:

```powershell
cd C:\wamp64\www\<project>\repo
git worktree remove ..\worktrees\issue-31-claude
git worktree prune
git fetch --prune
git branch -D feature/31-contact-form
```

`-D`, not `-d`: after a squash merge git does not see the branch's own commits
on `dev`, so `-d` refuses. `gh pr merge --delete-branch` removes the remote
branch, and often the local branch and worktree with it — "not a working tree"
means that cleanup already happened. Stale worktrees cause agents to work
against dead branches — always clean up.

## Rules

- One Issue, one branch, one worktree. Never two Issues in one worktree.
- Two agents must never write in the same worktree. A reviewer may read any
  worktree but must not modify it.
- Never `git checkout` a different branch inside a worktree. Worktrees are
  single-purpose.
- Never create a worktree from a local branch reference. Always `origin/dev`.
- Never delete a worktree containing uncommitted or unpushed work.
