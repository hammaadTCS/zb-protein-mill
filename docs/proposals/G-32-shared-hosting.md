# G-32: Run the system on Hostinger "Unlimited" shared hosting

**Status:** proposal, part 1 of 2. Nothing is built. Part 2 (the exact text for every protected file, as a patch like `G-31-m9.patch`) is written after the account check below, because its results decide the design.

## 1. The request

- The business wants the live system on its existing Hostinger account, on the **Unlimited** web hosting plan (shared hosting), not on a VPS (spec D-41, ADR-0003).
- **The system must keep working as built:** the same rules, screens, reports and numbers, and the same protection of the books, because the books are kept for FBR. Every protection that depends on the VPS is replaced by the nearest equivalent that works on shared hosting (section 4). Only what cannot be replaced is listed as lost (section 5).
- The developer is also a partner. The other partner approved the move verbally on Zoom. Spec §20.3 needs it **in writing**: one e-mail or message from the other partner accepting section 5 is enough.
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
| H-6 | Who owns this Hostinger account, who else can log in, and is two-step login on? Can you give a second person their own access? | Account → Security / Access |
| H-7 | The database type shown (MySQL or MariaDB) and its version | Databases |

## 4. How each protection carries over

| Protection today (VPS) | On shared hosting | Depends on |
| --- | --- | --- |
| Money exact to the paisa (`numeric`, `BigDecimal`, strings in JSON) | **Same** (`DECIMAL`, `BigDecimal`). AT-1 to AT-6 must give the same numbers | None |
| Only `PostingService` writes journal rows; debits equal credits | **Same** in code | None |
| Database refuses an unbalanced entry (deferred check at the end of the save) | **Replaced:** `PostingService` writes the lines, then one "seal" row per entry; a database rule on the seal row adds up the lines and refuses the whole save if debits and credits differ | Triggers allowed (check §5) |
| Database refuses edits and deletes of entries, audit rows, rate changes and month events | **Same idea** as MySQL triggers | Triggers allowed (check §5) |
| The web app's database user cannot remove those rules (ADR-0007) | **Same** if a second user with fewer rights is possible; otherwise lost (section 5) | H-4 |
| No database password (peer login) | **Lost:** the password is in `.env`, outside the web folder | None |
| Hourly backups, encrypted with a public key, off-site, the server cannot delete them (ADR-0006) | **Same idea:** an hourly scheduled job dumps the database from PHP, encrypts it with PHP's built-in `sodium` to the public key (private key offline, two copies, one per partner), and uploads it to object storage (worked example: Backblaze B2) with an upload-only key; Object Lock keeps copies for 7 years | Outside storage approved; outgoing HTTPS (check §4) |
| Daily `zb:verify` check of the books | **Same** | None |
| Proof that nobody changed the books outside the app (new) | **Added:** a books fingerprint (section 6) | Approval |
| Security headers and CSP nonce | **Same**, all set by the app (today some are in the nginx file) | None |
| HTTPS | Hostinger's free certificate, HSTS from the app | None |
| One-command deploy with roll-back | **Same idea:** a folder per release and a link to the live one | H-3, check §3 |
| Scheduler every minute (systemd) | hPanel cron every minute | H-2, check §2 |
| Separate staging server | A second subdomain on the same plan, with its own database and test data only | H-3 |
| Uptime and backup alerts | **Same** (outside monitor) | None |
| Server for this system only | **Lost** (shared machine) | None |

## 5. What is still lost (for the written approval)

1. **Someone with the Hostinger login can still change the database directly** (phpMyAdmin), and with one all-rights database user can also remove the database rules first. The books fingerprint (section 6) **detects** this at the next check; nothing on shared hosting can **prevent** it.
2. **A database password exists** (in `.env`, outside the web folder), instead of none.
3. **A shared machine:** the books sit on the same computer as other customers' sites; speed depends on them too (T8.2 and T9.2 are measured again).
4. **If H-4 fails:** the web app's database user has all rights, so a bug or a leaked password can drop tables. Backups (section 4) are the recovery.

## 6. Books fingerprint (new, proposed)

Why: one partner develops the system and holds the logins. On a VPS the database itself made silent changes impossible. On shared hosting it cannot, so the other partner, and FBR, need another way to see that past entries were not changed.

1. Every journal entry and every audit row stores a fingerprint (a SHA-256 hash) made from its own data **and the fingerprint of the row before it**. Changing, adding or removing any old row breaks every fingerprint after it.
2. The daily `zb:verify` recalculates the chain and alerts when it breaks.
3. **At every month-end close**, the system shows and prints the month's closing fingerprint (a short code) on the month-end close record. **The other partner keeps that code outside the system** (printed and signed, or sent to them by message).
4. Anyone can later re-check a month: the system recalculates the fingerprint up to that month's close and compares it with the code the partner holds. A direct database change shows up as a mismatch, even if the person also changed the check code, because the partner's copy is outside their reach.

