# Policy: Coding

Centrally managed by ATLAS. Framework specifics live in
`.agent/framework/RULES.md` and override style details here.

Two qualities matter more than any other in code that agents and people will
keep changing for years: **maintainability** — the next reader understands it
and can change it safely — and **mouldability** — it can be bent to a new need
by adding to it, not by rewriting it. Most of this policy is about those two.

## The first rule

**Match the code that is already there.** Naming, structure, comment density,
error handling, and idiom come from the surrounding file, not from your
preferences. A change that reads as if it were written by the original author is
correct. A change that reads as if a different developer arrived is a defect,
even when it is objectively tidier.

## Scope discipline

Implement exactly the acceptance criteria. Nothing else.

Prohibited without an Issue that asks for it:

- Renaming things that are not part of the change
- Reformatting files you did not otherwise need to touch
- Upgrading dependencies
- "While I was in here" refactors
- Adding abstraction for a second case that does not exist yet
- Adding configuration options nobody requested

A diff containing unrelated changes fails review. See `policies/review.md`.

## Simplicity

Prefer, in order:

1. Deleting code
2. Using something that already exists in the project
3. Using something from the framework or standard library
4. Writing a small, direct function
5. Adding a dependency (last resort — see below)

Do not add a layer, interface, factory, event, or abstraction until there are
at least two concrete callers. Speculative generality is the most common defect
in agent-written code.

A system stays simple by having **few concepts, each fully explained**, and
**one way to do each thing**. When a verb exists — `set`, `find`, `save` — it
means the same thing everywhere it appears. Two names for one idea, or one name
for two ideas, is a defect: the reader has to learn the exception and will get
it wrong.

## Explicit over magic

The wiring of a system should be readable in one place, in ordinary code.

- Register things by hand in a file whose job is registration. A container
  that guesses, a router that discovers, a loader that scans annotations —
  each saves a line of typing and costs every future reader the ability to
  answer "where does this come from?" by reading.
- Where something *is* found by convention — a class by its filename, an
  override by its directory — the convention is written down, its search
  order is stated, and the order is chosen so that accidents are impossible
  (the framework's own names resolve first; the project's overrides resolve
  before the defaults they replace).
- One class per file, the filename identical to the class name. Nothing to
  configure, nothing to remember, and case-sensitive filesystems hold you to
  it before production does.
- No reflection-driven behaviour in application code. If a name arrives in a
  request, it is checked against an allowlist before it is used to choose
  code.

## Boundaries and extension points

Separate **what the project owns** from **what it merely uses**, and treat the
second as read-only: framework, vendor, runtime, tooling. A fix inside a
read-only path is a fork you will maintain forever; it goes upstream instead.

Change the used part only by the means it offers:

- **Add a file** in the place the convention names.
- **Subclass** under a new name. This needs no registration and leaves the
  original intact for everyone else.
- **Override by precedence**, when the design provides it — a same-named file
  in the project's directory replacing the default. Use it only when
  subclassing cannot express the change, and say so in the PR, because the
  replaced original is now invisible to the next reader.

Put **seams at the boundaries** and nowhere else: an engine that can be
swapped (a real database, a file store, an in-memory double for tests), a hook
fired after every write, a sender that can be told to capture instead of
deliver, a compile switch that falls back to an interpreter. A seam is a
promise to keep something replaceable; scattering them everywhere makes
nothing replaceable and everything slower.

**Starter and demo code is disposable and says so.** A file that exists to be
clicked through and then deleted carries that instruction in its first
comment. Nothing else in the project may depend on it.

## One place to change a thing

When a fact is needed in more than one file, it has exactly one home:

- Identifiers shared between the code that renders and the code that updates
  are constants on the class that owns them — never a string repeated in six
  places.
- A question the application asks its data has one named answer:
  `Invoice::overdue()`, `Order::unpaidFor($customer)`. When the meaning of
  "overdue" changes, it changes once.
- The product name, the base URL, the brand mark, the default language: one
  source each, read by everything.
