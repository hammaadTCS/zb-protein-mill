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
| Fraud by an insider | Fake supplier, inflated payment, edited history | Approvals over limits, first-payment approval, owner alerts on new parties and bank changes, no deletes, audit log, append-only ledger enforced by the database (D-28, D-05, ADR-0002 replaced by ADR-0016) | Owner-level misuse cannot be prevented by software; the audit log shows it |
| Stolen or guessed password | Accountant's password reused elsewhere | 12+ characters with mixed case, number, symbol, breach check; 5 tries per minute; two-factor login available, required for the Owner when O-31 = yes; password changed at first login | A phone left logged in: session ends after 60 minutes idle |
| Former staff still logged in | Accountant dismissed, phone still open | Disabling a user ends all their sessions at once; disabled users are logged out on the next click (BR-SEC-13) | — |
| Web attacks | Script injection, clickjacking, CSRF, forced browsing | Server-side permission on every route; company scope; CSRF tokens; CSP with nonce; frame denial; only `index.php` executes; uploads private and re-encoded (BR-SEC-05 to 07, 14) | A new zero-day in Laravel/PHP: automatic updates + Dependabot shorten the window |
| Host break-in | Stolen Hostinger login, vulnerable package | Hostinger account with a strong password and two-step login; SSH key login only; no FTP, remote database access off; the host's malware scanner on; PHP on the newest supported version; app, `.env` and uploads outside `public_html`; HTTPS only (BR-SEC-10, 12) | Someone with the Hostinger login can reach the database through phpMyAdmin and, with the one all-rights database user, change data: the daily `zb:verify`, the off-site backups and the paper register detect and recover, but cannot prevent it. Backups stay safe and unreadable to the attacker (next row) |
| Ransomware / destroyed host | Attacker or provider deletes the account | Daily and monthly backups off-site, encrypted with **sodium** to a key the host does not have, uploaded with a key that cannot delete, versioning/Object Lock (BR-OPS-01 to 03, ADR-0015) | Up to 1 day of entries lost (RPO, O-36) |
| Lost backup key | Both copies lost | Two holders, monthly restore test proves the key works (BR-OPS-02, 04) | If both copies are lost, old backups are unreadable: make a new key pair at once |
| Leaked secret in code | Key pasted into a file | Secret scan in CI, protected files, `.env` only on the host outside the web folder, the database password and storage key never in the repository, CI or a Claude session (BR-SEC-11, 18) | A secret leaked elsewhere (chat, e-mail): rotate it (runbook) |
| Vulnerable or malicious package | Compromised npm/composer dependency | Lock files, `composer audit` + `npm audit` in CI, Dependabot alerts and monthly grouped update pull requests through the normal checks, no packages outside the card list | Supply-chain attacks that are not yet known |
| AI code assistant goes wrong | Claude edits rules, weakens a check, or changes `main` | Plan first; protection hook (protected files; no commits on or pushes to `main`, no tags, no merges, no GitHub writes except its own branch); `.claude/settings.json` deny rules; protected-files check on every pull request (only with `[spec-change]`); main guard on `main`; release gate; guard script; architecture tests; human review of every plan and diff. Claude has no server access (CLAUDE.md, ADR-0005, ADR-0012) | Bugs that pass every test: the trial month (T8.5) and the integrity check catch money errors |
| Silent data corruption | Bug writes a wrong balance | Seal-row and immutability triggers, daily `zb:verify`, acceptance tests on every PR | — |

## 3. Controls by layer

**Accounts outside the system (you and the client)**
- Every provider account (Hostinger hosting, domain/DNS, storage, monitoring, GitHub) is in the **company's** name, with two-factor login, in a password manager (`docs/secrets.md`).
- GitHub: private repository on GitHub Free, which has no branch protection for private repositories. Compensating controls (ADR-0012): code reaches `main` through pull requests you merge when every check is green; Claude cannot commit on or push `main`, tag, merge or write to GitHub except its own branch; the CI main guard fails when a commit reaches `main` without a merged pull request, protected files changed without `[spec-change]`, or `main` was rewritten; the release workflow builds only from a commit whose CI on `main` is green. If the company moves to GitHub Team, a branch ruleset can be added on top.

**Hosting account** (hPanel)
- **Hostinger "Unlimited" shared hosting**, not a VPS: there is no root access and no operating system to manage (ADR-0013). Security updates and the web server are the host's responsibility; PHP is kept on the newest supported version in hPanel.
- Account owned by the **business** with two-step login; the developer gets their own access (D-10, BR-SEC-10).
- SSH with a **key only** (no password login, no FTP account); remote database access **off**, phpMyAdmin only through hPanel.
- The application, `.env` and uploads live in a folder **outside `public_html`**; only the `public` folder is on the web. The subdomain's document root points at `current/public` (the current release). The host's malware scanner is on.
- One **hPanel cron job every minute** runs `php artisan schedule:run` (D-7).

**Network and web** (app-set headers, `public/.htaccess`)
- HTTPS only with the host's free certificate; HSTS and every other security header are set by the **application** (D-8, BR-SEC-14, ADR-0008 replaced by ADR-0013); `.htaccess` keeps only what the app cannot send.
- Only `public/index.php` runs; hidden files and other `.php` files return 404; uploads over the size limit are refused before PHP.
- Access logs have no query strings (searches may contain names).

