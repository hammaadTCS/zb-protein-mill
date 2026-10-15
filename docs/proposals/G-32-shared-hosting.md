# G-32: Run the system on Hostinger "Unlimited" shared hosting

**Status:** proposal, part 1 of 2. Nothing is built. Part 2 (the exact text for every protected file, as a patch like `G-31-m9.patch`) is written after the account check below, because its results decide the design. **§12 records the implementation decisions of 15 October 2026 and the plan built on them.**

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

---

## 12. Implementation plan, decided 15 October 2026 (developer + Claude)

Status of this section: decisions taken in a working session before the account check (§3). They change §6, §9, §10 and §11 of this proposal exactly where noted. The facts from §3 still decide the design of part 2; nothing here pre-empts them.

### 12.1 Decisions taken (15 October 2026)

| # | Question | Decision | What it changes |
| --- | --- | --- | --- |
| D-a | Does Hostinger replace the VPS, or sit beside it? | **Hostinger replaces the VPS.** Shared hosting is the only target; the VPS is not built. | Part 2 rewrites spec **D-41** and §18 for shared hosting, marks **ADR-0003** replaced by a new ADR, retires (or keeps as history) the VPS-only parts of `infra/` (nginx, php-fpm, certbot, systemd timers, PostgreSQL grants), and rewrites **T8.3**, **T8.6** and **T8.7** as shared-hosting tasks. `docs/traceability.md` row D-41 changes with it. |
| D-b | Books fingerprint (§6, task T10.4)? | **Dropped entirely.** No fingerprint columns, no chain, no closing codes, no re-check page; nothing is added to the app for it. | §6 is not built; T10.4 does not exist (see 12.3). The books' protection rests only on the database rules/triggers (**D-2**) and two database users (**D-3**). Because of this, the other partner's written approval of §5 must now say plainly: *someone with the Hostinger login can change the database directly and leave no trace of that change.* §5's loss #1 stays unmitigated and is accepted. |
| D-c | The six existing tests named in §10? | **Approved to adapt, on the condition that the behaviour stays identical**: the save is refused, the row stays unchanged, and the refusal is still tested under the same rule IDs. | The five refusal tests assert MariaDB's error text instead of PostgreSQL's. `MigratorConnectionTest` is rewritten against MariaDB grants — and if **H-4** turns out to allow only one all-rights database user, the developer is asked again **before** that test is deleted, because that deletion would record a lost protection. Nothing else in the 588 tests changes. |
| D-d | Which engine runs the proofs? | **The exact version the host runs (H-7), whatever it takes**: the official apt repository if the distro package is a different version, otherwise a local container of that image; the distro package only if it already matches. | PostgreSQL stays installed and stays the default on the laptop until the same-answer check in **T10.3** passes. Applies to tests, cloud sessions and CI as §9 D-1 says. The CI, `phpunit.xml`, `.env.example`, `scripts/cloud-session-start.sh` and `scripts/guard.sh` changes are part 2 (protected files). |

### 12.2 Phases and gates

| Phase | What | Gate before it |
| --- | --- | --- |
| 0 | The account check of §3, plus the partner's written approval of §5 (now including D-b's sentence) | None — this is the first thing |
| 1 | **G-31** applied first (its `[spec-change]`), then **part 2** of this proposal as one `[spec-change]` pull request: every protected file in §10, the M10 card, the ADR changes, the six approved test changes | Phase 0 facts |
| 2 | Build, one branch and one pull request per task, in order: **T10.1 → T10.2 → T10.3 → T10.4 → T10.5 → T10.6 → T10.7** (renumbered, see 12.3) | Part 2 merged |
| 3 | Go-live on shared hosting: **T8.4** client review on staging, **T8.5** trial month alongside the Urdu register, **T8.6** go-live, **T8.7** handover — all rewritten for shared hosting by D-a | Phase 2 done; restore test on staging recorded in `docs/release-checks.md` |

**No data-migration step exists:** nothing is live today (T8.3–T8.7 unticked), so the first real entry is typed on Hostinger and no book ever changes database engine with real data in it.

### 12.3 Milestone M10 as it will be written in part 2

The fingerprint task of §11 (**T10.4**) is removed by D-b. The remaining tasks keep their content and shift down by one number:

