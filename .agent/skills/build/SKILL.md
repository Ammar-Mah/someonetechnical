---
name: build
description: Implement one GitHub Issue inside its worktree, commit, push, open the pull request, and merge to dev when checks pass and the DEV lane is free. Use after plan, for the actual coding work on a claimed Issue.
---

# Skill: build

Implements exactly one Issue. Nothing more.

Preconditions: the Issue is claimed and labelled `working`, the worktree exists
(`create-worktree`), and the plan is posted (`plan`).

## 1. Confirm where you are

```powershell
git rev-parse --show-toplevel        # must be the worktree, not repo\
git rev-parse --abbrev-ref HEAD      # must be feature/31-... or fix/31-...
git status --porcelain               # must be empty
```

If the branch is `dev` or `main`, **stop**. You are in the wrong place.

## 2. Re-read the specification

The Issue body, every comment, and your own plan. Keep the acceptance criteria
visible while you work — they are the definition of finished.

## 3. Implement

Follow, in this order of authority:

1. `AGENTS.md`
2. `.agent/framework/RULES.md`
3. `policies/coding.md` and `policies/security.md`
4. The surrounding code

### Scope discipline

Implement the acceptance criteria. Nothing else.

If you notice an unrelated problem: **do not fix it**. Note it, and file an
Issue after this one is done. The single exception is a change genuinely
required to make an acceptance criterion pass — declare it in the PR body.

An unrelated change in the diff fails review. See `policies/review.md`.

### While writing

- Match the surrounding code's naming, structure, and comment density.
- Parameterised queries. Escaped output. No exceptions.
- No secret, credential, host, or token in any file.
- No debug output, no `var_dump`, no commented-out code.
- No `TODO` without an Issue number.
- Write the test that would have caught the bug, for every bug fix.
- Log the outcome of every handler, job, and write — success and refusal
  both — with the ids involved and nothing sensitive in the context. A change
  that leaves no trace in the log fails review. See `policies/logging.md`.

### Commit as you go

Commit each logical step. Do not build for an hour and commit once.

```powershell
git add <specific paths>
git commit -m "feat(contact): add contact form submission

Adds ContactController, service, repository and migration. Reuses the
existing Validator rather than introducing a form abstraction for one form.

Refs #31
Agent: claude-code"
```

Use `git add <path>`, not `git add -A`. Know what you are committing. Format per
`policies/git.md`.

## 4. Check locally

Local checks catch syntax and obvious breakage fast. They **prove nothing about
correctness** — DEV is the authority.

| Framework | Commands |
| --- | --- |
| microframework (Baustein) | `php -l` on changed files; `php tests/run.php`. Read a snapshot diff before `--update`. |
| laravel | `php artisan test`; `vendor/bin/pint --test`; `vendor/bin/phpstan analyse` |
| wordpress | `php -l` on changed files; `vendor/bin/phpcs` if configured |
| generic | `php -l` on changed files; whatever `ARCHITECTURE.md` documents |

Run the app locally and exercise the change. If it fails locally it will fail on
DEV — fix it now rather than burning a deploy cycle. Then read the local log for
that window: the outcome lines you added are there, and nothing errored.

If the change touches the schema, prove the reverse works locally before
pushing: Laravel — migrate, roll back, migrate again; Baustein on the SQL
engine — apply the `database/NNNN_*.sql`, then its `.down.sql`, then the
forward file again. On the file engine there is no schema to change.

## 5. Update the documents

The documents are the next agent's memory — and yours, next session. They are
updated in the same commit as the change, before the self-check, so review
sees them with it (`policies/documentation.md`).

- **`HISTORY.md`, always.** One entry at the top, newest first: what changed
  (components, handlers, models, tables — not the diff), why, what is now
  true, where the evidence will be (the Issue and the commit), which documents
  you rewrote, who. About 120 words.
- **`ARCHITECTURE.md`** if the structure, the data model, a dependency, an
  environment, a constraint or the **Map** changed. The Map must locate every
  file you added, moved or removed.
- **`PLAN.md`** if a milestone completed or moved.
- **`docs/DATABASE.md`, `docs/API.md`, `docs/DEPLOYMENT.md`** if the schema, a
  public contract or the deployment changed.
- **`DECISIONS.md`** if you made a choice a future agent would otherwise redo.

Rewrite, never append. A description document reads as if it were written
today: edit the sentences that are no longer true; add an "update" paragraph
to nothing. A bug fix with no structural consequence changes only
`HISTORY.md`.

```powershell
git add HISTORY.md ARCHITECTURE.md PLAN.md docs
git commit -m "docs(contact): record the change and rewrite the map

Refs #31
Agent: claude-code"
```

## 6. Self-check before pushing

Read your own diff, whole:

```powershell
git diff origin/dev...HEAD
```

Check every line:

- [ ] Does every acceptance criterion have code that satisfies it?
- [ ] Is anything in this diff unrelated to the Issue?
- [ ] Any secret, token, key, password, or real customer data?
- [ ] Any debug output, `var_dump`, `console.log`, or commented-out code?
- [ ] Any string-concatenated SQL?
- [ ] Any unescaped output of a variable?
- [ ] Does the schema change have a working reverse — `down()`, or the `.down.sql`?
- [ ] Does the naming and style match the surrounding code?
- [ ] Are there tests for new branching logic and for any bug fixed?
- [ ] Does every new handler or action log its outcome, positive and negative,
      with no secret or personal data in the context?