New interface words (glossary) and a new rule ID come in part 2.

## 7. Exact money and accurate queries on MySQL/MariaDB

**Goal:** the same answer to the paisa, the same rows in the same order, for every save, check and report, as on PostgreSQL today. MySQL/MariaDB can match PostgreSQL for money (`DECIMAL` is exact), but it is not stricter, and some of its differences give a **wrong answer without any error**. Found in this code:

| # | Where | PostgreSQL today | MySQL/MariaDB, unchanged | Fix |
| --- | --- | --- | --- | --- |
| Q-1 | `Ageing.php` (days to pay): `completed_on - sale_date` | Days between the dates | Subtracts the dates as numbers (`20261101 - 20261031 = 70`, not 1): days to pay silently wrong | `DATEDIFF()` |
| Q-2 | `TrialBalance.php` with parties: sorted by party code | Lines without a party come **last** | They come **first**: the trial balance changes order | Write the NULL order explicitly in every sort on a column that can be empty |
| Q-3 | `AuditTrail.php` filters: `ilike` | Case-insensitive search | Not supported (error) | `LIKE` with the case-insensitive collation of that column |
| Q-4 | `Periods.php`: shared and exclusive advisory locks for month close | Held until the save ends | MySQL has no shared named lock, and its named locks outlive the save | Shared and exclusive row locks on one lock row per company (`FOR SHARE` / `FOR UPDATE`), released with the save |
| Q-5 | Every save | "Read committed": each statement sees the latest saved data | Default "repeatable read": a save keeps seeing the data as it was at its first read | Set the connection to `READ COMMITTED` |
| Q-6 | Unique rules on names and codes, for example `(company_id, name)` | "Aslam", "aslam" and "Aslam " are different | The same (case and trailing spaces ignored): different duplicate rules | Binary collation on columns with unique rules and on keys; case-insensitive collation only on search columns |
| Q-7 | 20 `timestampTz` columns | Time zone stored with the time | No such type | `DATETIME`, always UTC; the connection time zone set to `+00:00` |
| Q-8 | 2 partial unique indexes (opening balances) | `UNIQUE … WHERE … IS NOT NULL` | No partial indexes | Plain unique index (MySQL allows many empty values in a unique index); a test proves the same behaviour |
| Q-9 | 50 `CHECK` rules | Always enforced | Enforced only from MySQL 8.0.16 / MariaDB 10.2; older versions ignore them silently | The check script tests it; the system refuses to start on an older version |
| Q-10 | Money columns `DECIMAL(16,2)`, kg `(14,3)`, rate `(14,4)` | Too many decimals: error | Rounded silently | The app refuses amounts with too many decimals before saving (`Decimal` helpers); tests for every money, kg and rate field |
| Q-11 | `::numeric(16,2)::text` casts in 22 files, `FILTER (WHERE …)`, `to_char`, PostgreSQL error codes | PostgreSQL only | Error | `CAST(… AS DECIMAL(16,2))`, `CASE WHEN`, `DATE_FORMAT`, MySQL error codes |
| Q-12 | Comparing a key with a value of another type | Error | Text is turned into a number (`'12abc' = 12` is true) | Every key compared with a value of its own type; a guard rule for raw SQL |

**Fixed settings on the database connection:** strict mode (Laravel `strict`), `ONLY_FULL_GROUP_BY`, InnoDB only, `utf8mb4`, `READ COMMITTED`, UTC.

