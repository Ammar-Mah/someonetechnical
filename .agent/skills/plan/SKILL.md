---
name: plan
description: Produce an implementation plan for one GitHub Issue before writing code, and post it as an Issue comment. Also used to break a roadmap or feature request into well-formed Issues. Use whenever work is non-trivial or an Issue needs decomposing.
---

# Skill: plan

Two modes:

- **Task planning** — plan the implementation of one Issue. Run before `build`.
- **Decomposition** — turn a roadmap, feature request, or `PLAN.md` section into
  well-formed Issues.

Planning is cheap. Rework is not. Skip planning only for genuinely trivial
changes (a typo, a copy edit, a one-line fix with an obvious cause).

---

## Mode A — Task planning

### 1. Gather

```powershell
gh issue view 31 --json number,title,body,labels,comments
```

Read the Issue **and every comment**. Later comments override earlier ones and
override the body.

Then read:

- `PRODUCT.md` — what this is for
- `ARCHITECTURE.md` — how it is built, and its Map: where things are
- `HISTORY.md` — the newest entries: what changed recently and what is now true
- `DECISIONS.md` — the headings, and the entries that touch this Issue
- `.agent/framework/RULES.md` — conventions you must follow
- Only then the files the Map and the Issue point at — never `src/` end to end

Find every caller of everything you intend to modify:

```powershell
git grep -n "functionName"
git grep -n "table_name"
```

Do not plan against a mental model of the codebase. Plan against the documents
and the files they point at. Where a document disagrees with the code, the
document is wrong: say so in the plan and rewrite it as part of the change.

### 2. Check the Issue is implementable

Stop and comment if:

- An acceptance criterion is not observable — you could not prove it on DEV
- Two criteria contradict each other
- The Issue depends on something that does not exist and is not another Issue
- Two reasonable readings produce different implementations

Label `needs-human`, state the ambiguity precisely, and propose the reading you
would use. Do not guess and build.

### 3. Decide the approach

Consider at least two approaches. Choose one and be able to say why.

Bias toward:

- The smallest change that fully satisfies the criteria
- Reusing what exists over adding something new
- The pattern the codebase already uses
- No new dependency
- No new abstraction until there are two callers

### 4. Write the plan

A **small** change gets no plan comment: one line in the pull request says what
it does and why. A **standard** one gets the comment below, in at most 1,200
characters - bullets, no prose, no restating of the Issue. A **heavy** one gets
what it needs. See `policies/proportion.md`.

Post it as an Issue comment before writing code:

```markdown
## Plan — #31 contact form

**Approach**
A `ContactScreen` component renders the form; `ContactHandler` (an Events
class) validates and persists through a `Contact` model. Reuses the kit's
`Form`, `Field`, `TextInput` and `TextArea` — no new component types.
Follows the pattern of `ItemsScreen` and `AppHandler::addItem()`.

**Rejected:** a generic form-builder abstraction. One form does not justify it.

**Changes**
| File | Change |
| --- | --- |
| `src/app/Views/contact.php` | New — `@extend('app')`, one `<x:ContactScreen/>` |
| `public/index.php` | Add `contact` to the `$views` allowlist |
| `src/app/Components/ContactScreen.php` | New — the form and a `Container` for the thank-you region; region ids as constants |
| `src/app/Events/ContactHandler.php` | New — `submit(Request $r)`: validate, persist, re-render the region only |
| `src/app/Models/Contact.php` | New — `$fillable`, `deleted_at`, `Contact::add()` stamping the timestamps |
| `database/0003_create_contacts.sql` + `.down.sql` | New — table with an email index (SQL engine) |
| `tests/cases/contact.php` | New — 4 cases: the model's query, both validation branches, the screen snapshot |

**Order**
1. Schema file pair, applied to the local database (nothing to apply on the file engine)
2. Model
3. Handler
4. Screen, view, `$views` entry
5. Tests
6. `php -l`, `php tests/run.php`, then push

**Acceptance criteria → how each will be proven on DEV**
| AC | Proof |
| --- | --- |
| Valid submission stores a row and shows "Thanks" | POST the form payload to `updater.php` as the browser would; expect `status: ok` with an `inner` action on `#contact-region` whose HTML contains "Thanks"; `diagnostics?check=db` shows `contacts` rows 0 → 1 |
| Empty email shows a field error | POST with `email=""`; expect an `add` of `is-invalid` on the email field and a toast, no `inner`; rows unchanged |
| Submission is escaped on display | POST `<script>alert(1)</script>` as the name; the returned region HTML contains `&lt;script&gt;` |