| New ID | Was | Content |
| --- | --- | --- |
| T10.1 | T10.1 | Test database switch: the exact host version (D-d) in tests, cloud sessions and CI; section 7 settings |
| T10.2 | T10.2 | Migrations (Q-6 to Q-10), rules and the seal-row balance check (D-2), two database users (D-3); the six approved test adaptations (D-c) |
| T10.3 | T10.3 | Queries (Q-1 to Q-5, Q-11, Q-12); AT-1 to AT-6 pass with the same numbers; the same-answer check against PostgreSQL shows no difference; PostgreSQL then removed |
| T10.4 | ~~T10.5~~ | Headers in the app (D-8) |
| T10.5 | ~~T10.6~~ | Hourly encrypted off-site backups from PHP, backup status page, restore command (D-4) |
| T10.6 | ~~T10.7~~ | Shared-hosting deploy and roll-back (D-6), scheduler (D-7), `docs/operations/` documents |
| T10.7 | ~~T10.8~~ | [Human + Claude] Staging subdomain (D-9), first deploy, restore test, speed measured (T8.2 and T9.2 re-measured on the shared machine) |

The tasks are added to `docs/tasks.md` in part 2, as with every other milestone.

### 12.4 Cost, checked 15 October 2026

Prices are the hosts' public pages on that day; the account's own invoice decides the real number.

| Item | Cost | Note |
| --- | --- | --- |
| Hostinger **Unlimited** plan | Already owned. If bought new: intro ≈ **$3.99/month** on a 48-month term; renewal ≈ **$16.99–18.99/month** (48-month renewal commonly quoted ≈ $911.52) | G-32 §1: the account already exists |
| Domain, HTTPS, cron | **$0** (free certificate; hPanel cron every minute; domain with the plan) | H-2, §8 |
| Off-site encrypted backups | Object storage ≈ **$6–6.95 per TB/month**, first 10 GB free (Backblaze B2), egress free up to 3× stored | A mill's books are tens of MB a year → realistically cents, likely $0 |
| VPS | **$0 — not taken** (a VPS would be ≈ $6–13/month) | The point of D-a |
| New PHP packages | **$0**: `sodium` ships with PHP; uploads use the HTTP client already present. Any SDK would be a new package and needs approval | D-4 |
| Development | **3 to 5 weeks** (§11 estimate, now without T10.4) | Claude builds branches and proposes protected-file text; never a server |
| Your time | ≈30 minutes for §3; staging deploy and a restore test; the trial month (T8.5); go-live and handover | T8.4–T8.7 |

### 12.5 What stays identical, and what cannot (with D-b in force)

**Identical:** every screen, form, report, khata, statement, PDF, dashboard, permission check, audit row, month-end close and approval; money exact to the paisa (`DECIMAL(16,2)`, kg `(14,3)`, rate `(14,4)`, strings in JSON); AT-1 to AT-6 with the same numbers; all 588 tests except the six approved by D-c; server-side permissions, two-step login, CSP, login limits, company scope, uploads through the app, no personal data in logs.

