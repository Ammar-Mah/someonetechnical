# Decisions

Append-only. Newest last. See policies/documentation.md.

## 2026-09-15 — Link targets live on `SiteHeader`, not on the sections

**Context**
`ARCHITECTURE.md` planned `HowItWorksSection::ANCHOR` and
`SupportAreasSection::ANCHOR` as the ids the header and footer link to. #6
builds the header and footer before any section exists, and #10–#14 then run
in parallel.

**Decision**
`SiteHeader::HOW_IT_WORKS`, `SiteHeader::WHAT_WE_HELP_WITH` and
`SiteHeader::START_HREF` hold the targets. A section takes its id from its
constant; the footer, the hero and every other link read the same one.

**Alternatives**
- The literals in both header and footer until the sections arrive — one value
  in two places.
- Constants on the section classes — #11 and #12 would each edit the header and
  footer, in parallel.
- Section classes holding only a constant — a shell with nothing behind it.

**Consequences**
No section Issue edits the header or footer to connect its anchor. Renaming an
anchor is one edit.

**Refs** #6