- [ ] Is there a `HISTORY.md` entry, and does every description document the
      change touched describe the present — rewritten, not appended? Does the
      Map locate every file in the diff?

Fix anything you find now. It is far cheaper than a review cycle.

## 7. Push

```powershell
git push -u origin feature/31-contact-form
```

## 8. Open the pull request

```powershell
gh pr create --base dev --head feature/31-contact-form `
  --title "feat: contact form (#31)" `
  --body-file .git/PR_BODY.md
```

Body:

```markdown
Closes #31

## What
Adds the public contact form: `GET /contact`, `POST /contact`, and a thanks
page. Submissions persist to a new `contacts` table.

## Why
First step of the contact milestone. Reuses the existing `Validator` rather than
introducing a form abstraction for a single form.

## Acceptance criteria
- [ ] Valid submission stores a row and shows "Thanks"
- [ ] Empty email shows a field error
- [ ] Submitted content is escaped on display

Unticked — awaiting DEV validation. Ticked with evidence after `validate-dev`.

## Documents
HISTORY entry added. ARCHITECTURE (Map, data model) and PLAN rewritten;
PRODUCT unchanged; DATABASE.md rewritten.

## Notes
- Adds `contacts` table. Labelled `schema-change`; needs the DEV lane to itself.
- Shares `Validator` with the newsletter form — regression check both.

## Out of scope
Admin listing (#32). Email notification (#33).
```

Criteria are **not** ticked here. They are ticked in `validate-dev`, with
evidence. A ticked box without evidence is a false claim.

## 9. Wait for checks

```powershell
gh pr checks 52 --watch
```

If a check fails:

```powershell
gh run view <id> --log-failed
```

Fix it and push. A failing check is a real failure — never merge past it, never
disable the check, never delete the failing test.

## 9b. The budget

Check the clock against the Issue's budget - `budget: 30m` if it names one,
otherwise 15 minutes for a small change and 45 for a standard one
(`policies/proportion.md`). Over it, finish the step in hand, say on the Issue
how long it took and what is left, and either merge what is proven and file the
rest as one Issue, or label `needs-human` if nothing is mergeable. Heavy
changes have no budget.

## 10. Merge to dev — only when the lane is free

Per `policies/deployment.md`, one Issue integrates at a time.

Before merging:

```powershell
gh pr list --state open --json number,title,labels
gh run list --workflow=deploy-dev.yml --limit 3 --json status,conclusion,headSha
```

If another Issue is between "merged" and "validated", **wait**. Comment on your
Issue:

```
Ready to integrate. Waiting on #34 to complete DEV validation first.
```

A **small** change does not hold the lane at all (`policies/proportion.md`): it
rides with the next deployment and is validated alongside it. Wait only when
this is a standard or heavy change.

If the lane is free, first take the **before** captures — DEV still runs the
commit without your change, and after the merge that state is gone
(`policies/testing.md`, "Captures"). Only for a screen this change alters; an
API, schema, test or documentation Issue has nothing to photograph.

```powershell
$dev = (Get-Content .agent/project.json -Raw | ConvertFrom-Json).urls.dev   # https://<domain>/atlas/<project>
$before = (Invoke-RestMethod "$dev/__dev/probe").git_commit      # the evidence labels every capture with its commit
atlas capture "$dev/contact" captures/31-contact-desktop-before.png
atlas capture "$dev/contact" captures/31-contact-mobile-before.png -Width 390 -Height 844 -Mobile
```

One pair per screen the criteria name, plus the adjacent screen your change
must not touch. `atlas capture` prints the width the page reported and refuses
a file laid out at any other width - keep those lines for the evidence. Look at
each file before going on: a login page, an error page or an unstyled frame is
not a before. `captures/` is git-ignored; if `git status` lists it, fix the
ignore before you merge.

**Put them on the Issue now, before the merge:**

```powershell
gh issue comment 31 --body "Before captures of /contact on DEV at $before, taken ahead of the merge." `
  --attach captures/31-contact-desktop-before.png --attach captures/31-contact-mobile-before.png
```

The worktree is not a safe place to keep evidence. `gh pr merge --delete-branch`
takes the worktree off the branch, and the clean-up at the end of the session
removes the directory with everything git-ignored inside it — the befores
included. Attach them while they still exist; GitHub keeps them.

Then merge:

```powershell
git fetch origin
git rebase origin/dev          # re-run checks after rebasing
git push --force-with-lease    # safe on your own feature branch only
gh pr merge 52 --squash --delete-branch
```

Squash into `dev` — one Issue, one commit. Never force-push `dev` or `main`.

## 11. Hand off

```powershell
gh issue edit 31 --add-label needs-review --remove-label working
gh issue comment 31 --body "Implemented in PR #52, merged to dev as a1b2c3d. Awaiting DEV deployment and validation. The before captures of /contact at 9f8e7d6 are attached to the comment above."
```

The befores are already on the Issue from the step before the merge, so
whichever session validates has them — the worktree may not survive; GitHub
does. If they are not there, say so rather than passing off an after as a
before.

Then run `validate-dev`. Do not report the Issue as complete — it is not
complete until DEV proves it.

## Never

- Build on `dev` or `main`
- Build outside the worktree
- Include unrelated changes
- Commit a secret
- Merge with failing checks
- Merge while another Issue holds the DEV lane
- Tick an acceptance criterion before DEV proves it
- Say "this should work" — say what you observed, or say nothing