- A rule with a threshold — the retry count, the debounce, the page size — is
  a named constant with a comment saying why that number.

If a change requires editing the same value in two places, the design is
wrong there; fix the design, not the second place.

## Fail loudly, degrade deliberately

Silence is the enemy. A system that keeps going with wrong state is worse
than one that stops.

- **Treat warnings as errors.** An undefined index, a failed file read, a
  missing key — each is a fatal condition until the code says, explicitly,
  that it is acceptable (`??`, `isset`, `is_file`). Guarding is part of the
  code; it is never left to the runtime's tolerance.
- **A bad configuration value falls back and says so.** An unknown engine
  name, an unrecognised transport, a mistyped level: the code chooses the
  safe option *and* logs a warning naming what it read. A typo must stop
  something loudly, never behave like something else.
- **Refuse rather than half-work.** When a backend cannot do what was asked —
  a raw query on a store that has no query language, a grouping it cannot
  express — it throws with a message naming the alternative. It does not
  return a partial answer.
- **Degrade only where degrading is correct, and log it.** A cache that
  cannot write becomes "no cache" and the caller still gets correct data. An
  unknown tag is left in the output verbatim rather than crashing the page.
  Each such case is logged on its own channel so the degradation is visible.
- **Refusals explain themselves in development and say nothing in
  production.** "No method `addItm()` — did you mean `addItem()`?" is what a
  developer needs. Telling an anonymous caller which classes exist and what
  they expose is a map of the application; production gets a flat message,
  and the detail goes to the log.
- **Every request carries an id** that appears in the log and in the error
  the user sees, so a report can be matched to its log lines without
  guesswork.

## Logging is part of the code

Every unit of work — a handler, an action, a job, a write, an external call —
ends by recording its outcome, whichever way it went. The log is the primary
instrument for knowing a system is up and for finding out why it is not
(`policies/logging.md`); code that leaves no trace cannot be operated.

- **One line per outcome.** `info` when the expected thing happened, `warn`
  when the code refused or fell back, `error` when it failed. Both branches of
  every `if` that matters produce a line.
- **Fixed phrase, variables in context.** `Log::info('app', 'item added',
  ['id' => $id])` — never a sentence with the id interpolated into it. A fixed
  phrase can be grepped and counted.
- **Identifiers and counts, never contents, never secrets.** The id of the
  row, not the row. Filter the context at the call site.
- **Do not catch an exception only to log it.** Let it propagate to the layer
  that logs it once, with the request id, and turns it into a response.
- **Expensive context goes in a closure**, so it is only built if the line
  will actually be recorded.
- **A request id on every line**, carried into the error the user sees, so a
  report can be matched to its lines.
- **Logging never breaks the request.** A logger that throws is a defect.

## Delete; do not leave a shell

- A feature with no working implementation is worse than none. A method that
  reads like a feature and does nothing is a trap for every reader. Remove it,
  and record in the manual that it existed and why it went.
- No shims kept "for compatibility" with no remaining callers. Search for the
  callers; if there are none, delete.
- Commented-out code is deleted. Git remembers.
- A document that describes an older version is deleted rather than left to
  mislead, and the replacement says that it supersedes it.
- If you are unsure whether something is used, search the repository, the
  route tables, and the templates, and say so in the PR rather than guessing.

## Contracts are written down

Whatever one layer expects of another is stated in one place, explicitly:

- The functions a bootstrap must define and the globals it must fill, because
  the framework calls them.
- The session key a login must set, because the dispatcher checks it.
- The names that are reserved — payload keys, property names, internal
  prefixes — because using one silently breaks the round-trip.
- The methods a subclass may override and the ones it must not.

A contract that lives only in the implementer's head is rediscovered by every
new contributor through a bug.

## Security by construction

The safe path should be the default path, so that forgetting is impossible.

- **Escape at output, by default.** Everything a template prints is escaped
  unless it is markup by construction (a component that built its own) or has
  been explicitly marked as markup. Then no setter has to remember to escape,
  and the one trap — returning pre-rendered markup as a plain string — is
  named in the manual.
