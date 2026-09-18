# Policy: Deployment

Centrally managed by ATLAS.

## Deployment is automation, never a person and never an agent

All deployment happens through GitHub Actions. No agent uploads files, runs
`rsync`, opens an SFTP client, or edits a file on a server.

Reasons: every release is reproducible, logged, attributable, and revertible.
A hand-deployed change is none of those.

| Trigger | Workflow | Target |
| --- | --- | --- |
| Push/merge to `dev` | `deploy-dev.yml` | Remote DEV |
| Push/merge to `main` | `deploy-prod.yml` | Production |
| Tag `v*` | `release.yml` | Release notes and artefact |
| Pull request | `checks.yml` | Nothing — checks only |

## Serialised integration into DEV

Parallel development is encouraged. Parallel integration is not.

DEV is a single shared environment. If three PRs merge within a minute, DEV
holds a state nobody planned, and a validation failure cannot be attributed.

The rule:

```
PR #31 → dev → deploy → validate → done
                                     ↓
PR #34 → dev → deploy → validate → done
                                     ↓
PR #36 → dev → deploy → validate
```

Before merging into `dev`:

1. Check whether another Issue is between "merged to dev" and "validated".
   `gh issue list --label needs-review` and check the recent `dev` commits.
2. If one is in flight, wait. Comment on your Issue: `Waiting on #34 to
   validate before integrating.`
3. If nothing is in flight, merge, then immediately claim the DEV lane by
   commenting on your Issue.

An Issue holds the DEV lane from merge until its validation posts. If validation
has not completed within 30 minutes, any agent may comment noting the stall and
proceed.

## The DEV deployment contract

`deploy-dev.yml` must:

1. Check out the exact commit — never a branch reference.
2. Build or package if the project needs it.
3. Deploy to the project's folder on the shared DEV account — the domain's `atlas/<project>`.
4. Write `.dev-state.json` recording what it believes it deployed.
5. Request `/__dev/probe`.
6. Assert the probe's `git_commit` equals the deployed commit.
7. Assert `health` is `ok`.
8. Read the DEV log for the deployment window through the diagnostics
   endpoint. Fail on any `error` entry; annotate every `warn`. A deployment
   that booted with errors is not a baseline anyone can validate against.
9. Fail the workflow if any assertion fails.

Step 6 is the one that matters most; step 8 is how you learn that the code
which arrived is unhappy about where it landed. A deployment that reports success without
confirming the running application serves the new commit is not a deployment —
it is a file copy with optimism.

## Two sources of truth, deliberately

| File | Written by | Says |
| --- | --- | --- |
| `.dev-state.json` | The deployment workflow | What the pipeline **thinks** it deployed |
| `/__dev/probe` | The running application | What the application **reports** it is |

When they disagree, the deployment is broken. Common causes, in order of
likelihood:

- The FTP account does not log in to the domain's `atlas/` folder, so the project's folder is not what the domain serves at `/atlas/<project>`
- An opcode cache is serving the previous build
- The webserver is serving a different directory than the deploy target
- Two deployments raced
- The build excluded a file the app needs to compute its own commit

Diagnose in that order. Do not retry a deployment more than once without
investigating — a retry that appears to fix it has usually just outlived a
cache, and the underlying fault remains.

## Where DEV is

DEV is one FTP account and one domain, shared by every project. The account
logs in to the domain's `atlas/` folder, so nothing about paths is configured:
each project deploys to its own folder there and is served at
`https://<domain>/atlas/<project>`. An account that reaches only `atlas/`
cannot touch anything else on the domain. The account and the domain are set
once, in `scripts/local.config.json` on the machine that creates projects.

The folder is built from the repository name, which cannot be empty and cannot
contain a path. So although the upload mirrors with `--delete`, one project's
deployment cannot reach another's.

Production is per project — its own host, path and URL, in the project's
`atlas.local.json` beside the checkout.

## What reaches a server

Both deployments assemble what they upload in a folder of their own, check it
there, then mirror it to the server with `--delete`. Two lists decide it.

- **Never deployed** — the repository's own material: `.git/`, `.github/`,
  `.agent/`, `.claude/`, `.codex/`, every Markdown file at any depth
  (`AGENTS.md`, `PRODUCT.md`, `ARCHITECTURE.md`, `PLAN.md`, `DECISIONS.md`,
  `HISTORY.md`, `README.md`), `docs/`, `LLM.txt`, `captures/`, `tests/`, and
  the tooling files. A deployment fails if any of it is in what
  it would upload, and deletes it from a server an earlier upload left it on.
  Production also leaves out the DEV tooling — `__dev/`, `runtime.dev.php`,
  the deployment state.
- **The server's own** — `runtime.local.php`, `.well-known/`, and every path in
  `.deployignore` (DEV) or `.deployignore.production`: the application's cache,
  logs and data, the server's settings. Never uploaded, never deleted.

Everything else replaces what the server has, and whatever else the server has
is deleted. The paths the server keeps are the only exception to the mirror.

## Transport

Port 22 is SFTP; any other port is FTP over TLS. Plain FTP is never used:
credentials would cross the network in clear text.

## Environment secrets

Held as GitHub **environment** secrets, never repository secrets, so that
production credentials are not readable by a workflow running on a feature
branch.

| `development` | `production` |
| --- | --- |
| `DEV_HOST` | `PROD_HOST` |
| `DEV_PORT` | `PROD_PORT` |
| `DEV_USER` | `PROD_USER` |
| `DEV_PASSWORD` | `PROD_PASSWORD` |
| — | `PROD_PATH` |
| — | `PROD_URL` |

DEV has no path, URL or token secret. The workflow builds the folder from the
repository name, checks the project's DEV URL ends in that folder, and derives
the probe token — HMAC-SHA256 of the project name, keyed with the DEV
password — exactly as `atlas token` does. Nothing is invented, stored or kept
in sync; a new password means new tokens at the next deployment.

The `production` environment has a required reviewer. That is what makes
promotion a human decision. See `policies/production.md`.

## Rollback

Rolling back is a deployment of an earlier commit, not a file restore.

```powershell
gh workflow run deploy-prod.yml -f ref=<previous-good-sha>
```

A small change does not hold the DEV lane (`policies/proportion.md`): it rides
with the next deployment and is validated alongside it, in one comment naming
each Issue. Standard changes hold the lane until validated; a `schema-change`
holds it alone.

Before rolling back, check whether a migration ran. If it did, rolling back code
without reversing the schema can be worse than the fault. See
`policies/database.md`.

Every project must be able to state its last known-good production commit. That
is what `.dev-state.json` and the release history are for.

## What a deployment must never do

- Deploy from a working directory rather than a clean checkout
- Deploy a branch reference instead of a commit SHA
- Skip the probe assertion
- Deploy to production from any branch other than `main`
- Carry DEV-only files into the production package
- Run `composer install` with dev dependencies in a production package
- Continue past a failed health check
