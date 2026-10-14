# G-32: Run the system on Hostinger "Unlimited" shared hosting

**Status:** proposal, part 1 of 2. Nothing is built. Part 2 (the exact text for every protected file, as a patch like `G-31-m9.patch`) is written after the account check below, because its results decide the design.

## 1. The request

- The client wants the live system on their existing Hostinger account, on the **Unlimited** web hosting plan (shared hosting), not on a VPS (spec D-41, ADR-0003).
- The client was told what is lost (section 4) and accepted it in conversation. Spec §20.3 needs this **in writing** (an e-mail is enough) before part 2 is applied.
- Hostinger's own help pages say shared plans have no PostgreSQL (it points to VPS) and that database files must not contain triggers on shared plans. So the system moves from PostgreSQL to MySQL/MariaDB.

## 2. Before this change

- **G-31 is still open.** The M9 code (T9.1 to T9.5) is on `main`, but `docs/proposals/G-31-m9.patch` (card `docs/cards/M9.md` and the spec text) was never applied. Apply it in its `[spec-change]` pull request first, so the documents match the code before the next change starts.

## 3. Step 1: check the real account (about 30 minutes)

**A. Over SSH** on the hosting account (hPanel → Advanced → SSH Access, turn it on):

1. In hPanel → Databases, create an empty test database and user (any names).
2. Copy `docs/proposals/G-32-hostinger-check.sh` to the account and run `bash G-32-hostinger-check.sh`.
3. Paste the whole output into the Claude session. Do not commit it (it has account and folder names).
4. Delete the test database in hPanel afterwards.

**B. In hPanel**, answer these:

| # | Question | Where |
| --- | --- | --- |
| H-1 | Highest PHP version you can choose | Advanced → PHP Configuration |
| H-2 | Can a cron job run **every minute**? | Advanced → Cron Jobs |
| H-3 | When you add the subdomain `accounts.zbgroup.website`, can you choose its folder? Can the folder be outside `public_html`? | Domains → Subdomains |
| H-4 | Can one database have **two users** with different rights, or does every user get all rights? | Databases |
| H-5 | How many days of daily backups are kept, and can you download them? | Files → Backups |
| H-6 | Who can log in to this Hostinger account today, and is two-step login on? | Account → Security |
| H-7 | The database type shown (MySQL or MariaDB) and its version | Databases |

## 4. What the client loses (for the written approval)

1. **The database no longer stops changes.** Today 23 database rules refuse edits and deletes of entries, the audit log and closed months, and refuse unbalanced entries. On shared hosting these checks live in the code only (unless H-4 and the trigger test both pass; see D-2).
2. **Anyone with the Hostinger login can change or delete past entries** through phpMyAdmin, without a trace in the app.
3. **One database user with all rights** instead of three limited ones (ADR-0007). A bug or a leaked password can drop tables.
4. **Backups:** Hostinger's daily copies, kept inside the same account, instead of hourly encrypted copies elsewhere that the server cannot delete (ADR-0006). Up to one day of entries can be lost, and losing the account loses every copy, unless the owner keeps the weekly download in D-4.
5. **A shared machine:** the books sit on the same computer as other customers' sites. Speed depends on them too.

## 5. Proposed design (confirmed or changed after step 1)

| # | Decision | Proposal | Depends on |
| --- | --- | --- | --- |
| D-1 | Database | The exact MySQL/MariaDB version Hostinger runs, in tests, CI, cloud sessions and the laptop. Never SQLite | Check §5, H-7 |
| D-2 | Database rules | If triggers are allowed: keep the "refuse edit/delete" rules as MySQL triggers. Either way: the balance check moves to `PostingService` only (MySQL has no check at the end of a save) | Check §5 |
| D-3 | Tamper evidence (new) | Every journal entry and audit row stores a fingerprint (hash) of the row before it; the daily `zb:verify` flags any change made outside the app | Client approval |
| D-4 | Backups | Hostinger daily backups, **plus** an encrypted backup file the owner downloads every week from an Owner-only page (encrypted with the public key using PHP's built-in `sodium`; the private key stays offline, as now). No new package | H-5, check §1 |
| D-5 | Installing updates | The CI release archive, uploaded over SSH; a `releases/` folder per version and a link to the live one, so roll-back is one command; database changes run over SSH | H-3, check §3 |
| D-6 | Scheduler | One hPanel cron job every minute: `php artisan schedule:run` | H-2, check §2 |
| D-7 | Security headers | All headers set by the app (today some are in the nginx file) | None |
| D-8 | Staging | A second subdomain on the same plan, with its own database and test data only | H-3 |
| D-9 | Access | Only the Owner holds the Hostinger login, with two-step login; a written rule in the handover | H-6 |
| D-10 | Speed | T8.2 and T9.2 are measured again on Hostinger | After T10 deploy |

## 6. What changes

**Protected files (you change them in the part 2 `[spec-change]` pull request):** `CLAUDE.md`, `START-HERE.md`, `README.md`, `.env.production.example`, `docs/spec.md` (D-41, §18, change log), `docs/requirements.md`, `docs/conventions.md`, `docs/stack-baseline.md`, `docs/architecture.md`, `docs/security.md`, `docs/environments.md`, `docs/data-handling.md`, `docs/secrets.md`, `docs/backend.md`, `docs/traceability.md`, ADR-0002, 0003, 0004, 0006, 0007 and 0008 (marked replaced, new ADRs added), `docs/cards/M8.md` and a new `docs/cards/M10.md`, `docs/operations/*`, `infra/` (VPS scripts kept as history or removed), `scripts/check-all.sh`, `scripts/cloud-session-start.sh`, `scripts/setup-local.sh`, `scripts/guard.sh`, `.github/workflows/*`.

**Existing tests that change (needs your approval):** the 5 tests that expect the database to refuse a change, and `tests/Feature/Security/MigratorConnectionTest.php` (separate database users).

**Code (by Claude, after part 2 is merged):** the 33 migrations, 24 app files with PostgreSQL-only SQL (`::numeric`/`::text` casts, `FILTER (WHERE …)`, `to_char`, `pg_advisory_xact_lock`, error codes), `config/database.php`, `phpunit.xml`, `.env.example`.

## 7. Proposed milestone M10 (cards in part 2)

1. **T10.1** Test database switch: MySQL/MariaDB in cloud sessions, laptop and CI; all tests run there.
2. **T10.2** Migrations for MySQL/MariaDB; database rules as allowed (D-2).
3. **T10.3** Queries: the 24 app files; AT-1 to AT-6 pass with the same numbers.
4. **T10.4** Tamper evidence (D-3) in `zb:verify`.
5. **T10.5** Headers in the app (D-7).
6. **T10.6** Weekly encrypted backup page (D-4) and a restore command.
7. **T10.7** Shared-hosting deploy and roll-back (D-5), scheduler (D-6), documents in `docs/operations/`.
8. **T10.8** [Human + Claude] Staging subdomain (D-8), first deploy, restore test, speed measured (D-10).

**Rough size:** 2 to 4 weeks of work. The unknowns are D-2, D-5 and speed on a shared machine.
