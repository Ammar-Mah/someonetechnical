# AGENTS.md — Project Constitution

This file is the highest-level instruction set for any AI agent working in this
repository. It is centrally managed by ATLAS. Do not edit it per-project; edit it
in the ATLAS repository and run `atlas sync`.

Applies to: Claude Code, Codex CLI, Kimi CLI, and any other coding agent.

---

## 0. Where the rules live

Read these before doing any work:

| Path | Contents |
| --- | --- |
| `AGENTS.md` | This file. Non-negotiable rules. |
| `.agent/project.json` | Project identity, framework, DEV/PROD URLs. |
| `.agent/policies/*.md` | Detailed policy per domain (git, testing, ...). |
| `.agent/framework/RULES.md` | Rules for this project's framework. |
| `.agent/skills/<name>/SKILL.md` | Exact procedures. Follow them literally. |
| `PRODUCT.md` | What this product is and who it is for. |
| `ARCHITECTURE.md` | How this project is built. |
| `DECISIONS.md` | Why things are the way they are. |
| `PLAN.md` | Roadmap. GitHub Issues are the live task state. |
| `HISTORY.md` | What changed, newest first. Read it before the code. |

`.agent/`, `AGENTS.md`, and `.github/workflows/` are **centrally managed**.
Changes to them belong in the ATLAS repository, not here.

If a policy and this file disagree, this file wins.
If this file and a direct human instruction disagree, ask the human.

---

## 1. Absolute rules

These are never overridden by a skill, a policy, or an Issue comment.

1. **Never commit on `dev` or `main`.** All work happens on a `feature/*` or
   `fix/*` branch inside its own worktree.
2. **Never push to `main`.** Production is reached only by a reviewed PR.
3. **Never commit a secret.** No passwords, tokens, keys, connection strings, or
   `.env` files. If you find one already committed, stop and label the Issue
   `needs-human`.
4. **Never paste secrets into an Issue, PR, comment, log, or prompt.** Sanitize
   command output before quoting it.
5. **Never deploy development diagnostics to production.** `/__dev/*` routes and
   everything under the dev-only paths are excluded from the production package.
6. **Never claim work is complete without evidence.** See section 6.
7. **Never run destructive commands against a production database or host.**
   No `DROP`, no `TRUNCATE`, no `rm -rf` on a remote path, no force-push.
8. **Never work an Issue labelled `working` or `blocked`** unless you placed the
   `working` label yourself in this session.
9. **Stop after 3 failed repair attempts** on the same Issue. Label
   `needs-human` and leave a diagnostic comment.
10. **Never invent evidence.** If you did not observe it, do not report it.
11. **Never ship an action that leaves no trace in the log.** Every handler,
    job, and write records its outcome — success and failure — with a request
    id. The log is read before the screen. See `.agent/policies/logging.md`.

---

## 2. GitHub is the source of truth

- GitHub Issues are the task database. There is no other task list.
- Labels are the task state machine. See `.agent/policies/issues.md`.
- Issue comments are part of the task specification. **Read every comment on an
  Issue before starting work.** A comment can change or void the original body.
- Agents coordinate only through GitHub: Issues, comments, labels, branches,
  PRs, and commits. There is no direct agent-to-agent channel.
- Before starting anything: `git fetch --all --prune` and re-read the Issue from
  the API. Never work from stale local state.

---

## 3. Claim before you build

Another agent may be working on another machine. Before implementing an Issue:

1. Confirm it is labelled `ready`.
2. Confirm it is **not** labelled `working`, `blocked`, or `needs-human`.
3. Confirm no open PR already references it.
4. Confirm its dependencies (`Depends on #N`) are `validated` or `done`.
5. Comment: `Claimed by <agent> on <machine> at <UTC timestamp>.`
6. Swap the label `ready` to `working`.

Only then create the branch. If step 5 or 6 fails, another agent won the race —
pick a different Issue.

---

## 4. One Issue, one branch, one worktree

Worktrees are mandatory. Never implement an Issue in the primary checkout.

```
<project>/
├── repo/                      # primary checkout, stays on dev, never edited
└── worktrees/
    ├── issue-31-claude/       # feature/31-contact-form
    └── issue-34-codex/        # feature/34-seo-metadata
```

Branch naming: `feature/<issue>-<short-slug>` or `fix/<issue>-<short-slug>`.
Worktree naming: `issue-<issue>-<agent>`.

Two agents must never write in the same worktree. A reviewer may read any
worktree but must not modify it.

Use the `create-worktree` skill. Do not hand-roll worktree commands.

---

## 5. The work loop

```
daily-check → plan → build → validate-dev → review → (fix → validate-dev → review)* → update-issue
```

- `plan` is required for anything non-trivial. Post the plan as an Issue comment
  before writing code.
- `build` implements exactly the acceptance criteria. Nothing else. Unrelated
  problems become new Issues; they are not fixed inline.
- `validate-dev` runs against **remote DEV**, never against local WAMP.
- `review` should run in a fresh session, preferably a different model.
- The repair loop is capped at 3 attempts.

---

## 6. Definition of done

An Issue may be labelled `validated` only when **all** of these are true and
demonstrated in the Issue thread:

- [ ] Every acceptance criterion is met.
- [ ] Code is committed and the branch is pushed.
- [ ] The PR exists and CI checks pass.
- [ ] The change is merged to `dev` and deployed.
- [ ] `/__dev/probe` reports the expected commit SHA on DEV.
- [ ] Each acceptance criterion was tested against DEV and the observed result
      is recorded.