A criterion about what the user sees also names the screen's entry URL and
the state to photograph, so that `build` can capture it before the merge and
`validate-dev` after (`policies/testing.md`, "Captures").

**Risks**
- `schema-change`: adds a table. Needs the DEV lane to itself.
- The sidebar's screen allowlist in `AppHandler::SCREENS` must gain the entry.

**Out of scope**
Admin listing UI (#32). Email notification (#33).

**Documents**
HISTORY entry. ARCHITECTURE: Map and data model. PLAN: milestone 3.
DATABASE.md: the `contacts` table. PRODUCT: unchanged.

**Estimate:** ~220 lines across 8 files.
```

The acceptance-criteria table is the most important part. If you cannot state
how a criterion will be proven, you cannot implement the Issue — go back to
step 2.

### 5. Proceed

Post the plan, then run `build`. If the plan revealed the Issue is much larger
than stated, say so and propose splitting it before building.

---

## Mode B — Decomposition

Turning a roadmap or feature request into Issues.

### 1. Understand the whole

Read `PRODUCT.md`, `ARCHITECTURE.md`, `PLAN.md`, and the existing Issues. Do not
create a duplicate of something already filed.

### 2. Slice by deliverable value

Each Issue should be:

- **Independently valuable** — worth shipping on its own
- **Independently testable** — provable on DEV without its siblings
- **Small** — one agent session, roughly under 400 lines of change
- **Ordered** — dependencies explicit and acyclic

Slice by user-visible capability, not by layer. `Contact form end to end` is one
good Issue. `Add contact table` / `Add contact controller` / `Add contact view`
is three bad ones — none is independently valuable or testable.

Slice as coarsely as those four allow. Every Issue costs a plan, a merge, a
deployment, a validation, a review and a history entry, so anything under about
fifteen minutes of work belongs with its neighbour. A landing page is five to
eight Issues, not fourteen — `policies/proportion.md`.

Split when an Issue would exceed roughly 400 lines, touches more than one
domain, or has more than about five acceptance criteria.

### 3. Sequence

```
#30 Database schema and base layout   (no dependencies)
      ↓
#31 Contact form                       (depends on #30)
#32 Newsletter signup                  (depends on #30, parallel with #31)
      ↓
#33 Admin submission list              (depends on #31, #32)
```

Maximise what can run in parallel — agents work concurrently. Minimise
`schema-change` Issues on the critical path; they serialise.

### 4. Write each Issue

Use the anatomy in `policies/issues.md`. Every acceptance criterion must be
observable.

```powershell
gh issue create --title "feat: contact form" --body-file plan/31.md --label "ready,feature,backend"
```

Then record dependencies as comments: `Depends on #30.`

### 5. Update PLAN.md

`PLAN.md` holds the shape of the roadmap — phases, milestones, sequence.
GitHub Issues hold the live state. Do not duplicate Issue status into `PLAN.md`;
it will go stale immediately.

### 6. Report

```
Created 7 Issues for the contact and newsletter milestone.

Sequence:
  #30 schema + layout          ready       (blocks 31, 32)
  #31 contact form             ready
  #32 newsletter signup        ready       (parallel with 31)
  #33 admin submission list    blocked     (needs 31, 32)
  ...

Schema changes: #30 only — it must integrate alone.
PLAN.md updated with the milestone.
```

---

## Never

- Write code during `plan`
- Post a plan without the acceptance-criteria proof table
- Create an Issue with an unobservable acceptance criterion
- Plan against a remembered version of the codebase rather than the current one
- Create circular dependencies
- Silently expand scope beyond what the Issue or roadmap asked for
