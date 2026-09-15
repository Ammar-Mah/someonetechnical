# History

Newest first. One entry per change that reached `dev`. Entries are never edited except to correct a fact. See .agent/policies/documentation.md.

## 2026-09-15 — Initialised (#6–#19 filed)

**Changed** ARCHITECTURE.md and PLAN.md written from PRODUCT.md; 14 Issues filed across four phases — 4 ready, 8 blocked, 2 needs-human. **Why** /project-init. **Now true** The Map describes the template as shipped, demo included; *Planned structure* is not built. DEV serves `76e31ec` healthy, but `/health` answers 404, `log.metrics` is false, `starter_auto_login` is true and the session cookie lacks `HttpOnly` and `SameSite` — #7, #8 and #9 cover them. **Evidence** The Issues; the DEV probe at 2026-09-15T10:31Z. **Documents** ARCHITECTURE and PLAN written; PRODUCT supplied by the human, unchanged. Merged through a PR, not pushed to `dev` (AGENTS.md §1). **By** claude-code, ITNEUE-154F1007

## 2026-09-14 — Project created

**Changed** Created from the ATLAS microframework template: repository Ammar-Mah/someonetechnical, branches main and dev, the ATLAS rules, workflows and DEV probe. **Why** New project. **Now true** Nothing is planned yet; /project-init writes ARCHITECTURE.md and PLAN.md and files the Issues. **Evidence** new-project.ps1 output. **Documents** PRODUCT.md supplied; the rest are stubs. **By** new-project.ps1 on ITNEUE-154F1007