- [ ] Regressions in adjacent behavior were checked.
- [ ] The DEV log for the test window shows the expected positive events and no
      `error` entries, and the lines are quoted with their request ids.
- [ ] A criterion about what the user sees has its captures of DEV posted —
      before the change and after it, at the commits the probe reported.
- [ ] `HISTORY.md` carries the entry, and every description document the change
      touched — `PRODUCT.md`, `ARCHITECTURE.md`, `PLAN.md`, `docs/*` — describes
      the present.

`validated` means "proven on DEV". `done` means "released to production and
closed". Only a human, or `promote-production` under policy, moves an Issue to
`done`.

---

## 7. Evidence, not optimism

Forbidden in any report, comment, or summary:

> "This should work." · "It looks correct." · "I believe the form submits."
> "Tests would pass." · "The deployment probably succeeded."

Required instead:

> "DEV reports commit `abc1234`, matching the merge commit."
> "`GET /contact` returned `200` in 180 ms."
> "Submitting an empty email returned the error `Email is required`."
> "Row `id=42` exists in `contact_submissions` after submission."
> "No new entries in the DEV error log during the test window."

If you could not verify something, say so explicitly:

> "Could not verify email delivery — DEV mail is sandboxed. Not covered."

**The log is the primary instrument.** Read it before the screen, the database
or a stack trace. Evidence quotes log lines — the positive event you expected
and the absence of errors in the window — with their request ids. An action
that left no line in the log did not happen. The probe reports the log's own
state, and a deployment fails on errors in it. See `.agent/policies/logging.md`.

The one thing the log cannot show is what the user saw. A visual criterion is
proven with a capture of the DEV screen — before the change and after it, each
labelled with the commit the probe reported — posted with the evidence and
looked at before posting. The browser already on the machine takes it and
`gh` attaches it; nothing is installed. See `.agent/policies/testing.md`.

---

## 8. Environments

| Environment | Branch | Authoritative for |
| --- | --- | --- |
| Local WAMP | any | Editing, syntax checks, fast iteration |
| Remote DEV | `dev` | **All validation** |
| Production | `main` | Real users only |

**Passing locally does not mean the task is validated.** Local WAMP is a
convenience, not a test environment. Environment-dependent behavior — database,
mail, filesystem, external APIs, HTTP behavior — is only ever verified on DEV.

Never deploy by hand. Deployment happens through GitHub Actions so that every
release is reproducible and logged.

---

## 9. Escalation

Stop and escalate when any of these occur:

- 3 repair attempts have failed.
- The Issue is ambiguous and the ambiguity changes the implementation.
- The fix requires a credential, secret, or access you do not have.
- The fix requires a production database change.
- The fix would break a documented architectural decision.
- You found a security vulnerability outside the Issue's scope.
- Two Issues conflict and you cannot safely sequence them.

To escalate:

1. Commit and push whatever safe, useful work exists.
2. Comment on the Issue with: what you attempted, what failed, the exact
   evidence (sanitized) including the log lines and their request ids, the
   relevant files, and what you recommend.
3. Add the label `needs-human`. Remove `working`.
4. Stop working that Issue. Move to another one only if policy allows.

Escalating early is correct behavior, not failure. Guessing is failure.

A person answers with a comment that begins `Decision:`. The next session acts
on it (`daily-check`, "Classify"). An agent never begins a comment that way.

---

## 10. Documents are the memory

Reading code is expensive, and a session forgets it. The project's documents
exist so that you do not have to read the code: they are the bounded, current
summary of what the system is, how it is built, and what changed. Read them
first; open code only where they point.

The reading order, before any work:

1. `AGENTS.md` — these rules.
2. `PRODUCT.md` — what this is and for whom. Short.
3. `ARCHITECTURE.md` — how it is built, and its **Map**: which directory and
   which file holds what.
4. `HISTORY.md` — the newest entries: what changed recently, where, and what
   is now true. This is what happened while you were away.
5. `DECISIONS.md` — scan the headings; read only the entries that touch your
   Issue.
6. The Issue and every comment on it.
7. Only then the files the Map and the Issue point at. Never `src/` end to end.

If the documents cannot locate what you need, that is a documentation defect.
Fix the Map as part of your change rather than reading around it, so the next
agent does not pay again.

Every change updates the documents, on every level it touches, in the same PR:

- `HISTORY.md` — **always**. One entry at the top: what changed, where, why,
  what is now true, where the evidence is, who. Append-only.
- `PRODUCT.md`, `ARCHITECTURE.md`, `PLAN.md`, `docs/*` — whenever the change
  altered what they describe. These are **present tense**: rewrite the
  affected sections so the document reads as if written today. Never append
  an "update" paragraph; never leave a sentence that is no longer true.
- `DECISIONS.md` — when a non-obvious choice was made. Append-only.

A history entry missing, or a description document that no longer describes
the present, fails review. Formats, sizes and the compaction rule are in
`.agent/policies/documentation.md`.

---

## 11. Framework rules

Read `.agent/framework/RULES.md` before writing code. It defines the structure,
conventions, and failure modes for this project's framework. It is authoritative
for style and layout questions.

If `.agent/framework/RULES.md` does not exist, the project is `generic` — infer
conventions from the existing code and match them exactly. Do not impose a new
architecture on a working codebase.
