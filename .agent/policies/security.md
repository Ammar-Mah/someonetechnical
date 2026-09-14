# Policy: Security

Centrally managed by ATLAS. This policy is not negotiable by an Issue comment.

## Secrets

**Never in Git.** Not in source, config, tests, fixtures, comments, commit
messages, or documentation. Not "temporarily". Not in a branch you intend to
squash.

Secrets live in exactly two places:

1. GitHub **environment secrets** (`development`, `production`), used by Actions.
2. Two files on the engineer's machine, both kept out of every repository and
   read only by the bootstrap scripts to populate (1): `scripts/local.config.json`,
   holding the one shared DEV FTP account, and `atlas.local.json` in a
   project's folder — beside the checkout and **outside** it — holding that
   project's production host.

Committed `.env.example` files hold keys with empty or obviously-fake values.

**Never in an Issue, PR, comment, log, or prompt.** Before pasting any command
output, remove: passwords, tokens, API keys, private keys, connection strings,
session IDs, cookies, authorization headers, and customer personal data. Replace
with `[redacted]`. A capture is output too: look at the screenshot before
attaching it, and never post one that shows a token, a session identifier or
real personal data — an image cannot be redacted once posted.

### If a secret is already committed

Stop all work on the Issue.

1. Do **not** rewrite history yourself.
2. Do **not** simply delete the value in a new commit — it stays in history.
3. Comment on the Issue naming the file and the **kind** of secret (never the
   value itself).
4. Label `security` and `needs-human`.
5. Escalate immediately. The credential must be rotated first, then history
   handled by a person.

## Credential separation

- DEV and PROD have separate credentials. Never reuse.
- Deployment accounts get least privilege: write access to the deployment path
  only. No shell beyond what deployment needs, no access to other sites.
- Agents do not receive production credentials. Production is reached only
  through GitHub Actions.
- Database users: the application user must not have `DROP`, `CREATE USER`, or
  `GRANT`. Migrations may use a separate, more privileged user invoked only by
  the deployment workflow.

## Production is not a playground

Agents must never:

- SSH into production
- Run a query against the production database
- Modify a file on the production host
- Deploy to production outside the `deploy-prod` workflow
- Read production customer data

Production access is a human action with a human reason.

## Development diagnostics must not reach production

`/__dev/probe`, `/__dev/diagnostics`, debug toolbars, test endpoints, seed
scripts, and fixture data are excluded from the production package by the build
step — see `policies/production.md`.

A runtime `if (environment === 'production')` guard is **not sufficient**. It is
one misconfigured variable away from exposing the endpoint. Exclusion at package
time is the control; the runtime guard is the backup.

The diagnostics endpoint itself must:

- require a token (`DEV_PROBE_TOKEN`) for anything beyond basic health — one per
  project, derived from the DEV password rather than invented, so a leaked token
  opens one project's diagnostics and reveals nothing about the password
- never return raw environment variables, credentials, keys, or customer data
- never accept a path, filename, or SQL fragment as a parameter
- never execute arbitrary code

## Application security baseline

Every project, every change:

| Risk | Requirement |
| --- | --- |
| SQL injection | Parameterised queries only. String-concatenated SQL is prohibited. |
| XSS | Escape at output. Never render unescaped user input. Use the framework's escaping. |
| CSRF | State-changing requests require a CSRF token. |
| Auth | Check authorisation on every protected action, server-side, per request. Hiding a button is not access control. |
| Passwords | `password_hash()` with the default algorithm. Never MD5, SHA1, or a custom scheme. |
| Sessions | Regenerate the ID on privilege change. `HttpOnly`, `Secure`, `SameSite=Lax` minimum. |
| File upload | Validate type by content, not extension. Store outside the web root. Never execute an uploaded file. |
| Redirects | Never redirect to a URL taken from user input without an allow-list. |
| Rate limiting | Login, password reset, and any send-email endpoint must be rate limited. |
| Headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` or CSP `frame-ancestors`, HSTS in production. |
| Errors | Never show a stack trace, SQL statement, or path to an end user. |
| Dependencies | Review before adding. Check for known advisories. |
| Logging | Every security event is logged — refused requests, failed and successful logins, privilege changes, rejected tokens — with a request id and never with the secret involved. A silent refusal is invisible to the person watching the log. See `policies/logging.md`. |

## Reviewing for security

Every `review` pass checks, at minimum:

1. Is any user input used in a query, a path, a command, or output without
   validation and escaping?
2. Does every new route enforce authentication and authorisation?
3. Did the change add a way to enumerate or access another user's data?
4. Did the change log something sensitive?
5. Did the change add a dependency? Is it justified and sound?
6. Did the change weaken an existing control?

## Finding a vulnerability mid-task

If you find a vulnerability that is **not** part of your Issue:

1. Do not fix it inline and do not describe the exploit in a public Issue.
2. Create a new Issue with labels `security` and `high-priority`. Describe the
   affected component and class of problem — not a working exploit.
3. Reference it from your current Issue.
4. If it is actively exploitable in production, label `needs-human`, escalate
   immediately, and say so in plain terms.

## Security-labelled Issues

An Issue labelled `security`:

- requires a review pass by a different model or a fresh session
- requires human approval before production promotion, always, regardless of
  any auto-promotion policy
- must never have its exploit details posted in a public repository's Issue
