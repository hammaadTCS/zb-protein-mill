# Back end: how it is built, and why the data is safe

A summary for anyone working on the server side. It collects the back-end rules from `docs/spec.md` (§5–§10, §12–§14, §18), `docs/conventions.md`, `docs/architecture.md`, `docs/data-handling.md` and the ADRs. **If this page and those documents ever disagree, they win** and the conflict must be reported.

## 1. Shape of the system

```text
Browser (React pages via Inertia)
  -> Controller (thin): validates field shapes, checks permission
  -> Action class: ONE database transaction, all or nothing
       -> business tables (purchases, sales, payments, expenses ...)
       -> PostingService: debit = credit, or nothing is saved -> ledger (never edited)
       -> audit log (every change, permanent)
Reports: money from the ledger, kg from the business tables
```

- **One Laravel 13 application and one MariaDB database** (ADR-0001), split into modules under `app/Domain/` (Parties, Items, MoneyAccounts, Purchases, Sales, Adjustments, Payments, Expenses, Partners, Transfers, OpeningBalances, Ledger, Periods, Checks, Reports, Insights, Settings, Audit, Numbering). Folder list: spec §6.
- **Thin controllers, fat Actions.** Every use case is one Action class with one `handle(User $user, ...)` method (`CreatePurchase`, `AddPurchaseRate`, `CancelPurchase` …). A future mobile API can reuse the same Actions (NFR-09).
- **No queue worker, no Redis** (ADR-0004): `QUEUE_CONNECTION=sync`. Scheduled jobs (the daily integrity check, `zb:backup`, the health check) run from the Laravel scheduler, started every minute by **one hPanel cron job** on the host (`php artisan schedule:run`, D-7).
- **Every business record has a `company_id`**, and every query is filtered by the user's company automatically (BR-GEN-01). This leaves room for a second plant later (NFR-10).

## 2. What happens on every save (conventions §2)

Inside **one** `DB::transaction()`, in this order:

1. All required settings are filled (BR-GEN-07).
2. The entry date is in an open month and not in the future (BR-GEN-02).
3. For creates: has this `idempotency_key` been saved already? If so, return that entry and stop (BR-GEN-11).
4. Business rules and warnings (spec §10).
5. Next voucher number, taken with a row lock (BR-GEN-05).
6. Save the business record.
7. `PostingService::post(...)` if it has an amount and is approved.
8. Write the audit log row.

If any step fails, **everything rolls back**: no half-saved record, no used voucher number, no unbalanced journal. The page then shows the voucher number in a success message.

## 3. Accounting core

- **Double entry.** Every money event becomes one journal entry whose debits equal its credits. The 28 posting rules (PR-01 to PR-28, spec §8.3) are the only ways money is posted. Each rule is a named builder in `App\Domain\Ledger\PostingRules`.
- **One writer.** Only `PostingService` creates journal entries and lines. The models refuse to be created any other way (conventions §6).
- **Balanced, twice.** PHP checks debit = credit. At the end of the same transaction `PostingService` writes the entry's **seal row** into `journal_seals`, and a `BEFORE INSERT` trigger on that table re-sums the entry's lines and refuses the whole save when they are fewer than two or debit ≠ credit (BR-LED-03, BR-LED-11). This is the MariaDB replacement for the deferred constraint trigger (D-45, ADR-0016).
- **Party sub-ledger.** Every line on a control account (1200 Buyers, 1300 Employees, 2000 Suppliers, 2100 Other, 3000/3100 Partners) carries the party, so every khata adds up to its control account (BR-LED-04).
- **Never edited, never deleted.** An edit is a reversal (PR-25) plus a new journal; a cancel is a reversal plus a reason. Closed months are locked; late changes go in the open month as corrections (BR-LED-08, BR-LED-09, BR-MEC-07).
- **Rate decided later.** A rate-pending purchase or sale creates no journal until the rate is added (BR-LED-06).
- **Reports:** money comes only from journal lines (by `posting_date`); kg comes only from purchases and sales (by `entry_date`). Cancelled entries and payments awaiting approval are excluded (spec §12.1).

## 4. Money, kg and rate are exact

