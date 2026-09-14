# Policy: Logging

Centrally managed by ATLAS.

**The log is the primary instrument.** Before you look at a screen, query a
database, or read a stack trace, you read the log. It is the first thing an
agent checks to know whether a system is up, the first thing it reads when
something is wrong, and the evidence it quotes when it claims something worked.

An action that left no line in the log did not happen, as far as ATLAS is
concerned.

## Why the log comes first

- It records what **actually ran**, in order, with timing — including the
  things that succeeded. A screen shows the end state; the log shows how it
  got there.
- It is the same instrument locally, on DEV, and in production. Nothing else
  is.
- It survives the request. The response is gone; the line is not.
- An agent can read it. An agent cannot watch a screen.
- Every line carries a request id, so a user's report, an Issue comment, and
  the lines that explain them can be joined without guesswork.

## Positive and negative, both

Logging only failures produces a log that is silent when everything is fine
and silent when nothing is running at all — and you cannot tell those apart.
Every unit of work records its **outcome**, whichever way it went:

| Event | Level | Must include |
| --- | --- | --- |
| A request completed | `info` | method, path, status, duration, user, query count — the per-request summary |
| A handler or action succeeded | `info` | its name, the ids it acted on, what changed (counts, not contents) |
| A handler refused its input | `warn` | which rule, which field — never the rejected value if it could be sensitive |
| A record was written | `info` on `audit` | table, id, the changed columns |
| Someone signed in, failed to, signed out, changed privilege | `info` / `warn` on `auth` | user id or attempted identifier, ip — never the password |
| A request was refused | `warn` on `security` | what, why, from where, the request id |
| An exception escaped a handler | `error` | class, message, file and line, a trimmed trace, the request id |
| A fatal ended the request | `error` | message, file, line |
| An external call was made | `info` / `error` on `external` | endpoint, duration, outcome — never credentials |
| A scheduled job ran | `info` / `error` on `job` | name, duration, items processed, outcome |
| A configuration value fell back | `warn` | the key, what was read, what was used instead |
| A query was slow | `warn` | milliseconds, the condensed statement |
| The deployment tooling ran | `info` / `warn` / `error` on `atlas` | what was checked or applied, the result |
| Boot finished | a timing mark | milliseconds, so the summary can split boot from work |

## Levels

| Level | Means | Examples |
| --- | --- | --- |
| `debug` | Detail wanted only while chasing something | every query, an undefined template variable, a mail body |
| `info` | A thing happened and it was expected | request complete, item added, mail sent, user signed in |
| `warn` | Something went wrong and the code handled it | input refused, fallback taken, slow query, request rejected, degraded mode |
| `error` | Something failed | an exception, a failed write, a failed send, a failed migration |

`info` is the floor in every environment. A production log set to `warn` has
thrown away every positive event and can only ever say what went wrong.

## Structure

One event, one line, machine-readable:

```json
{"ts":"2026-09-10 14:04:12","ms":38,"rid":"3f9a1c02","lvl":"info","ch":"app","msg":"item added","ctx":{"id":42,"by":1}}
```

- **`msg` is a fixed phrase**, past tense, the same every time that event
  happens: `item added`, `login failed`, `mail sent`. Variables go in `ctx`,
  never in the message. A fixed phrase can be grepped and counted; an
  interpolated sentence cannot.
- **`ctx` holds identifiers and counts**, not contents. The id of the row, not
  the row.
- **`ch` (the channel) says which subsystem spoke.** A project keeps a small,
  stable set, documented in `ARCHITECTURE.md`. Conventional application
  channels: `app` (handler and action outcomes), `audit` (writes), `auth`,
  `job`, `external`. The framework's own channels are listed in
  `.agent/framework/RULES.md`.
- **`rid` (the request id)** is on every line, is shown to the user in every
  error, and is quoted in every Issue comment that reports a failure.

## Never in the log

Passwords, tokens, API keys, session identifiers, card numbers, private keys,
connection strings, full request payloads, and personal data beyond an
identifier. A context array is filtered at the call site, before the call.
A log line that leaks a secret is a security incident (`policies/security.md`).

## Per environment

| | Level | Per-request summary | Slow-query threshold |
| --- | --- | --- | --- |
| Local | `info` (`debug` while chasing) | on | 200 ms |
| DEV | `info` | **on** — the summary line is the heartbeat the pipeline and agents read | 200 ms |
| Production | `info` | off, unless profiling | 200 ms |

## The log is how ATLAS checks things

| Moment | What is read | Skill |
| --- | --- | --- |
| After every DEV deployment | the deployment window: any `error` fails the deploy, `warn` is annotated | `deploy-dev.yml` |
| Before validating | the deployment window is clean and the probe answered | `validate-dev` |
| While validating | the test window: the **positive** line for each criterion is present, no `error` appeared | `validate-dev` |
| Reviewing | the change logs its outcomes; nothing sensitive in a context; the log is how it would be diagnosed | `review` (check 11) |
| Starting a session | errors since the last session become Issues | `daily-check` |
| Repairing | the failing request's `rid`, read before anything is changed | `fix` |
| Releasing | the last 24 hours on DEV carry no unexplained `error` | `prepare-release` |
| After a release | the production log, read by the approver through the host — production has no diagnostics endpoint | `promote-production` |

One thing the log cannot show is what the user saw. A criterion about that is
proven with a capture of the screen — before and after, on DEV, at the commits
the probe reported — beside the log lines, never instead of them. See
`policies/testing.md`, "Captures".

## The reading order

1. Establish the window: when the deployment finished, when the test started.
2. Read `warn` and `error` for the window. Each one is a finding, whether or
   not the criterion passed.
3. Then read for the **positive** lines you expect: the handler outcome, the
   audit entry, the request summary. Their presence is the proof.
4. Quote them — sanitised, with their request ids — in the evidence.

Absence of errors is not evidence. Presence of the expected line is.

## Hygiene

- Logging must never break the request. A logger that throws, blocks, or
  fills the disk is a defect. Buffered writes, a hard cap on lines per
  request, rotation, and a size limit are the baseline.
- Log files are never web-readable — an inert extension, a guard line, a
  deny rule — and never committed. `logs/` is in `.gitignore` and in
  `.deployignore`, so a deployment neither uploads nor sweeps it.
- Rotation keeps one backup per day past the size limit. Retention on DEV is
  whatever the disk allows; on production, whatever the release notes say.
- Expensive context is passed as a closure so it is only built if the line
  will be recorded.

## In review

A change fails review when:

- a new handler, job, action, or write records no outcome;
- a failure path is silent;
- a message interpolates variables instead of carrying them in context;
- a context contains anything from the *Never in the log* list;
- the level is wrong — a handled refusal at `error`, a real failure at `info`.