- **Make data unable to become code.** Transform templates into code once,
  from the source, before any data exists; at render time there is no pass
  left that could read a value as syntax. Never "optimise" such an ordering by
  inlining values earlier — write the comment that says why the order is
  what it is.
- **Allowlist what a request may invoke.** A name that arrives from the client
  chooses code only after it is checked against what the application
  declared: the class must be one of ours, the method must be public,
  declared by us, not static, not magic. Anything inherited from plumbing is
  refused.
- **Never evaluate a string that came from anywhere.** Resolve a function
  name against a known table; there is no `eval`.
- Validate every input at the boundary. Never trust a request, a header, a
  cookie, a file, or an external API response.
- Parameterised queries. String-concatenated SQL is prohibited without
  exception. See `policies/security.md`.
- Generated files that could be reached over the web are inert by
  construction — a guard line, an extension the server executes as nothing,
  a deny rule — not merely hidden.

## Errors

- Never swallow an exception. If you catch it, handle it or re-throw it with
  context.
- Never `catch (Exception $e) {}`. Never `catch { }` in JavaScript.
- Fail loudly in development, gracefully in production.
- An error message must say what failed and what the caller can do. `"Error"` is
  not an error message.
- Log the technical detail. Show the user the human-readable cause.
- Never expose a stack trace, file path, SQL statement, or internal identifier
  to an end user.

## Dependencies

Before adding any dependency:

- Confirm the framework or PHP standard library cannot do it.
- Check it is actively maintained and widely used.
- Check its licence is compatible.
- Check its transitive dependency count.
- Record the reason in `DECISIONS.md`.

Adding a dependency to avoid writing 20 lines is usually wrong. Adding one to
avoid writing a cryptography, date-parsing, or HTTP implementation is usually
right. A system with no dependencies, no build step and no package manager is
not primitive; it is one that will still run, unchanged, in five years.

Removing a dependency is always allowed if nothing uses it.

## Naming

- Names say what a thing is or does, not how it is implemented.
- No abbreviations except ones already used in the project.
- Booleans read as assertions: `$isPublished`, `$hasAccess`, `$canEdit`.
- Functions that return a value are nouns or `getX`; functions that act are
  verbs.
- No `data`, `info`, `manager`, `helper`, `util`, or `process` in a new name
  unless the project already uses that convention.
- **Collision hygiene in a shared namespace.** Internal state of a base class
  is prefixed (`_rendered`, `_handlers`) so that a subclass can call its own
  property `$handlers` without shadowing anything. A parent's property a
  subclass may legitimately redeclare is `public` or `protected`, never
  `private` — a private property is not inherited, and the subclass's
  same-named declaration creates a second, invisible slot. Reserved names are
  listed where the reader will look for them.

## Comments record why, and what failed

Comment density must match the surrounding file — but what a comment is *for*
is not negotiable.

Write a comment for:

- **Why this approach and not the obvious one.**
- **The failure that shaped the code.** "This used to read `$env[...]`, which
  never existed, so the setting was silently ignored" is worth more than any
  description of what the line does now. A reader who knows what broke will
  not reintroduce it.
- **An invariant that must not be optimised away** — an ordering with a
  security property, a lock released before dispatch, a value parked behind a
  placeholder until every pass has run. Say what happens if it is changed.
- **A measured trade-off**, with the number: "O(attributes × length) before;
  one pass now."
- **A reference** to the Issue or decision that produced the code.

Do not write a comment that restates the line below it. Do not leave `// TODO`
without an Issue number: `// TODO(#74): ...`.

A file's header comment says what the file is for, how it is used, and what
it expects of its neighbours — the reader should not need to open a second
file to know whether this is the one they want.

## Documentation is written against the code

- A system has **one manual**, and it documents the code **as implemented**:
  every class, method, setting and gotcha it names has been checked against
  the running code. The code is the source of truth; the manual is corrected
  to match it in the same PR as any change, never the other way around.
