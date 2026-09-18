# Policy: Issues

Centrally managed by ATLAS.

GitHub Issues are the task database. There is no other task list, board, or
tracker. Do not build one.

## Issue anatomy

Every Issue an agent creates must have:

```markdown
## Objective
One sentence. What must be true when this is finished.

## Background
Why this exists. Link the relevant code, prior Issue, or decision.

## Acceptance criteria
- [ ] Observable, testable statement.
- [ ] Another one.

## Out of scope
What this Issue deliberately does not cover.

## Dependencies
Depends on #12, #14.

## Risk
Anything that could go wrong, and the blast radius. Omit if genuinely none.
```

Acceptance criteria must be **observable**. An agent has to be able to check
each one against remote DEV and record what it saw.

| Bad | Good |
| --- | --- |
| The contact form should work | Submitting a valid form stores a row in `contact_submissions` and shows "Thanks" |
| Improve performance | `GET /products` responds in under 400 ms with 500 seeded products |
| Make it secure | Submitting `<script>alert(1)</script>` as the name renders it escaped, not executed |

If an Issue's acceptance criteria are not observable, an agent must not
implement it. Comment asking for clarification and label `needs-human`.

## Labels are the state machine

Exactly one **state** label at a time:

| Label | Meaning | Set by |
| --- | --- | --- |
| `ready` | Specified and unblocked. Available to claim. | Human or `plan` |
| `working` | Claimed. An agent is implementing it right now. | The claiming agent |
| `needs-review` | Implemented, pushed, PR open, awaiting review. | `build` |
| `needs-fix` | Review or validation failed. Fixes required. | `review` / `validate-dev` |
| `validated` | Proven on DEV. Ready for release. | `validate-dev` after `review` passes |
| `blocked` | Cannot proceed — a dependency or external factor. | Any agent |
| `needs-human` | Autonomous work has stopped. A person must look. | Any agent |
| `production-ready` | Approved for the next release. | `prepare-release` |
| `done` | Released to production. Issue closed. | `promote-production` or human |

Legal transitions:

```
ready ──▶ working ──▶ needs-review ──▶ validated ──▶ production-ready ──▶ done
             │             │                │
             │             ▼                │
             │         needs-fix ───────────┘
             ▼             │
          blocked          ▼
             │        needs-human
             ▼
          ready
```

Any state may move to `blocked` or `needs-human`. Nothing moves out of
`needs-human` except a human.

## Type and area labels

Free to combine, and independent of state:

`bug` · `feature` · `security` · `database` · `schema-change` · `frontend` ·
`backend` · `infra` · `docs` · `high-priority` · `good-first-issue`

Two labels carry mandatory extra process:

- **`schema-change`** — see `policies/database.md`. Only one may be integrating
  at a time.
- **`security`** — see `policies/security.md`. Requires human review before
  production.

## Claiming

Because multiple agents on multiple machines share one repository, GitHub is the
lock. Before implementation:

1. Re-fetch the Issue from the API. Local state is not trustworthy.
2. Verify the state label is `ready`.
3. Verify no open PR references the Issue.
4. Verify every `Depends on #N` is `validated` or `done`.
5. Post a claim comment:

   ```
   Claimed by claude-code on MACHINE-A at 2026-09-10T14:03:00Z (claim 3f9a1c07).
   Branch: feature/31-contact-form
   ```

   The claim mark is random and new for each claim: two sessions can post as
   one account from one machine, and the mark is how each tells its own. The
   claim also names the size the work gets - `size: small`, `standard` or
   `heavy`, per `policies/proportion.md`.
6. Replace `ready` with `working`.
7. Re-read the comments once more. If another agent also claimed it, the claim
   GitHub dated **earlier** keeps it. The other removes its comment, leaves the
   label to the winner, and picks a different Issue.

An Issue labelled `working` for more than 24 hours with no branch activity is
stale. Any agent may comment noting the staleness and return it to `ready`.

## An Issue is an outcome

One Issue is one outcome a person would notice, not one component. A landing
page is five to eight Issues. Work that takes under fifteen minutes belongs
with its neighbour: every Issue costs a plan, a validation, a review and a
history entry of its own. A note about wording, test coverage or tidiness is a
comment, not an Issue - `policies/proportion.md`.

## Comments are specification

**Read every comment before starting work.** A comment can:

- add or change an acceptance criterion
- void the original body
- record a decision that constrains the implementation
- carry evidence from a previous failed attempt

Treat the Issue body plus all comments as the complete specification, with later
comments taking precedence over earlier ones.

## What agents post

Post a comment at these moments and no others:

| Moment | Content |
| --- | --- |
| Claim | Agent, machine, timestamp, branch |
| Plan approved | The implementation plan, before coding |
| Implementation done | Commit range, PR link, what was built |
| Validation | PASS/FAIL with per-criterion evidence, including the log lines that show each outcome and, for a visual criterion, the before/after captures |
| Review | PASS/FAIL with findings |
| Escalation | Attempts, failure, evidence, recommendation |
| Release | Version, production evidence |

Do not post progress narration. Do not post "starting now", "still working", or
a summary of what you are about to do. Noise makes the thread unusable as a
specification.

## Sanitise before posting

Command output pasted into an Issue must have removed: passwords, tokens, API
keys, connection strings, session identifiers, customer names, email addresses,
and full filesystem paths that reveal server layout. Replace with `[redacted]`.

A capture is output too. Look at it before attaching it: no token in a URL,
no session identifier, no real personal data on screen. A screenshot cannot be
redacted once it is posted.

## Creating Issues from discovered work

When an agent finds a real problem outside its current Issue's scope:

1. Do **not** fix it inline.
2. Create a new Issue with the full anatomy above.
3. Label it `ready` if fully specified, `needs-human` if it needs a decision.
4. Reference it from the current Issue: `Found while working this: #74.`

Exception: a one-line fix required to make the current Issue's acceptance
criteria pass is in scope. Note it in the PR body.
