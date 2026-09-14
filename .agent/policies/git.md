# Policy: Git

Centrally managed by ATLAS.

## Branch model

```
feature/*  fix/*        short-lived, one per Issue
    │
    ▼
   dev                  integrated development code, deploys to remote DEV
    │
    ▼
   main                 production-ready code, deploys to production
```

- `main` is protected. Direct pushes are prohibited. Force-push is prohibited.
- `dev` is protected against force-push. Direct pushes are prohibited for agents.
- All work reaches `dev` through a Pull Request.
- All work reaches `main` through a Pull Request from `dev`.

## Branch naming

| Kind | Pattern | Example |
| --- | --- | --- |
| Feature | `feature/<issue>-<slug>` | `feature/31-contact-form` |
| Bug fix | `fix/<issue>-<slug>` | `fix/58-session-expiry` |
| Release | `release/<version>` | `release/1.4.0` |
| Hotfix | `hotfix/<issue>-<slug>` | `hotfix/91-payment-500` |

The slug is lowercase, hyphenated, and at most 4 words. The Issue number is
mandatory — it is how automation links branches, PRs, and Issues.

## Worktrees

Every implementation branch gets its own worktree. See the `create-worktree`
skill. Layout:

```
C:\wamp64\www\<project>\
├── repo\            primary checkout, stays on dev, never edited by an agent
└── worktrees\
    └── issue-<n>-<agent>\
```

Remove the worktree after the branch merges. Do not leave stale worktrees —
they cause agents to work against dead branches.

```powershell
git worktree remove ..\worktrees\issue-31-claude
git worktree prune
```

## Commits

Format:

```
<type>(<scope>): <subject>

<body — why, not what>

Refs #<issue>
```

Types: `feat`, `fix`, `refactor`, `perf`, `docs`, `test`, `chore`, `build`, `ci`.

Rules:

- Subject in the imperative, lowercase, no trailing period, at most 72 chars.
- Every commit references its Issue with `Refs #<n>`.
- The commit that completes an Issue may use `Closes #<n>` in the PR body, not
  in the commit, so the Issue closes on merge to `main` rather than to `dev`.
- One logical change per commit. Do not mix refactoring with behavior change.
- Never commit commented-out code, debug statements, or generated artefacts.
- Never commit a file that is not needed to run or build the project.

Agents must sign their work in the commit trailer so multi-agent history is
attributable:

```
Agent: claude-code
```

## Pull requests

A PR must contain:

- Title: `<type>: <subject> (#<issue>)`
- A `Closes #<issue>` line, or `Refs #<issue>` when the Issue stays open.
- What changed and why, in three sentences or fewer.
- The acceptance criteria as a checklist, each ticked with its evidence.
- Any risk, migration, or follow-up.
- The `HISTORY.md` entry for the change, and the description documents it
  rewrote (`policies/documentation.md`).

A PR must not:

- Contain unrelated changes.
- Contain more than one Issue's work, unless the Issues are explicitly linked
  and a comment on each explains why they were combined.
- Be merged while CI is failing.
- Be merged by the same agent session that wrote it without a `review` pass.

Exception: a PR that changes only documentation — `HISTORY.md`,
`DECISIONS.md`, `PRODUCT.md`, `ARCHITECTURE.md`, `PLAN.md`, `docs/`, other
`*.md` — may be merged by its author once checks pass. It needs no DEV
validation; nothing runs.

## Merging

- Merge `feature/*` into `dev` with **squash** — one Issue, one commit on `dev`.
- Merge `dev` into `main` with a **merge commit** — release history stays intact.
- Never merge with unresolved conflicts left as conflict markers. Resolve them
  in the feature branch by rebasing or merging `dev` in, then re-validate.

Before merging into `dev`:

```powershell
git fetch origin
git rebase origin/dev        # or merge, if the branch is shared
```

Re-run checks after rebasing. A green check from before the rebase is stale.

## Serialised integration

Only one PR integrates into `dev` at a time. See `policies/deployment.md`.
Parallel development is encouraged; parallel integration is not.

## Prohibited operations

Agents must never run:

- `git push --force` / `--force-with-lease` on `dev` or `main`
- `git reset --hard` on a branch with unpushed work belonging to another agent
- `git rebase` on a branch that another agent has checked out
- `git clean -xfd` in the primary checkout
- `git filter-branch`, `git filter-repo`, or any history rewrite
- Any operation that deletes a remote branch other than one it created

If history genuinely needs rewriting — for example, a committed secret — stop,
label the Issue `needs-human`, and escalate. See `policies/security.md`.