- The manual **documents limits honestly**. "What this will not do" — with the
  alternative named — is part of the reference, not an admission.
- A manual that has drifted is a defect with the same severity as a bug,
  because an agent will act on it.
- Project knowledge follows `policies/documentation.md`: description
  documents are present tense and rewritten, never appended; `HISTORY.md`
  records every change, newest first; agents read the documents before the
  code. Keeping them true is what makes reading them cheaper than reading
  the code.

## Tests that catch what escaped

- **Dependency-free where possible.** A test runner that needs nothing
  installed drops into a git hook or CI as it stands and never rots.
- **Never touch real data.** Tests run against a scratch store, wiped at the
  start of the run, so that rendering a screen cannot seed a real table and
  results are repeatable (ids start at 1 every time).
- **Normalise what is meaningless** before comparing: random identifiers,
  attribute order, timestamps. Then a snapshot is stable and a diff is
  signal.
- **Test the layer where the bug was invisible.** A component that renders
  perfectly on its own can still be escaped into `&lt;div&gt;` by the page
  that includes it. When a bug escapes, the test that would have caught it is
  added at the level where it showed — and the suite's own notes say which
  bug it guards.
- **Read a snapshot diff before accepting it.** A snapshot proves the new code
  renders what the old code rendered; it cannot notice that both are wrong.
  Reflexively updating is deleting the test.
- Every bug fix gets the test that would have caught it. Every branch in a
  handler gets a case.

## Configuration

- A configuration file holds **values only**, loaded before anything else
  exists, so it can never depend on the code it configures.
- **Every key carries a comment saying what breaks when it is wrong.** "The
  usual reason assets 404 after a move" tells the reader more than a type.
- **Safe defaults.** Debug off. Mail to the log, not the world. The engine
  that needs no server. A missing value is the safe value.
- **Resources open lazily.** The database connects on the first query; a
  store creates its table on the first insert. The application boots and
  renders with nothing configured, so the UI can be built first and the
  environment wired later.
- **Per-machine overrides live in a file that never leaves the machine** —
  git-ignored, deploy-ignored, holding only the keys that differ. No secret
  is ever in the shape of a file that gets committed.
- Never call the environment from application code; read configuration
  through the one mechanism the project uses.

## Runtime state heals itself

Anything generated — a compiled template, a class map, a cache entry, a
working directory — is rebuilt on demand and may be deleted at any time
without consequence:

- A map that is not warm fills itself in the slow way and remembers.
- An entry that has moved or been deleted falls back to a scan on its next
  use.
- An expired entry is removed when it is found, not left to be overwritten.
- A directory that is missing is created when it is first needed.

Generated state is never committed, never deployed, and never a reason a
deployment fails.

## Measure before optimising

- Profile first; the time is rarely where it looks. Record where it actually
  went, in the commit or the decision, with numbers from a stated machine.
- The cheapest optimisation is doing less: sending only what changed,
  rendering only the region that moved, counting in the engine instead of
  fetching rows to count them, loading a relation once for a set instead of
  once per row.
- Work paid on every request — bootstrap, reference data — is cached with a
  TTL and marked, so the request log can separate boot from work.
- Only cache a complete result. A cached partial freezes an error in place
  for the whole TTL; the uncached path would simply retry.

## Functions and files

- A function does one thing. If you need "and" to describe it, split it.
- Prefer early return over nested conditionals. Three levels of nesting is a
  smell; four is a defect.
- A file has one clear responsibility. When a file exceeds roughly 400 lines,
  ask whether it is doing two jobs — but do not split a cohesive file just to
  hit a number.
- Build repeated output in a method that can be read and tested, assign it to
  a property, and print the property — not in logic embedded in a template.

## Formatting

- Follow the project's existing formatter and linter. Never change its config
  as part of an unrelated change.
- PHP: PSR-12 unless the framework rules say otherwise.
- JavaScript: the project's existing style. No transpiler or build step may be
  introduced without an Issue.
- Indentation, quote style, and trailing commas: copy the file you are editing.
