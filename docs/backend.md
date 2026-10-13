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

- **One Laravel 13 application and one PostgreSQL database** (ADR-0001), split into modules under `app/Domain/` (Parties, Items, MoneyAccounts, Purchases, Sales, Adjustments, Payments, Expenses, Partners, Transfers, OpeningBalances, Ledger, Periods, Checks, Reports, Insights, Settings, Audit, Numbering). Folder list: spec §6.
- **Thin controllers, fat Actions.** Every use case is one Action class with one `handle(User $user, ...)` method (`CreatePurchase`, `AddPurchaseRate`, `CancelPurchase` …). A future mobile API can reuse the same Actions (NFR-09).
- **No queue worker, no Redis** (ADR-0004): `QUEUE_CONNECTION=sync`. Scheduled jobs (the daily integrity check) run from the Laravel scheduler, started every minute by a systemd timer on the server.
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

- **Double entry.** Every money event becomes one journal entry whose debits equal its credits. The 27 posting rules (PR-01 to PR-27, spec §8.3) are the only ways money is posted. Each rule is a named builder in `App\Domain\Ledger\PostingRules`.
- **One writer.** Only `PostingService` creates journal entries and lines. The models refuse to be created any other way (conventions §6).
- **Balanced, twice.** PHP checks debit = credit. A deferred database trigger checks it again before the transaction commits (BR-LED-03).
- **Party sub-ledger.** Every line on a control account (1200 Buyers, 1300 Employees, 2000 Suppliers, 2100 Other, 3000/3100 Partners) carries the party, so every khata adds up to its control account (BR-LED-04).
- **Never edited, never deleted.** An edit is a reversal (PR-25) plus a new journal; a cancel is a reversal plus a reason. Closed months are locked; late changes go in the open month as corrections (BR-LED-08, BR-LED-09, BR-MEC-07).
- **Rate decided later.** A rate-pending purchase or sale creates no journal until the rate is added (BR-LED-06).
- **Reports:** money comes only from journal lines (by `posting_date`); kg comes only from purchases and sales (by `entry_date`). Cancelled entries and payments awaiting approval are excluded (spec §12.1).

## 4. Money, kg and rate are exact

| Layer | Rule |
| --- | --- |
| Database | `numeric(16,2)` money, `numeric(14,3)` kg, `numeric(14,4)` rate |
| PHP | `Brick\Math\BigDecimal` through `App\Support\Decimal` only, rounding `HALF_UP`, at the step set by `amount_rounding` |
| JSON | always strings |
| Forbidden | `float`, `(float)`, `round()`, `number_format()`, and `+ - * /` on money strings (NFR-06). `scripts/guard.sh` searches for these |

## 5. Database design and protections (spec §9, ADR-0002, ADR-0007)

- 27 tables plus the permission tables; exact names and columns in spec §9. Common columns: `id`, `company_id`, `created_by`, `updated_by`, timestamps in UTC.
- Transaction tables also carry `voucher_no`, `entry_date`, `status`, cancel fields, `journal_entry_id` and `idempotency_key` (unique per company).
- **Database triggers:**
  - block UPDATE and DELETE on journal entries, journal lines, audit logs, rate changes and period events;
  - block DELETE on every master and transaction table;
  - a deferred balance check on journal lines.
- **Three database roles** with no passwords. They connect only through the local socket (peer authentication), and PostgreSQL listens on no network address:

  | Role | Used for | Can |
  | --- | --- | --- |
  | `zb_app` | The app | Read, insert, update |
  | `zb_migrator` | Deploys only | Owns the schema; runs migrations and `SystemDataSeeder` |
  | `zb_backup` | Backups | Read only |

  `zb_app` cannot change structure, disable triggers, truncate, or delete business rows (BR-SEC-12). CI proves this on every run from T1.11.
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
| Someone deletes or edits history | Nothing is ever deleted; corrections are reversals; database triggers refuse deletes and journal edits even for hand-written SQL by the app's role | BR-GEN-03, ADR-0002 |
| A bug posts an unbalanced or wrong journal | PHP check plus database trigger; acceptance tests with fixed numbers on every change | BR-LED-03, AT-1 to AT-6 |
| Something wrong slips through anyway | `zb:verify` runs every day: journals balance, trial balance equal, voucher numbers without gaps, khatas add up. Any failure alerts you | BR-OPS-08 |
| Who changed what | Permanent audit log | BR-AUD-01, BR-AUD-02 |
| The server dies, is hacked or is deleted | **Encrypted database backup every hour** and uploads every day, stored **off the server** with a key that **cannot delete** them. The server cannot read its own backups (public-key encryption; the private key is kept offline by two people) | BR-OPS-01 to 03, ADR-0006 |
| A backup that does not restore | A restore is tested on staging **every month**, and a full rebuild drill is done before go-live and yearly | BR-OPS-04, BR-OPS-05 |
| How much can be lost | At most **1 hour** of entries; running again on a new server within **4 hours** (targets the client confirms, O-36) | BR-OPS-05 |
| A failed backup nobody notices | Each backup pings a monitor; a missing ping alerts within 2 hours (database) or 26 hours (uploads); the Backup status page shows the last success | BR-OPS-07 |
| A bad release | Database backup before every deploy; automatic switch back if the health check fails; one-command rollback | BR-OPS-10, ADR-0005 |

The data on your laptop is **test data only**, so it needs no backup. Your code lives on GitHub: every merged pull request is stored there.

## 9. System data and seeders (BR-OPS-11)

| Seeder | Runs where | What it does |
| --- | --- | --- |
| `SystemDataSeeder` | Every server deploy, as `zb_migrator`, right after the migrations | Adds what is missing: the company, every settings key (OPEN values stay empty), roles and permissions (exactly spec §7), system ledger accounts (spec §8.1) and the four items. Never changes saved data, never creates users or test data |
| `DatabaseSeeder` | Cloud sessions, laptop and tests only | Refuses to run in production. Calls `SystemDataSeeder`, the test settings and the test user `test@example.com` (Owner role, local only) |
| `TestSettingsSeeder`, `DemoSeeder`, `PerformanceSeeder` | Cloud sessions, laptop and tests only | Test values (conventions §11), demo data from AT-1, a large performance data set. All refuse production |

The first real user is created on the server with `php artisan zb:create-owner`.

## 10. Tests and checks

| Kind | Where | What |
| --- | --- | --- |
| Feature and unit tests | `tests/Feature/<Module>/`, `tests/Unit/` | Pest 4 on **PostgreSQL** (never SQLite). Each test name starts with its rule ID. Money compared as exact strings |
| Acceptance tests | `tests/Acceptance/` | Load `docs/acceptance/*.json` and run every entry through the real Actions. Expected numbers are never copied into code |
| Architecture tests | `tests/Arch/` | From T0.3: only the Ledger, Reports and Insights modules touch journal models, only Ledger code and Actions call PostingService, controllers never use `DB` or build journal lines, no `floatval`/`round`, strict types, no soft deletes, no debugging helpers |
| Static analysis and style | Larastan level 7, Pint | Every task |
| Guards | `scripts/guard.sh`, `scripts/secret-scan.sh` | Forbidden patterns, secrets |
| Database privileges | CI, from T1.11 | The app's role really cannot delete, truncate or alter |
| Everything at once | `bash scripts/check-all.sh` | Same order as CI |

Rule for everyone: **never change or delete an existing test to make it pass.** A failing old test means the new code is wrong or a rule changed through a `[spec-change]`.
