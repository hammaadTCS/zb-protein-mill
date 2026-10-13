# Security

What we protect, from whom, and exactly where each protection lives and how it is proven. Rule IDs refer to `docs/spec.md` §14 (BR-SEC, BR-OPS) and §7 (BR-USR).

## 1. What we protect

| Asset | Why it matters |
| --- | --- |
| The books (ledger, entries, balances) | The business's money record; must be correct and unaltered |
| Party details (names, mobiles, areas, bank details) | Personal data of suppliers, buyers, partners, employees |
| Uploaded slips and receipts | Evidence for disputes |
| User accounts (Owner, Accountant) | Whoever logs in can record payments |
| Backups | A copy of everything above |
| `APP_KEY`, backup private key, storage key, admin SSH key | Keys that unlock the above |

## 2. Threats and what stops them

| Threat | Example | Controls | What is left (accepted risk) |
| --- | --- | --- | --- |
| Fraud by an insider | Fake supplier, inflated payment, edited history | Approvals over limits, first-payment approval, owner alerts on new parties and bank changes, no deletes, audit log, append-only ledger enforced by the database (D-28, D-05, ADR-0002) | Owner-level misuse cannot be prevented by software; the audit log shows it |
| Stolen or guessed password | Accountant's password reused elsewhere | 12+ characters with mixed case, number, symbol, breach check; 5 tries per minute; two-factor login available, required for the Owner when O-31 = yes; password changed at first login | A phone left logged in: session ends after 60 minutes idle |
| Former staff still logged in | Accountant dismissed, phone still open | Disabling a user ends all their sessions at once; disabled users are logged out on the next click (BR-SEC-13) | — |
| Web attacks | Script injection, clickjacking, CSRF, forced browsing | Server-side permission on every route; company scope; CSRF tokens; CSP with nonce; frame denial; only `index.php` executes; uploads private and re-encoded (BR-SEC-05 to 07, 14) | A new zero-day in Laravel/PHP: automatic updates + Dependabot shorten the window |
| Server break-in | Stolen admin key, vulnerable package | SSH keys only, root login off, firewall (22/80/443), automatic security updates, separate OS users, web user cannot change code, database roles without passwords (BR-SEC-10, 12) | A full root compromise sees live data. Backups stay safe and unreadable to the attacker (next row) |
| Ransomware / destroyed server | Attacker or provider deletes the server | Hourly backups off-site, encrypted to a key the server does not have, uploaded with a key that cannot delete, versioning/Object Lock (BR-OPS-01 to 03) | Up to 1 hour of entries lost (RPO, O-36) |
| Lost backup key | Both copies lost | Two holders, monthly restore test proves the key works (BR-OPS-02, 04) | If both copies are lost, old backups are unreadable: make a new key pair at once |
| Leaked secret in code | Key pasted into a file | Secret scan in CI, protected files, `.env` only on the server, no DB passwords at all (BR-SEC-11, 18) | A secret leaked elsewhere (chat, e-mail): rotate it (runbook) |
| Vulnerable or malicious package | Compromised npm/composer dependency | Lock files, `composer audit` + `npm audit` in CI, Dependabot alerts and monthly grouped update pull requests through the normal checks, no packages outside the card list | Supply-chain attacks that are not yet known |
| AI code assistant goes wrong | Claude edits rules, weakens a check, or changes `main` | Plan first; protection hook (protected files; no commits on or pushes to `main`, no tags, no merges, no GitHub writes except its own branch); `.claude/settings.json` deny rules; protected-files check on every pull request (only with `[spec-change]`); main guard on `main`; release gate; guard script; architecture tests; human review of every plan and diff. Claude has no server access (CLAUDE.md, ADR-0005, ADR-0012) | Bugs that pass every test: the trial month (T8.5) and the integrity check catch money errors |
| Silent data corruption | Bug writes a wrong balance | Database balance trigger, daily `zb:verify`, acceptance tests on every PR | — |

## 3. Controls by layer

**Accounts outside the system (you and the client)**
- Every provider account (VPS, domain/DNS, storage, monitoring, GitHub) is in the **company's** name, with two-factor login, in a password manager (`docs/secrets.md`).
- GitHub: private repository on GitHub Free, which has no branch protection for private repositories. Compensating controls (ADR-0012): code reaches `main` through pull requests you merge when every check is green; Claude cannot commit on or push `main`, tag, merge or write to GitHub except its own branch; the CI main guard fails when a commit reaches `main` without a merged pull request, protected files changed without `[spec-change]`, or `main` was rewritten; the release workflow builds only from a commit whose CI on `main` is green. If the company moves to GitHub Team, a branch ruleset can be added on top.

**Server** (`infra/provision`)
- Ubuntu 26.04 LTS, security updates install automatically; reboot at 22:30 UTC when needed.
- SSH: only your admin user, key only, no root, no forwarding; `ufw` allows 22 (rate-limited), 80, 443.
- Separate OS users: `www-data` (nginx), `zbmill` (PHP), `zbdeploy` (migrations), `zbbackup` (backups). The web user cannot change code or the config cache that holds `APP_KEY` (proven in `infra/tests/test-deploy.sh`).
- PHP-FPM cannot start programs (`exec`, `proc_open`… disabled), cannot open URLs as files; only `public/index.php` runs (proven in `infra/tests/test-web.sh`).
- Time is UTC and synchronised (audit times depend on it).