**Cannot stay identical — written here so nobody is surprised:** database error wording (the five refusal tests); speed on a shared machine (T8.2 and T9.2 are measured again on the real machine in T10.7); a database password in `.env` instead of peer login (§5 #2); and, by D-b, **direct database edits are possible and leave no trace** — only the daily `zb:verify` and the off-site backups limit the damage they can do. If H-4 fails, a leaked database password could drop tables and backups are the only recovery.

### 12.6 Phase 0 sheet (about 30 minutes, human, before part 2 is written)

1. hPanel → Databases: create an **empty test database** and user (any names).
2. hPanel → Advanced → SSH Access: turn SSH on; copy `docs/proposals/G-32-hostinger-check.sh` to the account; run `bash G-32-hostinger-check.sh`; paste the whole output into the Claude session. **Do not commit it** — it contains account and folder names. Delete the test database afterwards.
3. Answer from hPanel: **H-1** highest PHP version; **H-2** can a cron job run every minute; **H-3** can `accounts.zbgroup.website` have a folder of its own, and can it be outside `public_html`; **H-4** can one database have two users with different rights; **H-5** how many days of daily backups, and can they be downloaded; **H-6** who owns the account, is two-step login on, can a second person get their own access; **H-7** MySQL or MariaDB, and its version.
4. Send the other partner the §5 losses and ask for a written reply (e-mail or message, enough for spec §20.3) that now includes, because of D-b: *someone with the Hostinger login can change the database directly and leave no trace of that change.*

### 12.7 Who does what

Claude: branches, code, migrations, tests, the same-answer comparison, backup and deploy scripts, and the exact proposed text for every protected file in part 2. Claude never connects to Hostinger, never pushes or merges, never tags, never edits a protected file.

The developer: Phase 0, hPanel and SSH steps, the account ownership and two-step login (H-6, D-10), the storage account and the backup keys (D-4), staging and production deploys, the restore test, merging the pull requests, and the client-facing tasks T8.4 to T8.7.

### 12.8 Phase 0 results (account check run 15 October 2026; the report itself is not committed — it is in the session log)

The script reported *"nothing for you to fix"*. What each fact decides for part 2:

| Fact from the check | What it decides |
| --- | --- |
| **MariaDB 11.8.9** (`11.8.9-MariaDB-log`) | **H-7 answered.** D-d is now concrete: 11.8.x in tests, cloud sessions, CI and the laptop. CHECK rules are enforced on this version (tested: yes), so Q-9's floor is met |
| **Triggers allowed; all 7 ledger-protection tests passed** (balanced entry posts; unbalanced, changed or deleted entries and lines refused) | **D-2 keeps its strongest form**: the database itself still refuses unbalanced entries, edits and deletes, exactly as on the VPS. This was the biggest unknown and it is resolved well |
| Grants: one user, `ALL PRIVILEGES` on its own database only (`GRANT USAGE ON *.*` + all on `<db-user>.*`) | **H-4 evidence: failed.** No limited second user on this account — §5 loss #4 applies (a leaked database password could drop tables; backups are the recovery), and the D-c condition is triggered: `MigratorConnectionTest` would be deleted with a written note, with the developer's second approval first |
| `sql_mode` has no `STRICT_*`, `innodb_strict_mode = OFF` | **NOTE, load-bearing:** strict mode must be set on the connection itself (Laravel `strict`) and the app-level decimal refusal of Q-10 carries the "too many decimals" guarantee, not the server |
| `READ-COMMITTED` already the server default; system time zone UTC; `explicit_defaults_for_timestamp = ON`; `lower_case_table_names = 0`; InnoDB; `utf8mb4` / `utf8mb4_unicode_ci` | Q-5, Q-7 settings are already half in place; unique columns still need a binary collation per column (Q-6) and the connection settings are still set explicitly in code, because defaults are not a promise |
| `mariadb-dump` (client 10.20) works; a dump taken, restored, **triggers came back and still blocked changes** | T10.5's backup/restore route exists. Dumps contain `DEFINER` clauses — the restore command must strip or handle them when restoring to a different server/user (T10.5 design item) |
| PHP **8.5.11** on the command line and on the website (LiteSpeed + hcdn), opcache on, memory 2048M, execution time 360 s, upload/post 2048M | **H-1 answered**: the exact PHP the project requires. The 3-month statement PDF (2.7 s on the laptop) has ample headroom |
| Symlink switching works from SSH and the website follows the link; the app can live outside `public_html` | **D-6 confirmed**: `releases/<version>` + one symlink is possible. H-3 (the folder for `accounts.zbgroup.website` and the staging subdomain) is still an hPanel answer |
| CLI disables `exec`, `system`, `shell_exec`, `popen` — but `proc_open` works | The backup job calls `mariadb-dump` through Symfony Process (`proc_open`), never `exec` |
| `crontab` binary absent | Irrelevant: the scheduler is hPanel cron (D-7); H-2 (every minute) still to be confirmed in hPanel |
| Reachable: Backblaze B2 (401 = reached), GitHub (200), healthchecks.io (301) | D-4 off-site backups, downloading the CI release archive, and an outside uptime monitor are all possible |
| `MAX_USER_CONNECTIONS 75`, `wait_timeout 20`, `innodb_lock_wait_timeout 50`, `MAX_STATEMENT_TIME 120` | Small but adequate for one company; noted for the T10.7 speed re-measurement. The 120 s statement ceiling is far above any page's 3 s target |

**Still open after the script** (hPanel, ~10 minutes): **H-2** (cron every minute), **H-3** (subdomain folder, outside `public_html`), **H-4** confirmation (can a second user with fewer rights be added to one database?), **H-5** (daily backups: how many days, downloadable?), **H-6** (account ownership, two-step login, a second person's access). Then: the throwaway database is deleted in hPanel, and the other partner's written §5 approval arrives — which must now carry both sentences: a direct database edit leaves no trace (D-b), and one all-rights database user means a leaked password could drop tables (§5 #4, this check).