| Layer | Rule |
| --- | --- |
| Database | `DECIMAL(16,2)` money, `DECIMAL(14,3)` kg, `DECIMAL(14,4)` rate (`numeric` is MariaDB's synonym for `decimal`) |
| PHP | `Brick\Math\BigDecimal` through `App\Support\Decimal` only, rounding `HALF_UP`, at the step set by `amount_rounding` |
| JSON | always strings |
| Forbidden | `float`, `(float)`, `round()`, `number_format()`, and `+ - * /` on money strings (NFR-06). `scripts/guard.sh` searches for these |

## 5. Database design and protections (spec §9, ADR-0002 replaced by ADR-0016, ADR-0007 replaced by ADR-0014)

- 29 tables plus the permission tables; exact names and columns in spec §9. Common columns: `id`, `company_id`, `created_by`, `updated_by`, timestamps in UTC (`DATETIME(3)`).
- Transaction tables also carry `voucher_no`, `entry_date`, `status`, cancel fields, `journal_entry_id` and `idempotency_key` (unique per company).
- **Connection settings enforced in code** (`config/database.php`, conventions §14a): the connection is `mysql` (MariaDB), with `READ COMMITTED` isolation, a strict `sql_mode` that includes `ONLY_FULL_GROUP_BY`, `time_zone = '+00:00'`, engine `InnoDB` and `utf8mb4`/`utf8mb4_unicode_ci`. They are set by the app on every connection, never trusted from the host, whose own server defaults have no strict mode and no `ONLY_FULL_GROUP_BY`.
- **Raw SQL is MariaDB only** (`scripts/guard.sh` fails on the PostgreSQL-only forms, conventions §14b): `CAST(… AS DECIMAL(…))` not `::numeric`, `LIKE` not `ilike`, `CASE WHEN` not `FILTER (WHERE …)`, `DATE_FORMAT` not `to_char`, `DATEDIFF()` never date subtraction, `FOR UPDATE`/`LOCK IN SHARE MODE` on a `company_locks` row not `pg_advisory_xact_lock`, MariaDB error `1062` not SQLSTATE `23505`, and every key compared only with a value of its own type.
- **Database triggers:**
  - block UPDATE and DELETE on journal entries, journal lines, audit logs, rate changes and period events;
  - block DELETE on every master and transaction table;
  - a `BEFORE INSERT` trigger on `journal_seals` that re-sums the entry's lines and refuses the save when they are fewer than two or debit ≠ credit (BR-LED-11, D-45).
- **One database user** with all rights on its own database only (ADR-0014): the host cannot make a second, lower-rights user, so the VPS's three-role split is not possible. The password is a strong random value in `.env` outside the web folder and is never in the repository, CI or a Claude session (BR-SEC-12). Because that one user can in principle change or drop the protections, the compensating controls are the daily `zb:verify` books check (BR-OPS-08), the off-site backups (BR-OPS-01) and the paper register kept by the other partner. Trigger creation depends on `trust_function_creators` and the binary log staying off, and `zb:verify` refuses to run when a protection trigger is missing.
- **Startup check** (`zb:db-check`, run on boot and by `zb:verify`, T10.1): the system refuses to run when the server is MariaDB older than 10.2, when a table is not InnoDB, when the session `sql_mode` has no strict mode, when the time zone is not `+00:00`, or when a protection trigger is missing. The message names the failing item.
- **Two people editing the same entry:** the second save is refused with "This entry was changed by another user. Reload." (BR-GEN-09).
- **Migrations always work with the previous release** (BR-OPS-10): add first, stop using later, remove last. A rollback switches code only, never the database.

## 6. Users, permissions and security (spec §7, §14.2)

- **Roles:** Owner and Accountant, with fixed permission lists (spec §7), stored with `spatie/laravel-permission`. Every route and action checks a permission key on the server (BR-SEC-05). Code never checks `hasRole('owner')`.
- **Login:** Fortify from the kit:
  - 12+ character passwords with a breach check;
  - 5 tries a minute;
  - two-step login available (required for Owners when O-31 = yes);
  - 60-minute idle timeout;
  - no self-registration and no self-service reset (BR-USR-06).
- **Accounts:**
  - the first Owner is created on the server with `zb:create-owner`;
  - new users must change the temporary password the Owner sets;
  - disabling a user, changing a role or resetting a password ends that user's other sessions at once (BR-SEC-13).
- **Uploads:**
  - JPG, PNG, WEBP or PDF up to 5 MB, checked by file content;
  - images re-encoded on the server;
  - random names on a private disk, served only through a permission-checked route (BR-SEC-06).
- **Headers and cookies:** HSTS, frame denial, nosniff, a Content Security Policy with a nonce, `noindex`; the session cookie is Secure, HttpOnly and SameSite=Lax (BR-SEC-14, BR-SEC-15).
- **Logs:** never passwords, tokens, request bodies, party names, mobiles, bank details or amounts. They hold only the record type and ID (BR-SEC-17).
- **Configuration:** `env()` only inside `config/`. Secrets live only on the server (`docs/secrets.md`), never in the repository.
- **No outgoing calls** except the kit's password breach check.

## 7. Audit trail (spec §14.1)

Every login, failed login, create, edit (old and new value of each changed field), cancel, rate change, approval, merge, settings change, user change, month close or reopen, confirmed warning and server command is written to `audit_logs` with user, time, IP address and device. Audit rows cannot be changed or deleted (database trigger). The Owner sees the full log; each detail page shows that record's own history.

## 8. Why data is not lost and not wrong

This section answers: "Is every entry saved, every time, and can we get it back?"

| Risk | What protects the data | Rule |
| --- | --- | --- |
| A save half-finishes (power cut, crash, error) | One transaction per save: all or nothing | conventions §2 |
| A double tap or retry on a slow phone saves twice | One-time `idempotency_key` per form; a repeat returns the first entry | BR-GEN-11, ADR-0011 |
| Two users overwrite each other | The second save is refused | BR-GEN-09 |
| Someone deletes or edits history | Nothing is ever deleted; corrections are reversals; database triggers refuse deletes and journal edits. The one database user could in principle drop the triggers, so `zb:verify` re-checks every day and the off-site backups and paper register are the compensating controls | BR-GEN-03, BR-SEC-12, ADR-0016 |
| A bug posts an unbalanced or wrong journal | PHP check plus the seal-row trigger; acceptance tests with fixed numbers on every change | BR-LED-03, BR-LED-11, AT-1 to AT-6 |
| Something wrong slips through anyway | `zb:verify` runs every day: journals balance, every seal row matches its entry, trial balance equal, voucher numbers without gaps, khatas add up, and the database settings are right. Any failure alerts you | BR-OPS-08 |
| Who changed what | Permanent audit log | BR-AUD-01, BR-AUD-02 |
| The host dies, is hacked or is deleted | **Encrypted database and uploads backup daily**, with a monthly long-keep copy, stored **off the host** with a key that **cannot delete** them. The host cannot read its own backups (public-key encryption; the private key is kept offline by two people) | BR-OPS-01 to 03, ADR-0015 |
| A backup that does not restore | A restore is tested on staging **every month**, and a full rebuild drill is done before go-live and yearly | BR-OPS-04, BR-OPS-05 |
| How much can be lost | At most **1 day** of entries; running again on a new host within **4 hours** (owner's decision, O-36) | BR-OPS-05 |
| A failed backup nobody notices | Each backup pings a monitor; a missing or failed ping alerts within 26 hours; the Backup status page shows the last success and the last error | BR-OPS-07 |
| A bad release | Database backup before every deploy; automatic switch back if the health check fails; one-command rollback | BR-OPS-10, ADR-0005 |

The data on your laptop is **test data only**, so it needs no backup. Your code lives on GitHub: every merged pull request is stored there.

Backups are the application commands `zb:backup` (dump → gzip → encrypt with **sodium** to the backup public key → upload with the upload-only key) and `zb:restore` (download → decrypt → load; used on staging for the monthly restore test). Both are driven by the scheduler and documented in `docs/operations/`.

## 9. System data and seeders (BR-OPS-11)

| Seeder | Runs where | What it does |
| --- | --- | --- |
| `SystemDataSeeder` | Every host deploy, right after the migrations | Adds what is missing: the company, every settings key (OPEN values stay empty), roles and permissions (exactly spec §7), system ledger accounts (spec §8.1) and the four items. Never changes saved data, never creates users or test data |
| `DatabaseSeeder` | Cloud sessions, laptop and tests only | Refuses to run in production. Calls `SystemDataSeeder`, the test settings and the test user `test@example.com` (Owner role, local only) |
| `TestSettingsSeeder`, `DemoSeeder`, `PerformanceSeeder` | Cloud sessions, laptop and tests only | Test values (conventions §11), demo data from AT-1, a large performance data set. All refuse production |

The first real user is created on the server with `php artisan zb:create-owner`.

## 10. Tests and checks

| Kind | Where | What |
| --- | --- | --- |
| Feature and unit tests | `tests/Feature/<Module>/`, `tests/Unit/` | Pest 4 on **MariaDB 11.8** (never SQLite, never PostgreSQL). Each test name starts with its rule ID. Money compared as exact strings |
| Acceptance tests | `tests/Acceptance/` | Load `docs/acceptance/*.json` and run every entry through the real Actions. Expected numbers are never copied into code |
| Architecture tests | `tests/Arch/` | From T0.3: only the Ledger, Reports and Insights modules touch journal models, only Ledger code and Actions call PostingService, controllers never use `DB` or build journal lines, no `floatval`/`round`, strict types, no soft deletes, no debugging helpers |
| Static analysis and style | Larastan level 7, Pint | Every task |
| Guards | `scripts/guard.sh`, `scripts/secret-scan.sh` | Forbidden patterns (including PostgreSQL-only SQL, conventions §14b), secrets |
| Database settings and rules | `zb:db-check` (part of `zb:verify`), the schema-refusal tests | The server is MariaDB 11.8, every table is InnoDB, the session settings are strict/UTC, every protection trigger is present, and an unbalanced entry or an edit/delete is refused |
| Everything at once | `bash scripts/check-all.sh` | Same order as CI |

Rule for everyone: **never change or delete an existing test to make it pass.** A failing old test means the new code is wrong or a rule changed through a `[spec-change]`.