**Network and web** (`infra/files/nginx`)
- HTTPS only, TLS 1.2/1.3, HSTS one year, certificate renewed automatically (BR-SEC-01).
- Requests for other host names are dropped; hidden files and other `.php` files return 404; uploads over 8 MB refused before PHP.
- Headers on every response (ADR-0008); 20 requests/second per address to PHP, bursts of 60.
- Access logs have no query strings (searches may contain names).

**Application** (tasks T0.2, T1.x, T5.2)
- Login: no self-registration, no "forgot password" e-mail, no account deletion (BR-USR-06). First Owner by server command only (BR-USR-07). Passwords set by the Owner must be changed at first login (BR-USR-08). Emergency resets are server commands, audited (BR-USR-09).
- Every route checks a permission on the server (BR-SEC-05, T5.3 tests every route). Every query is scoped to the company (BR-GEN-01).
- Amounts and balances are calculated on the server; browser totals are ignored (BR-GEN-06).
- Sessions: database-stored, encrypted, `Secure`, `HttpOnly`, `SameSite=Lax`, 60 minutes idle (BR-SEC-04, 15).
- Uploads: JPG/PNG/WEBP/PDF, 5 MB, checked by content, images re-encoded on the server (removes GPS data), random names, private disk, permission-checked download; the kit's public file-serving route is switched off (BR-SEC-06).
- Errors: `APP_DEBUG=false` in production and staging; plain error pages; strict model mode outside production (BR-SEC-16).
- Double-submit protection (BR-GEN-11).

**Database** (`infra/files/postgres`, ADR-0007): no network listener, no passwords, three roles, triggers, grants re-applied after each migration. Proven: `infra/tests/test-postgres.sh`, CI privilege check.

**Backups** (ADR-0006): public-key encryption, upload-only key, versioning/Object Lock, retention by lifecycle, monthly restore test. Proven: `infra/tests/test-backup-restore.sh`; on the real storage by `zb-backup-verify-offsite`.

**Code and supply chain**: lock files committed; CI on two PHP versions with Pint, Larastan level 7, vite-plus lint, TypeScript, tests, guard, secret scan, audits on every pull request (both PHP versions, plus the protected-files check) and on `main` (PHP 8.5 plus the main guard); the same checks run by Claude before every pull request (`scripts/check-all.sh`); Dependabot alerts and monthly grouped update pull requests; release archives built only by CI from tags on `main`, deployed by a person after checking the SHA-256.

## 4. Rule index: where enforced, how proven

| Rule | Enforced in | Proven by |
| --- | --- | --- |
| BR-SEC-01 HTTPS only | nginx site | `test-web.sh`; T8.3 on staging |
| BR-SEC-02 password rule | `AppServiceProvider` (kit production rule) | T5.2 tests |
| BR-SEC-03 login limit | Fortify rate limiter (kit: 5/min per email+IP) | T0.2 test |
| BR-SEC-04 60-minute idle | `SESSION_LIFETIME=60` | T1.12 test |
| BR-SEC-05 permissions | Policies + middleware | T5.3 (every route) |
| BR-SEC-06 uploads | `StoreAttachment`, disk config, nginx body limit | T2.8 tests; `test-web.sh` (413) |
| BR-SEC-07 CSRF | Laravel `PreventRequestForgery` | kit tests |
| BR-SEC-08 backups | `zb-backup-*`, timers | `test-backup-restore.sh`; monthly restore |
| BR-SEC-09 two-factor | Fortify 2FA (kit feature kept) | kit tests; T1.12 owner rule when O-31 = yes |
| BR-SEC-10 server hardening | `infra/provision` steps 10, 20 | T8.1 checklist on staging |
| BR-SEC-11 secrets | `docs/secrets.md`, secret scan | `test-guardrails.sh`; CI |
| BR-SEC-12 DB roles | `infra/files/postgres` | `test-postgres.sh`; CI privilege check (T1.11) |
| BR-SEC-13 session revocation | T1.12 middleware, T5.2 actions | T1.12, T5.2 tests |
| BR-SEC-14 headers + CSP | nginx snippet + T1.12 middleware | `test-web.sh`; T1.12 tests |
| BR-SEC-15 cookie flags, encrypted sessions | `.env` + `config/session.php` | T1.12 test |
| BR-SEC-16 no debug output | `.env`, error pages | T1.12 test |
| BR-SEC-17 log rules | `config/logging.php`, code review | T1.12 test (no password in logs) |
| BR-SEC-18 dependency and secret checks | CI, `check-all.sh`, Dependabot (alerts and monthly pull requests) | CI |
| BR-OPS-01 to 10 | `infra/`, runbook | infra tests; T8.1 and go-live checklist |

## 5. If something happens

Follow `docs/operations/runbook.md`: "Suspected break-in", "A secret leaked", "Server lost", "Backup alert". The first rule in every case: **do not delete anything** (logs and backups are evidence), and do not edit the database by hand.

## 6. Not covered in version 1

- High availability (a second server): not needed for two users; recovery is by rebuild (RTO 4 h).
- A web application firewall or DDoS protection service: add a CDN/WAF in front if the site is ever attacked; then configure trusted proxies so audit IPs stay correct.
- Formal penetration test: recommended before go-live if the budget allows; T8.1 is a checklist, not a pen test.
- Data-protection law review for Pakistan: not done; the client's lawyer should confirm (O-34).
