# Policy: Proportion

Centrally managed by ATLAS.

The process must cost less than the change it protects. A thirteen-line CSS fix
was once planned, validated, reviewed and written up exactly like a database
table: fifty minutes and thirty-three thousand characters, almost none of it
read by a person. Proving something and retelling it are not the same thing.

## Three sizes

The size is decided when the Issue is claimed, from the Issue and the change it
asks for, and named in the claim comment: `size: small`.

| | **small** | **standard** | **heavy** |
| --- | --- | --- | --- |
| What it is | documentation, tests, copy, styling; about thirty lines or fewer; no new behaviour | anything a visitor or an operator sees | `schema-change`, `security`, production, money, personal data |
| Plan | none - one line in the pull request | a comment, at most 1,200 characters | as long as it needs |
| Captures | only a screen the change alters | one pair per screen the criteria name | one pair per screen |
| DEV validation | at most 600 characters | at most 2,000 | as long as it needs |
| Review | the diff and the validation comment, at most 800 characters, nothing re-proven | at most 2,500 characters, with a spot check on DEV | a full independent review |
| `HISTORY.md` | one line | one entry | one entry |
| The DEV lane | rides with the next deployment | holds the lane | holds the lane alone |

A change grows a size the moment it touches shared code, changes a contract, or
surprises its reviewer. It never shrinks to save time. When two readings give
different sizes, take the larger.

## Evidence, not retelling

Every acceptance criterion still gets an observed result and the number that
proves it. What goes is everything around it:

- Do not restate the criterion, the plan, or what the code does.
- Quote the log line that is the evidence, never the whole window.
- "No errors in the window" beats a list of everything that was fine.
- Leave out what does not apply: "Database: not touched" proves nothing.
- Every cap lifts the moment something fails. A failure gets the full detail,
  the request ids and the exact observation, every time.

## Follow-ups

A review's non-blocking note is fixed in the same pull request when it is under
about ten lines. Otherwise it is one comment on the Issue, in one line.

A new Issue is filed only when a visitor or an operator would notice the
difference, or when it is a correctness or a security risk. Wording, test
coverage and tidiness are not Issues; nine finished Issues once produced ten
tidy-up Issues, and the backlog stopped meaning anything.

## Planning

Issues are outcomes, not components: a landing page is five to eight Issues,
not fourteen. Anything that takes under fifteen minutes to build belongs with
its neighbour rather than in an Issue of its own, with a plan, a validation, a
review and a history entry of its own.

## Stopping

`needs-human` is for a credential, a host, legal text, a production release, or
anything irreversible. Everything else: take the obvious option, record it in
one line in `DECISIONS.md`, and carry on. A question that can wait for a person
costs a day; a reversible choice costs a minute.