**How we prove it before PostgreSQL is removed (in T10.3):**
1. All 588 existing tests and AT-1 to AT-6 pass on the **exact** MySQL/MariaDB version Hostinger runs.
2. **Same-answer check:** the same test data is loaded into PostgreSQL (today's code) and MySQL (new code). Every report, khata, account statement, trial balance, ageing, PDF data and dashboard number is produced from both and compared line by line. Any difference stops the change until it is explained and fixed.
3. Only then are PostgreSQL and its code removed.

## 8. Security (standard for business websites on shared hosting)

**In the app: kept exactly as built** (it is code, not server): server-side permission checks on every route (T-09), two-step login, password rules, login limits, CSRF tokens, secure session cookies, CSP with nonce and the other security headers, company scope on every query, uploads checked and served only through the app, no personal data in logs, dependency audits in CI.

**On the hosting account (the standard checklist for shopping and business sites):**

1. Two-step login on the Hostinger account; the account owned by the business (D-10).
2. HTTPS only (Hostinger's free certificate), HSTS from the app.
3. The app, `.env` and uploads **outside** `public_html`; only the `public` folder is on the web.
4. `APP_DEBUG=false`; error pages without details.
5. SSH with a key only; no FTP accounts.
6. Remote database access off; phpMyAdmin only through hPanel.
7. Hostinger's malware scanner on; PHP kept on the newest supported version.
8. Uptime and backup alerts to both partners.
9. Off-site encrypted backups (D-4). Shopping sites keep copies outside the host for the same reason: a lost or hacked account takes the host's own copies with it.

The books fingerprint (section 6) is not extra security: it is what keeps the books provable once the database can no longer refuse changes, so it stays.

## 9. Proposed design decisions (confirmed or changed after step 1)

| # | Decision | Proposal | Depends on |
| --- | --- | --- | --- |
| D-1 | Database | The exact MySQL/MariaDB version Hostinger runs, in tests, CI, cloud sessions and the laptop, with the settings in section 7. Never SQLite | Check §5, H-7 |
| D-2 | Database rules | Triggers for "refuse edit/delete" and the seal-row balance check (section 4). If triggers are refused: code only, and the fingerprint becomes the only proof | Check §5 |
| D-3 | Database users | Two users if H-4 allows: one that changes tables (used only when installing updates), one for the app without the right to drop tables or triggers | H-4 |
| D-4 | Backups | Hourly encrypted off-site backups from PHP (section 4); Hostinger's daily backups as an extra copy; a restore command for staging and the laptop | Storage approval, check §1 and §4 |
| D-5 | Books fingerprint | Section 6 | Approval |
| D-6 | Installing updates | The CI release archive, uploaded over SSH; a `releases/` folder per version and a link to the live one, so roll-back is one command; database changes run over SSH | H-3, check §3 |
| D-7 | Scheduler | One hPanel cron job every minute: `php artisan schedule:run` | H-2, check §2 |
| D-8 | Security headers | All headers set by the app | None |
| D-9 | Staging | A second subdomain on the same plan, its own database, test data only | H-3 |
| D-10 | Access | The Hostinger account belongs to the business, owned by the other partner with two-step login; the developer gets their own access. One copy of the backup private key and the `APP_KEY` with each partner (O-37) | H-6 |

## 10. What changes

**Protected files (you change them in the part 2 `[spec-change]` pull request):** `CLAUDE.md`, `START-HERE.md`, `README.md`, `.env.production.example`, `docs/spec.md` (D-41, §18, change log, new rule IDs), `docs/requirements.md`, `docs/conventions.md`, `docs/glossary.md`, `docs/stack-baseline.md`, `docs/architecture.md`, `docs/security.md`, `docs/environments.md`, `docs/data-handling.md`, `docs/secrets.md`, `docs/backend.md`, `docs/traceability.md`, ADR-0002, 0003, 0004, 0006, 0007 and 0008 (marked replaced, new ADRs added), `docs/cards/M8.md` and a new `docs/cards/M10.md`, `docs/operations/*`, `infra/` (VPS scripts kept as history or removed), `scripts/check-all.sh`, `scripts/cloud-session-start.sh`, `scripts/setup-local.sh`, `scripts/guard.sh`, `.github/workflows/*`.

**Existing tests that change (needs your approval):** the 5 tests that expect the database to refuse a change (same behaviour, MySQL error instead of PostgreSQL's), and `tests/Feature/Security/MigratorConnectionTest.php` (separate database users, D-3).

**Code (by Claude, after part 2 is merged):** the 33 migrations, 24 app files with PostgreSQL-only SQL (`::numeric`/`::text` casts, `FILTER (WHERE …)`, `to_char`, `pg_advisory_xact_lock`, error codes), `config/database.php`, `phpunit.xml`, `.env.example`.

## 11. Proposed milestone M10 (cards in part 2)

1. **T10.1** Test database switch: MySQL/MariaDB in cloud sessions, laptop and CI, with the section 7 settings; all tests run there.
2. **T10.2** Migrations for MySQL/MariaDB (Q-6 to Q-10); database rules and the seal-row balance check (D-2); two database users (D-3).
3. **T10.3** Queries: the 24 app files (Q-1 to Q-5, Q-11, Q-12); AT-1 to AT-6 pass with the same numbers; the same-answer check against PostgreSQL (section 7) shows no difference; then PostgreSQL is removed.
4. **T10.4** Books fingerprint (section 6): chain, `zb:verify`, month-end code, re-check page.
5. **T10.5** Headers in the app (D-8).
6. **T10.6** Hourly encrypted off-site backups from PHP, backup status page, restore command (D-4).
7. **T10.7** Shared-hosting deploy and roll-back (D-6), scheduler (D-7), documents in `docs/operations/`.
8. **T10.8** [Human + Claude] Staging subdomain (D-9), first deploy, restore test, speed measured.

**Rough size:** 3 to 5 weeks of work (more than the first estimate, because sections 4 and 6 keep the protections instead of dropping them). The unknowns are the trigger test, H-4, the deploy layout and speed on a shared machine.