**Application** (tasks T0.2, T1.x, T5.2)
- Login: no self-registration, no "forgot password" e-mail, no account deletion (BR-USR-06). First Owner by server command only (BR-USR-07). Passwords set by the Owner must be changed at first login (BR-USR-08). Emergency resets are server commands, audited (BR-USR-09).
- Every route checks a permission on the server (BR-SEC-05, T5.3 tests every route). Every query is scoped to the company (BR-GEN-01).
- Amounts and balances are calculated on the server; browser totals are ignored (BR-GEN-06).
- Sessions: database-stored, encrypted, `Secure`, `HttpOnly`, `SameSite=Lax`, 60 minutes idle (BR-SEC-04, 15).
- Uploads: JPG/PNG/WEBP/PDF, 5 MB, checked by content, images re-encoded on the server (removes GPS data), random names, private disk, permission-checked download; the kit's public file-serving route is switched off (BR-SEC-06).
- Errors: `APP_DEBUG=false` in production and staging; plain error pages; strict model mode outside production (BR-SEC-16).
- Double-submit protection (BR-GEN-11).

**Database** (ADR-0007 replaced by ADR-0014): one MariaDB database reached by **one database user with all rights on its own database only**; the password is a strong random value in `.env` outside the web folder, never in the repository, CI or a Claude session; remote database access is off (BR-SEC-12). The immutability triggers and the seal-row trigger (BR-LED-11) are installed by the migrations; `zb:verify` refuses to run when a protection trigger is missing. Because the one user can in principle change or drop the protections, the compensating controls are the daily `zb:verify` books check, the off-site backups and the paper register.

**Backups** (ADR-0006 replaced by ADR-0015): the application command `zb:backup` dumps the database (`mariadb-dump`), gzips and encrypts it with **sodium** to a public key the host does not have the private half of, and uploads it with an upload-only storage key. Daily copies are kept 35 days and monthly copies 7 years; versioning/Object Lock stops early deletion. A monthly restore test proves the key works. Proven by `infra/tests/test-backup-restore.sh`; on the real storage by `zb-backup-verify-offsite`.

**Code and supply chain**: lock files committed; CI on two PHP versions with Pint, Larastan level 7, vite-plus lint, TypeScript, tests, guard, secret scan, audits on every pull request (both PHP versions, plus the protected-files check) and on `main` (PHP 8.5 plus the main guard); the same checks run by Claude before every pull request (`scripts/check-all.sh`); Dependabot alerts and monthly grouped update pull requests; release archives built only by CI from tags on `main`, deployed by a person after checking the SHA-256.

## 4. Rule index: where enforced, how proven

| Rule | Enforced in | Proven by |
| --- | --- | --- |
| BR-SEC-01 HTTPS only | Hostinger's free certificate + app HSTS | T8.1/T8.3 on staging |
| BR-SEC-02 password rule | `AppServiceProvider` (kit production rule) | T5.2 tests |
| BR-SEC-03 login limit | Fortify rate limiter (kit: 5/min per email+IP) | T0.2 test |
| BR-SEC-04 60-minute idle | `SESSION_LIFETIME=60` | T1.12 test |
| BR-SEC-05 permissions | Policies + middleware | T5.3 (every route) |
| BR-SEC-06 uploads | `StoreAttachment`, disk config, `.htaccess` body limit | T2.8 tests; `test-web.sh` (413) |
| BR-SEC-07 CSRF | Laravel `PreventRequestForgery` | kit tests |
| BR-SEC-08 backups | `zb:backup` (daily + monthly), the scheduler | `test-backup-restore.sh`; monthly restore (BR-OPS-04) |
| BR-SEC-09 two-factor | Fortify 2FA (kit feature kept) | kit tests; T1.12 owner rule when O-31 = yes |
| BR-SEC-10 hosting hardening | hPanel settings (HTTPS, SSH key only, no FTP, remote DB off, malware scanner, PHP version) and the app outside `public_html` | T8.1 checklist on staging |
| BR-SEC-11 secrets | `docs/secrets.md`, secret scan | `test-guardrails.sh`; CI |
| BR-SEC-12 one database user | `config/database.php`, `.env` outside the web folder | `zb:verify` books check (BR-OPS-08); go-live checklist |
| BR-SEC-13 session revocation | T1.12 middleware, T5.2 actions | T1.12, T5.2 tests |
| BR-SEC-14 headers + CSP | T1.12 middleware, all headers set by the app (D-8) | `test-web.sh`; T1.12 tests |
| BR-SEC-15 cookie flags, encrypted sessions | `.env` + `config/session.php` | T1.12 test |
| BR-SEC-16 no debug output | `.env`, error pages | T1.12 test |
| BR-SEC-17 log rules | `config/logging.php`, code review | T1.12 test (no password in logs) |
| BR-SEC-18 dependency and secret checks | CI, `check-all.sh`, Dependabot (alerts and monthly pull requests) | CI |
| BR-SEC-19 public ids in URLs | `App\Support\HasPublicId` on every linked model, UUID route patterns (`AppServiceProvider`), `idFromPublic()` for filters, migration `add_public_id_to_linked_tables` | `PublicIdTest`; T-09 route table |
| BR-OPS-01 to 11 | `zb:backup`/`zb:restore`, the scheduler, runbook | `test-backup-restore.sh`; T8.1 and go-live checklist |

## 5. If something happens

Follow `docs/operations/runbook.md`: "Suspected break-in", "A secret leaked", "Host lost", "Backup alert". The first rule in every case: **do not delete anything** (logs and backups are evidence), and do not edit the database by hand.

## 6. Not covered in version 1

- High availability (a second server): not needed for two users; recovery is by rebuild (RTO 4 h).
- A web application firewall or DDoS protection service: add a CDN/WAF in front if the site is ever attacked; then configure trusted proxies so audit IPs stay correct.
- Formal penetration test: recommended before go-live if the budget allows; T8.1 is a checklist, not a pen test.
- Data-protection law review for Pakistan: not done; the client's lawyer should confirm (O-34).
