# ZB Poultry Protein Mill – Accounts System Technical Specification v1.0

Oct 5, 2026 · @Hammaad Ahmad Khalid · update 3 (production readiness): 11 October 2026 · update 4 (corrections G-25 to G-29): 13 October 2026

## 0. Document control

This specification is the single source of truth for building version 1. It turns Requirements v1.2 and every decision from our discussion into exact rules, tables, pages and tests.

| Item | Value |
| --- | --- |
| Document | Technical Specification v1.0 |
| Based on | Requirements and Scope Document v1.2 (11 October 2026; v1.1 of 3 October 2026 plus security, backups and server) |
| Audience | Developer(s) building the system |
| Status | Draft until the client confirms Requirements v1.2 and every OPEN item in Section 4 is closed |
| Currency | Pakistani Rupee (PKR) only |
| Time zone | Asia/Karachi |

### Status labels used in this document

| Label | Meaning | Can it be built? |
| --- | --- | --- |
| **C** | Confirmed by the client (answers to the 10 questions and earlier answers) | Yes |
| **A** | Agreed in our discussion and written into Requirements v1.1 or v1.2 | Yes. Client confirms by signing v1.2 |
| **P** | Proposed in this specification. A technical or detail choice that was not discussed | Yes, after you approve it. Change it freely if you disagree |
| **OPEN** | Not decided. Must not be guessed | Build it as a setting or a blocked step. Fill the value before go-live |

### Rules for using this document

- Every rule has an ID (for example BR-PUR-03). Code comments and tests must reference the ID.
- If the code and this document disagree, the document wins until a new version changes it.
- No value is invented. Where a value is unknown, it is marked OPEN and built as a setting with no default value.
- "Must" means mandatory. "Should" means recommended.
- Changes after sign-off create a new version (v1.1, v1.2) with a change log entry. Old versions are never edited.

**Honest limit:** this document is accurate to everything confirmed so far. The system can only be 100% accurate for the client after the OPEN items in Section 4 are answered by the client.

## 1. Purpose and scope

Version 1 records every rupee coming in and going out, shows who owes whom at any time, and guarantees that debits equal credits, with month-end matching against the real bank statement and cash in hand.

**Goals (A):**

1. Record every purchase, sale, payment, receipt, expense, partner transaction and transfer.
2. Show each party's balance (khata) and each cash and bank balance at any time.
3. Every entry creates balanced debit and credit lines automatically.
4. At month end, system balances match the bank statement and counted cash, then the month is locked.
5. Protect records from mistakes and misuse (no deleting, full history, approvals, alerts).
6. Give the owner rough business indicators from data already recorded.

**Modules in version 1:** Parties, Items, Money Accounts, Purchases, Sales, Agreed Adjustments, Payments and Receipts, Expenses, Partner Money, Transfers, Opening Balances, Ledger, Daily Checks, Month-End Close, Reports, Business Insights, Audit Log, Settings, Users.

**Users in version 1:** Owner and Accountant (C). Roles are stored in the database so more roles can be added later without code changes (A).

Everything not listed here is out of scope (Section 20).

## 2. Business summary

ZB Poultry Protein Mill (the client, C) buys chicken waste and wood, boils the waste (wood is the fuel) into protein product and oil/fat, and sells both. All facts below are confirmed (C) or agreed (A).

| # | Fact | Source |
| --- | --- | --- |
| F-01 | Raw material: chicken waste, bought from many suppliers | C |
| F-02 | Fuel: wood, bought from suppliers | C |
| F-03 | Products sold: protein product (to feed factories) and oil/fat (sold separately) | C |
| F-04 | No middlemen. The person who brings the material is the supplier | C |
| F-05 | Suppliers and buyers are always different people | C |
| F-06 | All quantities are in kg | C |
| F-07 | Rate is entered on every entry. It changes by person and by day | C |
| F-08 | One delivery has one rate | C |
| F-09 | The rate is sometimes decided after delivery | C |
| F-10 | Advances are given to suppliers and received from buyers, sometimes | C |
| F-11 | Deductions (quality, weight, freight) are agreed between both sides, then the final figure is written down | C |
| F-12 | Returns are settled on site. Only the final agreed kg and amount is recorded | C |
| F-13 | Supplier's weight is final for purchases. Buyer's weight is final for sales | C |
| F-14 | Payments are by cash or bank transfer only. No cheques received | C |
| F-15 | Buyers pay the full amount. No tax is deducted by buyers | C |
| F-16 | Wallet money (JazzCash, Easypaisa) goes into the bank, or is spent and recorded as cash | C |
| F-17 | The business pays some transport | C |
| F-18 | The business has no loans, taken or given | C |
| F-19 | Partners invest money and sometimes withdraw money | C |
| F-20 | Expenses include food and office costs. One category list, no office/production split. Every expense needs a note | C |
| F-21 | Petty cash expenses are entered one by one | C |
| F-22 | CNIC is not required for parties | C |
| F-23 | Owner and accountant can both add parties | C |
| F-24 | Stock is not recorded in version 1 | C |
| F-25 | System language is English. Party names may be saved in Urdu | A |
| F-26 | Previous records are kept in Urdu registers. They are not migrated. The system starts from opening balances on a start date | A |

## 3. Decisions register

Every decision below is binding for version 1. The "Rules" column points to the business rules in Section 10 that implement it.

| ID | Decision | Source | Rules |
| --- | --- | --- | --- |
| D-01 | Every entry is linked to a party selected from a list, never typed by hand | A | BR-PTY-01 |
| D-02 | One parties list with types: supplier, buyer, partner, employee, other | A | BR-PTY-02 |
| D-03 | A party is never both supplier and buyer | C | BR-PTY-04 |
| D-04 | Mobile number is required and is the main identifier. No CNIC field | C | BR-PTY-03 |
| D-05 | Owner and accountant can add and edit parties. Owner is alerted when the accountant adds a party or changes bank details | C + A | BR-PTY-06, BR-PTY-07 |
| D-06 | Parties with history are never deleted, only made inactive. Duplicates are merged by the owner | A | BR-PTY-05, BR-PTY-08 |
| D-07 | Items: Waste (raw material), Wood (fuel), Protein product, Oil/Fat (products). Unit kg | C | BR-ITM-01 |
| D-08 | Rate is entered on every purchase and sale. One rate per entry. Rate can be left pending | C | BR-PUR-02, BR-PUR-03 |
| D-09 | Rate-pending entries are not posted to the ledger until the rate is added | A | BR-PUR-04, BR-LED-06 |
| D-10 | Every rate change is logged with old rate, new rate, user and time | A | BR-PUR-09 |
| D-11 | Last rate is shown as a hint, never auto-filled. Unusual rates show a warning | A | BR-PUR-05, BR-PUR-06 |
| D-12 | Payment weight is the supplier's (purchases) or buyer's (sales). Our own weight is optional. Weight slip number and photo are recorded | C + A | BR-PUR-07, BR-SAL-05 |
| D-13 | Agreed deductions are recorded as an adjustment linked to the purchase or sale. The original entry is not changed | C + A | BR-ADJ-01 to BR-ADJ-06 |
| D-14 | No returns module | C | Section 20 |
| D-15 | Payments and receipts: full, partial or advance. Cash or bank transfer, with transfer reference | C + A | BR-PAY-01 to BR-PAY-10 |
| D-16 | No cheque tracking, no tax-deduction field, no wallet accounts, no loans module | C | Section 20 |
| D-17 | Wallet money spent directly is recorded as cash received, then cash expense | A (suggested, client to confirm) | BR-PAY-09 |
| D-18 | Expenses: one simple category list. Note is required on every expense. Entered one by one | C | BR-EXP-01 to BR-EXP-03 |
| D-19 | Expenses can be paid now, unpaid (owed to a party, paid later), or paid by an employee from cash given to them | A | BR-EXP-04 to BR-EXP-07 |
| D-20 | Transport is an expense category. A transport expense can be linked to a purchase or sale | A | BR-EXP-08 |
| D-21 | Partner investments and withdrawals have their own form and never mix with sales or expenses | A | BR-PTN-01 to BR-PTN-04 |
| D-22 | Transfers between cash and bank, or bank and bank, are never income or expense | A | BR-TRF-01 to BR-TRF-03 |
| D-23 | Double-entry ledger under every entry. Debits must equal credits | A | BR-LED-01 to BR-LED-10 |
| D-24 | No deleting. Mistakes are edited or cancelled with reason. Every change is logged | A | BR-GEN-03, BR-AUD-01 |
| D-25 | Daily cash check: counted cash vs system cash | A | BR-CHK-01 to BR-CHK-04 |
| D-26 | Month-end close: pending rates, trial balance, bank match, cash count, lock. Only owner reopens | A | BR-MEC-01 to BR-MEC-09 |
| D-27 | Changes affecting a closed month are posted as adjustments in the current open month | A | BR-MEC-07 |
| D-28 | Large payments, and the first payment to a new party, need owner approval | A (amounts OPEN) | BR-PAY-06, BR-PAY-07 |
| D-29 | Expenses above a limit must have a party selected | A (amount OPEN) | BR-EXP-03 |
| D-30 | Stock not recorded. The monthly report is called "Income vs Spending", never "Profit" | C + A | BR-RPT-08 |
| D-31 | Every entry gets a running voucher number. Numbers are never reused | A | BR-GEN-05 |
| D-32 | Rate-pending age and advance age are highlighted | A (day limits OPEN) | BR-CHK-05, BR-CHK-06 |
| D-33 | Duplicate entry warning: same party, same item, same kg, same date | A | BR-PUR-08 |
| D-34 | Party dropdown shows name, area and mobile | A | BR-PTY-09 |
| D-35 | Entry slip and party statement can be shared as PDF | A | Section 13 |
| D-36 | Business insights: kg ratio, wood per kg, rate gap, rough cost per kg, supplier and buyer comparison | A | Section 12 |
| D-37 | Every record carries a company ID so a second plant can be added later | A | BR-GEN-01 |
| D-38 | Framework: Laravel 13 with the official React starter kit (Inertia, React 19, TypeScript, Tailwind 4, shadcn/ui), PostgreSQL | A | Section 5 |
| D-39 | Server always recalculates totals and balances. The browser only shows them | A | BR-GEN-06 |
| D-40 | Automatic encrypted off-site backups: database every hour, uploads daily (A for automatic backups, P for frequency and method) | A + P | BR-SEC-08, BR-OPS-01 to BR-OPS-05 |
| D-41 | One VPS on Ubuntu 26.04 LTS with Ubuntu's PHP 8.5 and PostgreSQL 18; scripted setup (ADR-0003) | P | BR-SEC-10, §18 |
| D-42 | Releases are built by CI from version tags and deployed by a person; no server credentials in GitHub or Claude; rollback in one command (ADR-0005) | P | BR-OPS-10, §18.2 |
| D-43 | Database access by Unix-socket peer authentication with three roles and no passwords (ADR-0007) | P | BR-SEC-12 |
| D-44 | Forms use Inertia's `useForm`; server validation is the only validation that counts | P | §5, §15.4 |

## 4. Open items register

37 items are not decided yet. None of them blocks development: each one is built as a setting, a choice, or a go-live checklist step. None may be guessed.

**Rule (P):** the system has a Setup Checklist. No transaction can be entered until every required setting below is filled by the owner.

### Values the client must give

| ID | Question | Why it matters | How the system handles it until answered |
| --- | --- | --- | --- |
| O-01 | Payments above what amount need owner approval? | Fraud control (D-28) | Setting `payment_approval_limit`, required, no default |
| O-02 | First payment to a new party: above what amount does it need approval? (Rs 0 = always) | Fake-supplier fraud (D-28) | Setting `new_party_first_payment_limit`, required, no default |
| O-03 | Expenses above what amount must have a party? | Traceability (D-29) | Setting `expense_party_required_limit`, required, no default |
| O-04 | Unusual rate warning: how many % away from normal, compared with how many past entries? | Typing mistakes and inflated rates (D-11) | Settings `unusual_rate_percent` and `unusual_rate_lookback`, required, no default |
| O-05 | Highlight rate-pending entries older than how many days? | Forgotten disputes (D-32) | Setting `rate_pending_age_days`, required |
| O-06 | Highlight advances older than how many days? | Unrecovered advances (D-32) | Setting `advance_age_days`, required |
| O-07 | System start date | All opening balances are as of this date | Setting `system_start_date`, required |
| O-08 | Names and numbers of all bank accounts. One cash account or more than one (for example office cash and plant cash)? | Accounts and daily cash check | Entered in Accounts Setup before go-live |
| O-09 | Opening balance of every bank, cash, supplier, buyer, partner and employee on the start date | Books must start correct | Opening Balances page. Go-live blocked until the owner marks it complete |
| O-10 | Partner names and profit share % | Partner records | Party profile. Stored only. No profit distribution in version 1 |
| O-11 | Starting list of expense categories | Expense entry | Proposed starting list (P): Wages, Food, Tea, Electricity, Vehicle Fuel, Transport/Freight, Repair, Stationery, Mobile/Internet, Rent, Bank Charges, Miscellaneous. Client confirms or edits |
| O-12 | Credit limit and payment terms for each buyer | Credit and overdue warnings | Optional per buyer. Empty = no warning for that buyer |
| O-13 | Number of users at start | Accounts to create | Created by owner in Users page |

### Formats the client must choose

| ID | Question | Why it matters | How the system handles it until answered |
| --- | --- | --- | --- |
| O-14 | Is kg always a whole number, or can it have decimals (for example 812.5 kg)? | Field type and validation | Database stores 3 decimals (P). Setting `kg_decimals` (0 to 3), required |
| O-15 | Is the rate always whole rupees, or can it have paisa (for example Rs 52.50)? | Field type and rounding | Database stores 4 decimals (P). Setting `rate_decimals` (0 to 2), required |
| O-16 | Should totals be rounded to whole rupees or kept to 2 decimals? | Every amount in the system | Setting `amount_rounding` (whole rupee / 2 decimals), required. Rounding method: half up (P) |
| O-17 | Show numbers as 1,00,000 (lakh) or 100,000? | Readability | Setting `number_format`, required |
| O-18 | Date display: 05-10-2026 or 05 Oct 2026? | Readability | Setting `date_format`, required. Stored as ISO date in the database (P) |

### Behaviour the client must confirm

| ID | Question | Suggested answer | How the system handles it until answered |
| --- | --- | --- | --- |
| O-19 | Who records partner investments and withdrawals? | Owner only | Permission `partner.manage`, owner only by default, changeable |
| O-20 | Who closes the month? | Owner only | Permission `period.close`, owner only by default, changeable |
| O-21 | Does the business ever pay suppliers by cheque? | No | Not built. If yes, a change request is needed |
| O-22 | Is oil/fat sold in kg? | Yes | Item unit kg. If not, a change request is needed |
| O-23 | Is all partner money coming in recorded as investment, unless the owner marks it otherwise? | Yes | Built as investment only. No loan type exists |
| O-24 | Wallet money spent directly: record as cash received, then cash expense (D-17)? | Yes | Training rule only, no special feature |
| O-25 | Does the plant have its own scale? | Unknown | Setting `plant_has_scale`. If no, the "our weight" field is hidden |
| O-26 | Are weight slip number and photo mandatory or optional? | Slip number mandatory, photo optional | Settings `slip_no_required`, `slip_photo_required`, required choice |
| O-27 | Can an agreed adjustment ever increase the amount (an extra amount agreed)? | Only reductions were discussed | Built for reductions only |
| O-28 | If a buyer never pays, is a write-off entry needed? | Not discussed with client | Not built. The amount stays visible in pending balances |
| O-29 | Do employees take salary advances or cash for buying things? | Not asked directly | Employee khata supports both. Only training is affected |
| O-30 | Hosting provider and domain name | Your choice with client | Needed before staging setup |
| O-31 | Should the owner use two-step login (code on phone)? | Yes, for the owner | Setting `owner_two_factor_required`, required, no default (BR-SEC-09). Two-step login is available to every user from the start |
| O-32 | Which off-site storage provider for backups? | Any with versioning/Object Lock and keys without delete rights (worked example: Backblaze B2) | Needed before staging. `zb-backup-verify-offsite` must pass |
| O-33 | Which monitoring service, and who receives alerts (phone and e-mail)? | Owner and developer | Go-live blocked until alerts reach both |
| O-34 | Server provider, size and region. Must data stay in Pakistan? | 2 vCPU, 4 GB RAM, 80 GB SSD (P). Data location: ask the client's lawyer | Needed before staging |
| O-35 | How many years must records and backups be kept? | At least 6 years (Income Tax Ordinance s.174(3)); monthly backups kept 7 years (P) | Built for 7 years; client's tax adviser confirms |
| O-36 | Is "at most 1 hour of entries lost, running again within 4 hours" acceptable? | Yes | Built for RPO 1 hour, RTO 4 hours (P) |
| O-37 | Who holds the two copies of the backup private key and the APP_KEY? | Owner (sealed paper) + developer, then a second company person at handover | Go-live blocked until both copies exist |

**Who answers:** O-01 to O-29, O-31, O-35 to O-37 by the client. O-30, O-32 to O-34 by you and the client.

## 5. Technology stack

The system is built on Laravel 13 using the official Laravel React starter kit, with PostgreSQL as the database (D-38). Exact package versions are pinned at install time in `composer.lock` and `package-lock.json`.

### Backend

| Part | Choice | Status | Fact or reason |
| --- | --- | --- | --- |
| Framework | Laravel 13 | A | Requires PHP 8.3 or newer. Bug fixes for 18 months and security fixes for 2 years per release ([Laravel release notes](https://laravel.com/docs/13.x/releases)) |
| Support window | Laravel 13 security fixes until Q1 2028 | Fact | Released 17 March 2026 ([Laravel News](https://laravel-news.com/index.php/laravel-13-released)). Plan an upgrade to the next major version within that window |
| Language | PHP 8.3 minimum; servers run **PHP 8.5** (security support until 31 Dec 2029) | Fact + P | Laravel 13 supports 8.3 to 8.5. Lock file resolved for 8.3 so cloud sessions, the laptop (Linux Mint 22) and the servers share it (ADR-0009) |
| Database | PostgreSQL 18 on servers (16+ supported) | P | Exact `numeric` type, check constraints, triggers for immutability. PostgreSQL 18 is supported until 14 Nov 2030 |
| Login | Laravel Fortify (included in the starter kit) | Fact | Starter kits use Fortify for authentication ([Laravel starter kits](https://laravel.com/docs/master/starter-kits)) |
| Roles and permissions | `spatie/laravel-permission` | P | Roles and permissions stored in the database (A) |
| Money calculations | `brick/money` (or PHP `bcmath`) | P | PHP float numbers are not exact. Never use float for money |
| PDF | `barryvdh/laravel-dompdf` | P | Runs in PHP, no browser or Node.js needed on the server |
| Backups | Server scripts: `pg_dump` + `age` (public-key encryption) + `rclone` (ADR-0006) | P | The server cannot read or delete its own backups. Replaces `spatie/laravel-backup` |
| Audit log | Own `audit_logs` table | P | Full control, write-only, protected by database trigger |
| Tests | Pest 4 (`^4.7`) on PHPUnit 12 | P | Pest 5 needs PHP 8.4 (ADR-0009). Static analysis: Larastan level 7 (in the kit) |
| File storage | Laravel private disk (not public) | P | Slip and receipt photos are served only to logged-in users |

### Frontend

| Part | Choice | Status | Fact or reason |
| --- | --- | --- | --- |
| Starter kit | Official Laravel React starter kit | A | Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Wayfinder, vite-plus (checked at commit 717b8f55, `docs/stack-baseline.md`) |
| Glue | Inertia | Fact | Laravel controllers render React pages directly. No separate API for the web app |
| Language | TypeScript | Fact | Included in the starter kit |
| Components | shadcn/ui | Fact | Included in the starter kit |
| Tables | TanStack Table | P | Sorting, filtering, paging for lists and khata |
| Forms | Inertia `useForm` (D-44) | P | Errors come back from Laravel's validation in plain words; no second validation library to keep in step |
| Routes in React | Wayfinder (typed route functions, in the kit) | Fact | A renamed route fails the TypeScript build. There is no Ziggy `route()` helper |
| Lint and format | vite-plus `vp check` (Oxlint, Oxfmt), TypeScript `tsc --noEmit` | Fact | The kit's toolchain. There is no ESLint |
| Front-end unit tests | `vp test` (Vitest bundled in vite-plus) | Fact | No separate Vitest install |
| Charts | shadcn charts (Recharts) | P | Dashboard and insights |
| Toasts | Sonner (shadcn) | P | Save confirmations |
| Server-side rendering | Off | P | Internal app, no public pages. No Node.js process on the server |

### Node.js

Node.js is used only to build the frontend files (`npm run build`). The live server runs PHP and PostgreSQL only. The release archive is built by CI (GitHub Actions) from a version tag and deployed by a person (D-42, §18.2).

## 6. Architecture and code structure

One Laravel application, one PostgreSQL database, split into modules. Only one class, the Posting Service, may write to the ledger (A, P for naming).

```text
Browser (React via Inertia)
  -> Controller: validates fields, checks permission
  -> Action class: ONE database transaction, all or nothing
       -> Business tables (purchases, sales, payments...)
       -> Posting Service: checks debit = credit -> Ledger (journal entries and lines, never edited)
       -> Audit log (every change, permanent)
Reports: money figures from the Ledger, kg figures from the business tables
```

Every save passes through one Action class in one transaction; balances come from the ledger and kg from the business tables.

### Folder structure (P)

```text
app/
  Domain/
    Parties/          Party model, actions, policy
    Items/
    MoneyAccounts/
    Purchases/
    Sales/
    Adjustments/
    Payments/
    Expenses/
    Partners/
    Transfers/
    OpeningBalances/
    Ledger/           LedgerAccount, JournalEntry, JournalLine, PostingService, PostingRules
    Periods/          Period, MonthEndService, BankReconciliation
    Checks/           CashCount
    Reports/          one query class per report
    Insights/
    Settings/
    Audit/
    Numbering/        VoucherNumberService
  Http/
    Controllers/      thin controllers, one per module
    Requests/         validation classes per form
resources/js/
  pages/<module>/     index.tsx, create.tsx, show.tsx
  components/         shared: PartyPicker, MoneyInput, KgInput, StatusBadge, KhataTable
database/
  migrations/
  seeders/            roles, permissions, system ledger accounts, items
tests/
  Feature/  Unit/
```

### What happens when any entry is saved

1. The controller receives the form. A validation class checks every field (Section 11).
2. A policy checks the user's permission (Section 7).
3. An Action class (for example `CreatePurchase`) opens one database transaction.
4. It checks that the entry date is in an open month and not in the future.
5. It takes the next voucher number from the numbering table, with a row lock.
6. It saves the business record (purchase, sale, payment and so on).
7. If the entry has an amount and is approved, it calls the Posting Service. The Posting Service builds the debit and credit lines from the posting rules (Section 8), checks that debits equal credits, and saves the journal entry and lines.
8. It writes an audit log record.
9. It commits. If any step fails, everything is rolled back: no partial record, no used voucher number, no unbalanced journal.
10. The page shows a success message and reloads its data.

### Design principles

- **One writer for the ledger.** Only `PostingService` creates journal entries. Database triggers block any UPDATE or DELETE on journal tables (Section 9).
- **Ledger is the truth for money.** Every money balance (party, cash, bank, trial balance, income vs spending) is calculated from journal lines only.
- **Business tables are the truth for kg.** Kg reports read purchases and sales (active entries only).
- **Thin controllers.** All business logic sits in Action classes, so a future mobile API reuses the same Actions.
- **No money logic in React.** The browser shows a total preview only. The server recalculates and its number is saved.
- **Exact money.** Database type `numeric`. PHP uses `brick/money` or `bcmath`. Float is forbidden for money and kg.
- **Company scope.** Every query is filtered by `company_id` automatically (global scope).
- **Settings, not code.** Limits, formats and categories live in the database (Section 9).

### Editing and cancelling a posted entry (A)

- **Edit:** in one transaction, a reversal journal cancels the old lines, then a new journal posts the new figures. The business record keeps one row; its `journal_entry_id` points to the latest journal.
- **Cancel:** a reversal journal cancels the old lines. The record stays with status "cancelled", reason, user and time.
- **Rate-pending entry:** no journal exists. Editing changes only the record. Adding the rate creates the first journal.
- **Closed month:** edit and cancel are refused. The change is made as an adjustment in the current open month (BR-MEC-07).

## 7. Users, roles and permissions

Two roles exist in version 1: Owner and Accountant (C). Permissions are stored in the database and checked on the server for every request (A). Hiding a button in React is never the protection.

| Permission key | What it allows | Owner | Accountant | Source |
| --- | --- | --- | --- | --- |
| `party.view` | See parties, profiles, khata | Yes | Yes | A |
| `party.create` / `party.update` | Add and edit parties | Yes | Yes (owner alerted) | C |
| `party.merge` | Merge duplicate parties | Yes | No | A |
| `party.review` | Mark new parties and bank detail changes as reviewed | Yes | No | A |
| `purchase.*` | View, create, edit, add rate, cancel purchases | Yes | Yes | A |
| `sale.*` | View, create, edit, add rate, cancel sales | Yes | Yes | A |
| `adjustment.*` | View, create, cancel agreed adjustments | Yes | Yes | A |
| `payment.*` | View, create, edit, cancel payments and receipts | Yes | Yes | A |
| `payment.approve` | Approve or reject payments waiting for approval | Yes | No | A |
| `expense.*` | View, create, edit, cancel expenses | Yes | Yes | A |
| `transfer.*` | View, create, edit, cancel transfers | Yes | Yes | A |
| `partner.view` | See partner money and statements | Yes | No | A |
| `partner.manage` | Record investments and withdrawals | Yes | No (O-19) | A |
| `account.view` | See cash and bank balances and statements | Yes | Yes | A |
| `cashcount.create` | Enter the daily cash count | Yes | Yes | A |
| `period.close` | Run month-end close | Yes | No (O-20) | A |
| `period.reopen` | Reopen a closed month | Yes | No, never | A |
| `report.view` | All reports in Section 12 except insights | Yes | Yes | A |
| `insights.view` | Business insights page | Yes | No | A |
| `audit.view` | Audit log | Yes | No | A |
| `settings.manage` | Settings, items, categories, accounts setup | Yes | No | A |
| `opening.manage` | Opening balances | Yes | No | A |
| `users.manage` | Add users, assign roles, disable users | Yes | No | A |
| `backup.view` | Backup status (no download: backups are readable only with the offline key, ADR-0006) | Yes | No | A + P |
| `dashboard.balances` | Cash and bank balances on the dashboard (BR-DSH-01) | Yes | No | C |

### Rules

- **BR-USR-01 (A):** a payment created by an Owner never needs approval. A payment created by an Accountant follows BR-PAY-06 and BR-PAY-07.
- **BR-USR-02 (P):** more than one user may have the Owner role. The number of users is O-13.
- **BR-USR-03 (P):** users are never deleted, only disabled. A disabled user cannot log in. Their past entries keep their name.
- **BR-USR-04 (P):** at least one active Owner must always exist. The system refuses to disable or demote the last Owner.
- **BR-USR-05 (A):** every create, edit, cancel, approval, login, failed login and logout is written to the audit log with the user's ID.
- **BR-USR-06 (P):** no self-registration, no self-service password reset, no email verification and no "delete my account". The starter kit's versions of these are removed. Only the Owner creates users and sets or resets their passwords on the Users page.
- **BR-USR-07 (P):** the first Owner is created only on the server with `php artisan zb:create-owner` (hidden password input). The command refuses to run when any Owner exists. Audited as `server_command`.
- **BR-USR-08 (P):** a password set by the Owner (new user or reset) is temporary. The user must choose a new password before any other page opens (`must_change_password`).
- **BR-USR-09 (P):** emergency commands exist on the server only: `zb:reset-password {email}` (prints a temporary password once, sets BR-USR-08, ends the user's sessions) and `zb:reset-two-factor {email}`. Both are audited as `server_command`. There is no web route for them.

## 8. Accounting design

Every money event becomes one journal entry whose debit total equals its credit total (D-23). Party balances are kept as a sub-ledger: each journal line on a control account carries a `party_id`.

### 8.1 Chart of accounts (seeded by the system)

Account codes are proposed (P). Account structure is agreed (A).

| Code | Account | Type | Normal side | Party on line | Created by |
| --- | --- | --- | --- | --- | --- |
| 1010+ | Cash – (name) | Asset | Debit | No | Automatically, one per cash money account |
| 1100+ | Bank – (name) | Asset | Debit | No | Automatically, one per bank money account |
| 1200 | Buyers | Asset | Debit | Buyer | Seeder |
| 1300 | Employees | Asset | Debit | Employee | Seeder |
| 2000 | Suppliers | Liability | Credit | Supplier | Seeder |
| 2100 | Other Parties | Liability | Credit | Other (transporters, shops, mechanics) | Seeder |
| 3000 | Partner Capital | Equity | Credit | Partner | Seeder |
| 3100 | Partner Withdrawals | Equity | Debit | Partner | Seeder |
| 3900 | Opening Balance Equity | Equity | Credit | No | Seeder |
| 4000 | Sales – Protein product | Income | Credit | No (item on line) | Seeder, linked to item |
| 4010 | Sales – Oil/Fat | Income | Credit | No (item on line) | Seeder, linked to item |
| 4900 | Sales Adjustments | Income (reduces income) | Debit | No | Seeder |
| 5000 | Purchases – Waste | Expense | Debit | No (item on line) | Seeder, linked to item |
| 5010 | Purchases – Wood | Expense | Debit | No (item on line) | Seeder, linked to item |
| 5900 | Purchase Adjustments | Expense (reduces expense) | Credit | No | Seeder |
| 6000+ | One account per expense category | Expense | Debit | No | Automatically when a category is created |
| 6900 | Cash Shortage / Excess | Expense | Debit | No | Seeder. Used only from month-end close |

**BR-LED-01 (A):** system accounts (`is_system = true`) cannot be renamed, disabled or deleted.

**Not covered in version 1:** there is no "other income" account (for example bank profit). If such money arrives, a change request is needed.

### 8.2 How a party balance is shown (A)

| Party type | Credit balance means | Debit balance means |
| --- | --- | --- |
| Supplier | We owe him | **Advance** (we paid before delivery) |
| Buyer | **Advance** (he paid before delivery) | He owes us |
| Other | We owe him | **Advance** |
| Employee | We owe him | He owes us (advance or cash held) |
| Partner | Capital invested (account 3000) | Withdrawn (account 3100) |

The screen never shows a minus sign for a party. It shows the words above (A).

### 8.3 Posting rules

`M` = the ledger account of the selected money account (cash or bank). `P` = the line carries the party. `I` = the line carries the item.

| ID | Event | Debit | Credit | Source |
| --- | --- | --- | --- | --- |
| PR-01 | Purchase, rate fixed | Item purchase account (I) | 2000 Suppliers (P) | A |
| PR-02 | Payment to supplier (including advance) | 2000 Suppliers (P) | M | A |
| PR-03 | Money received back from supplier (advance returned) | M | 2000 Suppliers (P) | P |
| PR-04 | Sale, rate fixed | 1200 Buyers (P) | Item sales account (I) | A |
| PR-05 | Receipt from buyer (including advance) | M | 1200 Buyers (P) | A |
| PR-06 | Money paid back to buyer (advance returned) | 1200 Buyers (P) | M | P |
| PR-07 | Agreed adjustment on a sale | 4900 Sales Adjustments | 1200 Buyers (P) | A |
| PR-08 | Agreed adjustment on a purchase | 2000 Suppliers (P) | 5900 Purchase Adjustments | A |
| PR-09 | Expense paid from cash or bank | Category account | M | A |
| PR-10 | Expense unpaid (owed to a party) | Category account | Party's control account (P) | A |
| PR-11 | Expense paid by an employee from cash he holds | Category account | 1300 Employees (P) | A |
| PR-12 | Payment to an other party or employee | Party's control account (P) | M | A |
| PR-13 | Money received from an other party or employee (cash returned) | M | Party's control account (P) | A |
| PR-14 | Partner investment | M | 3000 Partner Capital (P) | A |
| PR-15 | Partner withdrawal | 3100 Partner Withdrawals (P) | M | A |
| PR-16 | Transfer | M of "to" account | M of "from" account | A |
| PR-17 | Opening balance of a cash or bank account | M | 3900 | A |
| PR-18 | Opening: we owe a supplier or other party | 3900 | Control account (P) | A |
| PR-19 | Opening: advance with a supplier or other party | Control account (P) | 3900 | A |
| PR-20 | Opening: buyer or employee owes us | Control account (P) | 3900 | A |
| PR-21 | Opening: advance from a buyer, or we owe an employee | 3900 | Control account (P) | A |
| PR-22 | Opening: partner capital | 3900 | 3000 Partner Capital (P) | A |
| PR-23 | Month-end cash shortage | 6900 | M (cash) | A |
| PR-24 | Month-end cash excess | M (cash) | 6900 | A |
| PR-25 | Reversal (edit or cancel) | Same lines as the original, sides swapped |  | A |
| PR-26 | Rate correction of an entry in a closed month, purchase | Increase: item purchase account / Decrease: 2000 (P) | Increase: 2000 (P) / Decrease: item purchase account | A |
| PR-27 | Rate correction of an entry in a closed month, sale | Increase: 1200 (P) / Decrease: item sales account | Increase: item sales account / Decrease: 1200 (P) | A |

A "control account (P)" is chosen by party type: supplier 2000, buyer 1200, employee 1300, other 2100.

### 8.4 Ledger rules

- **BR-LED-02:** each journal line has either a debit or a credit greater than zero, never both, never neither.
- **BR-LED-03:** a journal entry is saved only if total debit equals total credit, checked in PHP and again by a database trigger.
- **BR-LED-04:** a line on accounts 1200, 1300, 2000, 2100, 3000, 3100 must have a `party_id` of the matching party type. A line on any other account must not.
- **BR-LED-05:** journal entries and lines are never updated or deleted. Corrections are reversals (PR-25).
- **BR-LED-06:** rate-pending purchases and sales, and payments waiting for approval, create no journal.
- **BR-LED-07:** every journal has a `posting_date`. It must be in an open month. Normally it equals the entry date.
- **BR-LED-08:** when a rate is added to an entry dated in a closed month, the journal's `posting_date` is the date the rate is added. The entry keeps its original date.
- **BR-LED-09:** when a posted entry in a closed month needs a rate change, the original is not touched. A rate correction (PR-26 or PR-27) for the difference is posted in the open month and linked to the original.
- **BR-LED-10:** the trial balance total debit must always equal total credit. If not, it is a software error: the system shows a red alert to the owner and month-end close is blocked.

## 9. Database design

PostgreSQL, 27 tables plus the permission package's tables (P for names and exact types, A for structure). Money is `numeric(16,2)`, kg is `numeric(14,3)`, rate is `numeric(14,4)`; screens round to the settings in O-14 to O-16.

### 9.1 Columns every table has

| Column | Type | Rule |
| --- | --- | --- |
| `id` | bigint identity | Primary key, never reused |
| `company_id` | bigint, FK companies | Required on every business table (D-37) |
| `created_by`, `updated_by` | bigint, FK users | Set by the system |
| `created_at`, `updated_at` | timestamptz | Stored in UTC, shown in Asia/Karachi |

**Tables opened from a link** (parties, purchases, sales, payments, expenses, transfers, partner_transactions, adjustments, attachments, money_accounts, items, expense_categories, users) also have `public_id` uuid, not null, default `gen_random_uuid()`, unique (BR-SEC-19).

**Transaction tables** (purchases, sales, adjustments, payments, expenses, partner_transactions, transfers) also have: `voucher_no` varchar(20) unique per company, `entry_date` date, `status` (active / cancelled), `cancel_reason` text, `cancelled_by`, `cancelled_at`, `journal_entry_id` (FK, latest journal, nullable), `idempotency_key` uuid nullable with a unique index on (`company_id`, `idempotency_key`) (BR-GEN-11).

### 9.2 Master tables

**companies:** `name`. One row in version 1.

**users** (from starter kit) plus: `company_id`, `is_active` boolean default true, `must_change_password` boolean default false (BR-USR-08), `password_changed_at` timestamptz. Two-factor columns come from the kit. Roles and permissions use the `spatie/laravel-permission` tables.

**settings:** `key` varchar(80), `value` text, `value_type` (string, integer, decimal, boolean, date, choice). Unique (`company_id`, `key`). Keys are listed in 9.6.

**parties**

| Column | Type | Rule |
| --- | --- | --- |
| `code` | varchar(10) | Auto: S-0001 supplier, B-0001 buyer, P-0001 partner, E-0001 employee, O-0001 other. Never changes |
| `type` | supplier / buyer / partner / employee / other | Required. Locked after the party's first entry |
| `name` | varchar(150) | Required |
| `name_ur` | varchar(150) | Optional, Urdu |
| `business_name`, `contact_person` | varchar(150) | Optional |
| `mobile` | varchar(20) | Required. Digits only, 10 to 15 digits (P) |
| `area`, `city` | varchar(100) | Optional |
| `address` | text | Optional |
| `ntn` | varchar(30) | Optional |
| `bank_name`, `bank_account_title`, `bank_account_number` | varchar(150) | Optional |
| `credit_limit` | numeric(16,2) | Buyers only, optional |
| `payment_terms_days` | integer | Buyers only, optional |
| `profit_share_percent` | numeric(5,2) | Partners only, optional |
| `joining_date` | date | Partners only, optional |
| `notes` | text | Optional |
| `status` | active / inactive | Default active |
| `merged_into_id` | FK parties | Set when merged |
| `needs_review` | boolean | True when created by an Accountant |
| `bank_change_needs_review` | boolean | True when an Accountant changes bank details |
| `reviewed_by`, `reviewed_at` |  | Owner review |

**items:** `code`, `name`, `type` (raw_material / fuel / product), `unit` (default 'kg'), `purchase_ledger_account_id` (required for raw_material and fuel), `sales_ledger_account_id` (required for product), `is_system`, `is_active`. Seeded: Waste (raw_material), Wood (fuel), Protein product (product), Oil/Fat (product).

**money_accounts:** `name`, `type` (cash / bank), `bank_name`, `account_title`, `account_number`, `ledger_account_id` (unique, created automatically), `is_active`.

**expense_categories:** `name` (unique per company), `ledger_account_id` (created automatically), `is_quick` (shown as a button on Quick Expense), `sort_order`, `is_active`.

**ledger_accounts:** `code` (unique per company), `name`, `type` (asset / liability / equity / income / expense), `normal_side` (debit / credit), `party_type` (nullable), `is_system`, `is_active`.

**voucher_sequences:** primary key (`company_id`, `doc_type`), `prefix`, `next_number`. Read with `SELECT … FOR UPDATE` inside the saving transaction, so numbers have no gaps. Prefixes (P): PUR, SAL, ADJ, PAY, RCV, EXP, PTN, TRF, OPB, RCO, CSH, JV. Format: prefix, dash, 6 digits (PUR-000001). Never reset.

### 9.3 Transaction tables

**purchases**

| Column | Type | Rule |
| --- | --- | --- |
| `party_id` | FK parties | Must be type supplier, active |
| `item_id` | FK items | Must be raw_material or fuel |
| `kg` | numeric(14,3) | Greater than 0. Supplier's weight (C) |
| `rate` | numeric(14,4) | Null when pending, else greater than 0 |
| `amount` | numeric(16,2) | Null when pending, else kg × rate, rounded per O-16 |
| `rate_status` | fixed / pending | Check: pending ⇔ rate and amount are null |
| `rate_fixed_at` | timestamptz | When the rate was first added |
| `slip_no` | varchar(50) | Required if setting `slip_no_required` |
| `our_weight_kg` | numeric(14,3) | Optional. Shown only if `plant_has_scale` |
| `note` | text | Optional |

**sales:** same columns as purchases, except `party_id` must be a buyer, `item_id` must be a product, `kg` is the buyer's weight (C), and `our_weight_kg` is the weight that left the plant.

**adjustments**

| Column | Type | Rule |
| --- | --- | --- |
| `target_type` | purchase / sale | Required |
| `purchase_id` / `sale_id` | FK | Exactly one is filled, matching `target_type` |
| `party_id` | FK parties | Must equal the party of the linked entry |
| `amount` | numeric(16,2) | Greater than 0. Reduces the amount (O-27) |
| `reason` | quality / weight / freight / other | Required |
| `note` | text | Required. What was agreed |

**payments**

| Column | Type | Rule |
| --- | --- | --- |
| `direction` | paid / received | Required |
| `party_id` | FK parties | Any type except partner |
| `amount` | numeric(16,2) | Greater than 0 |
| `money_account_id` | FK money_accounts | Required, active. Method (cash or bank transfer) comes from its type |
| `reference_no` | varchar(100) | Transaction ID for bank transfers |
| `handled_by_party_id` | FK parties | Optional, must be an employee |
| `purchase_id` / `sale_id` | FK | Set only when created from "paid now" or "received now" |
| `approval_status` | not_required / pending / approved / rejected | See BR-PAY-06 to 08 |
| `approved_by`, `approved_at`, `rejection_reason` |  | Owner decision |
| `note` | text | Optional |

**expenses**

| Column | Type | Rule |
| --- | --- | --- |
| `category_id` | FK expense_categories | Required, active |
| `amount` | numeric(16,2) | Greater than 0 |
| `payment_mode` | paid / unpaid / paid_by_employee | Required |
| `money_account_id` | FK | Required only when `paid` |
| `party_id` | FK parties | Required when `unpaid` (supplier, other or employee), when `paid_by_employee` (employee), or when amount ≥ `expense_party_required_limit` |
| `handled_by_party_id` | FK parties | Optional, employee |
| `linked_purchase_id` / `linked_sale_id` | FK | Optional, at most one. For transport |
| `note` | text | Required (C). Minimum 3 characters (P) |

**partner_transactions:** `party_id` (partner), `type` (investment / withdrawal), `amount` > 0, `money_account_id`, `note`.

**transfers:** `from_account_id`, `to_account_id` (must differ), `amount` > 0, `reference_no`, `note`.

**opening_balances:** `as_of_date` (= system start date), `money_account_id` or `party_id` (exactly one), `amount` ≥ 0, `side` (for parties: we_owe / they_owe; for partners: capital; for money accounts: balance), `journal_entry_id`. Locked when the first month is closed.

**rate_corrections:** `voucher_no` (RCO), `correction_date`, `target_type`, `purchase_id` / `sale_id`, `old_rate`, `new_rate`, `old_amount`, `new_amount`, `difference`, `reason` (required), `journal_entry_id`.

### 9.4 Ledger tables

**journal_entries:** `entry_no` (JV), `posting_date`, `source_type`, `source_id`, `description`, `reversal_of_id` (FK journal_entries, nullable), `created_by`, `created_at`. No `updated_at`.

**journal_lines:** `journal_entry_id`, `line_no`, `ledger_account_id`, `party_id` (nullable), `item_id` (nullable), `debit` numeric(16,2) default 0, `credit` numeric(16,2) default 0. Check: `debit >= 0`, `credit >= 0`, exactly one of them greater than 0.

### 9.5 Control and log tables

**rate_changes:** `target_type`, `target_id`, `old_rate` (nullable), `new_rate`, `old_amount`, `new_amount`, `changed_by`, `changed_at`, `reason` (optional).

**attachments:** `attachable_type`, `attachable_id`, `kind` (slip_photo / receipt_photo / other), `disk`, `path`, `original_name`, `mime_type`, `size_bytes`, `uploaded_by`, `replaced_by_id`. Never deleted; a replaced photo stays linked.

**periods:** `year`, `month`, `status` (open / closed), `closed_by`, `closed_at`. Unique (`company_id`, `year`, `month`).

**period_events:** `period_id`, `event` (closed / reopened), `user_id`, `reason`, `created_at`.

**bank_reconciliations:** `period_id`, `money_account_id`, `statement_balance`, `system_balance`, `difference`, `note`, `entered_by`, `entered_at`. The latest row per account and month is used.

**cash_counts:** `count_date`, `money_account_id` (cash only), `counted_amount`, `system_amount`, `difference`, `note`, `counted_by`, `created_at`. business_closed boolean (Owner dismissal for a closed day; counted amount empty). Several per day allowed; the latest is used.

**audit_logs:** `user_id`, `action`, `auditable_type`, `auditable_id`, `old_values` jsonb, `new_values` jsonb, `reason`, `ip_address`, `user_agent`, `created_at`.

### 9.6 Settings keys

| Key | Type | Default |
| --- | --- | --- |
| `company_name` | string | ZB Poultry Protein Mill (C) |
| `system_start_date` | date | none (O-07) |
| `payment_approval_limit` | decimal | none (O-01) |
| `new_party_first_payment_limit` | decimal | none (O-02) |
| `expense_party_required_limit` | decimal | none (O-03) |
| `unusual_rate_percent` | decimal | none (O-04) |
| `unusual_rate_lookback` | integer | none (O-04) |
| `rate_pending_age_days` | integer | none (O-05) |
| `advance_age_days` | integer | none (O-06) |
| `kg_decimals` | integer 0–3 | none (O-14) |
| `rate_decimals` | integer 0–2 | none (O-15) |
| `amount_rounding` | whole_rupee / two_decimals | none (O-16) |
| `number_format` | lakh / international | none (O-17) |
| `date_format` | choice | none (O-18) |
| `plant_has_scale` | boolean | none (O-25) |
| `slip_no_required`, `slip_photo_required` | boolean | none (O-26) |
| `insights_period_months` | integer | 3 (A) |
| `opening_balances_completed` | boolean | false |
| `owner_two_factor_required` | boolean | none (O-31) |

### 9.7 Database protections (P)

- Trigger: block UPDATE and DELETE on `journal_entries`, `journal_lines`, `audit_logs`, `rate_changes`, `period_events`.
- Trigger: block DELETE on every master and transaction table.
- Deferred constraint trigger on `journal_lines`: at commit, total debit must equal total credit for each journal entry.
- Indexes: (`company_id`, `entry_date`) and (`party_id`, `entry_date`) on transaction tables; (`rate_status`) on purchases and sales; (`ledger_account_id`), (`party_id`) on journal_lines; (`posting_date`), (`source_type`, `source_id`) on journal_entries.

## 10. Business rules

Each rule must be enforced on the server and covered by at least one automated test. A "warning" lets the user continue after confirming; a "block" refuses the save.

### 10.1 General

- **BR-GEN-01 (A):** every record belongs to a company. Every query is filtered by the user's company.
- **BR-GEN-02 (P):** entry date cannot be in the future, cannot be before `system_start_date`, and must be in an open month. Block.
- **BR-GEN-03 (A):** nothing is ever deleted. Mistakes are edited or cancelled.
- **BR-GEN-04 (A):** cancelling needs a reason of at least 5 characters (P for length). Cancelled entries stay visible with a "Cancelled" badge.
- **BR-GEN-05 (A):** every entry gets the next voucher number for its type. Numbers are gapless and never reused.
- **BR-GEN-06 (A):** amount = kg × rate and every balance are calculated on the server. Values sent by the browser for these are ignored.
- **BR-GEN-07 (P):** no transaction can be saved until every required setting (Section 9.6) is filled. Block with a message linking to Settings.
- **BR-GEN-08 (A):** inactive parties, accounts, items and categories cannot be selected for new entries. They stay visible in history and reports.
- **BR-GEN-09 (P):** if two users edit the same entry, the second save is refused with "This entry was changed by another user. Reload."
- **BR-GEN-10 (P):** if a save would make a cash account balance negative, show a warning: "Cash will go below zero. An entry may be missing."
- **BR-GEN-11 (P):** every create form sends a one-time `idempotency_key` (UUID made when the form opens). If an entry with that key already exists for the same user, nothing new is saved and the user sees "Already saved. {voucher}" (ADR-0011).

### 10.2 Parties

- **BR-PTY-01 (A):** every entry selects its party from the list. Free-text party names do not exist.
- **BR-PTY-02 (A):** party type is one of supplier, buyer, partner, employee, other.
- **BR-PTY-03 (C + A):** mobile is required. If another active party has the same mobile, or the same name (ignoring case and spaces), show a warning naming that party.
- **BR-PTY-04 (C):** a party has exactly one type. Type cannot change after its first entry. A supplier can never be selected as a buyer, and the reverse.
- **BR-PTY-05 (A):** a party with any entry can only be made inactive, never deleted.
- **BR-PTY-06 (A):** a party created by an Accountant is marked "needs review" and appears on the Owner's dashboard until the Owner marks it reviewed.
- **BR-PTY-07 (A):** when an Accountant changes bank name, title or number, the old and new values are logged and the Owner is alerted until reviewed.
- **BR-PTY-08 (A, P for method):** merge is Owner only and only between parties of the same type. History is not rewritten (journal lines are permanent). The system posts one journal moving the source party's balance to the target party, marks the source "merged into" the target and inactive. The target's khata shows the transferred balance with a link to the source's history.
- **BR-PTY-09 (A):** the party picker shows "Name – Area – Mobile" and searches by name, Urdu name, code and mobile.
- **BR-PTY-10 (A):** credit limit and payment terms exist only for buyers. Profit share and joining date exist only for partners.

### 10.3 Items and money accounts

- **BR-ITM-01 (C):** four items are seeded: Waste (raw material), Wood (fuel), Protein product (product), Oil/Fat (product). Unit kg.
- **BR-ITM-02 (P):** the Owner may add items. The system creates the matching ledger account. An item's type cannot change after its first entry.
- **BR-ACC-01 (A):** each money account is cash or bank and gets its own ledger account automatically.
- **BR-ACC-02 (P):** a money account can be made inactive only when its balance is zero.

### 10.4 Purchases

- **BR-PUR-01 (C):** party must be a supplier. Item must be raw material or fuel.
- **BR-PUR-02 (C):** kg is required and greater than 0 (supplier's weight). Decimals per `kg_decimals`.
- **BR-PUR-03 (C):** rate is optional. If given, it is greater than 0 and amount = kg × rate, rounded per `amount_rounding`. If empty, status is "Rate pending".
- **BR-PUR-04 (A):** a rate-pending purchase creates no journal. When the rate is added, the journal is posted (BR-LED-07, BR-LED-08).
- **BR-PUR-05 (A):** the form shows the last fixed rate for the same supplier and item, with its date. It is never filled in automatically.
- **BR-PUR-06 (A):** if the rate differs from the weighted average rate (Section 12.4) of the last `unusual_rate_lookback` fixed purchases of the same item (all suppliers) by more than `unusual_rate_percent`, show a warning. The user's confirmation is written to the audit log.
- **BR-PUR-07 (C + A):** slip number and slip photo follow `slip_no_required` and `slip_photo_required`. "Our weight" is shown only if `plant_has_scale` is on, and is always optional.
- **BR-PUR-08 (A):** if another active purchase has the same supplier, item, kg and date, show a duplicate warning.
- **BR-PUR-09 (A):** every rate addition or change writes a `rate_changes` row (old rate, new rate, old amount, new amount, user, time).
- **BR-PUR-10 (A):** "Paid now" (optional): amount greater than 0 and a money account. It creates a separate payment record linked to the purchase, in the same transaction. That payment follows the approval rules. An amount above the purchase total is allowed; the extra becomes an advance.
- **BR-PUR-11 (A):** editing a posted purchase creates a reversal and a new journal. All fields can be edited except the voucher number.
- **BR-PUR-12 (P):** cancelling a purchase does not cancel its linked payment. The system asks: "Also cancel the linked payment?" If not, the payment stays and shows as an advance.
- **BR-PUR-13 (P):** a purchase with active adjustments cannot be cancelled. Cancel the adjustments first.
- **BR-PUR-14 (A):** a purchase dated in a closed month cannot be edited or cancelled. A rate change is made as a rate correction (BR-LED-09).

### 10.5 Sales

Every purchase rule BR-PUR-02 to BR-PUR-14 also applies to sales, reading "supplier" as "buyer" and "paid now" as "received now". The sale-specific rules are:

- **BR-SAL-01 (C):** party must be a buyer. Item must be a product (protein product or oil/fat).
- **BR-SAL-05 (C + A):** kg is the buyer's weight. "Our weight" is the weight that left the plant.
- **BR-SAL-06 (A):** if the buyer has a credit limit and his balance after this sale would be above it, show a warning.
- **BR-SAL-07 (A):** "Received now" works like "Paid now" and never needs approval.

### 10.6 Agreed adjustments

- **BR-ADJ-01 (C + A):** an adjustment links to exactly one active purchase or sale with a fixed rate.
- **BR-ADJ-02 (A):** party is copied from the linked entry and cannot be changed.
- **BR-ADJ-03 (P):** amount is greater than 0 and not more than the linked amount minus its other active adjustments.
- **BR-ADJ-04 (C):** reason (quality, weight, freight, other) and a note of what was agreed are required.
- **BR-ADJ-05 (P):** date is on or after the linked entry's date and in an open month.
- **BR-ADJ-06 (P):** adjustments are not edited. A wrong adjustment is cancelled with a reason and entered again.

### 10.7 Payments and receipts

- **BR-PAY-01 (A):** direction is "paid" or "received". Party can be any type except partner.
- **BR-PAY-02 (A):** amount is greater than 0. Money account is required and active.
- **BR-PAY-03 (A):** method is cash or bank transfer, taken from the account type. No cheque option (C).
- **BR-PAY-04 (P):** for bank accounts, an empty reference number shows a warning.
- **BR-PAY-05 (A):** the form shows the party's current balance and the balance after this payment.
- **BR-PAY-06 (A):** a "paid" payment created by an Accountant with amount ≥ `payment_approval_limit` is saved as "Awaiting approval".
- **BR-PAY-07 (A):** a "paid" payment created by an Accountant to a party with no earlier posted "paid" payment, with amount ≥ `new_party_first_payment_limit`, is saved as "Awaiting approval".
- **BR-PAY-08 (A):** an awaiting payment has no journal and does not change any balance. It is listed on the Approvals page and on the party profile. The Owner approves (journal posted; posting date = entry date, or the approval date if that month is closed) or rejects with a reason (no journal, stays visible). The creator may edit or cancel it while it awaits approval.
- **BR-PAY-09 (A):** wallet money spent directly is recorded as a receipt into cash, then a cash expense (D-17, O-24). No special feature.
- **BR-PAY-10 (A):** "received" payments never need approval.
- **BR-PAY-11 (A):** paying more than the balance owed is allowed. The extra shows as an advance. The form shows an information message.
- **BR-PAY-12 (A):** "Handled by" (employee) is optional.

### 10.8 Expenses

- **BR-EXP-01 (C):** category and amount greater than 0 are required. One flat category list.
- **BR-EXP-02 (C):** a note is required on every expense (minimum 3 characters, P).
- **BR-EXP-03 (A):** if amount ≥ `expense_party_required_limit`, a party is required.
- **BR-EXP-04 (A):** payment mode "Paid" requires a money account (PR-09).
- **BR-EXP-05 (A):** payment mode "Unpaid" requires a supplier, other or employee party. The amount appears on that party's khata as "We owe" and is paid later with a normal payment (PR-10).
- **BR-EXP-06 (A):** payment mode "Paid by employee" requires an employee party and reduces the cash that employee holds (PR-11).
- **BR-EXP-07 (A):** "Handled by" (employee) is optional.
- **BR-EXP-08 (A):** an expense may be linked to one purchase or one sale (used for transport).
- **BR-EXP-09 (P):** Quick Expense opens with today's date, mode "Paid" and the default cash account.

### 10.9 Partners, transfers, opening balances

- **BR-PTN-01 (A):** party must be a partner. Type is investment or withdrawal.
- **BR-PTN-02 (A):** investment posts PR-14. Withdrawal posts PR-15.
- **BR-PTN-03 (A):** only users with `partner.manage` (Owner by default, O-19).
- **BR-PTN-04 (C + A):** version 1 does not calculate or distribute profit. The partner statement shows invested, withdrawn and net only.
- **BR-TRF-01 (A):** from and to accounts must be different and active.
- **BR-TRF-02 (A):** amount greater than 0. Posts PR-16.
- **BR-TRF-03 (A):** transfers never appear in income, spending or expense reports.
- **BR-OPB-01 (A):** opening balances are dated `system_start_date` and entered only on the Opening Balances page by the Owner.
- **BR-OPB-02 (A):** one opening record per money account and per party at most.
- **BR-OPB-03 (A):** opening balances can be edited (reversal and repost) until the Owner marks them complete or the first month is closed.
- **BR-OPB-04 (P):** the page shows the balance left in Opening Balance Equity (3900). Before marking complete, the Owner must confirm that figure. It represents past results not recorded elsewhere, or a missing balance.

### 10.10 Daily checks

- **BR-CHK-01 (A):** a cash count can be entered for any cash account and any date.
- **BR-CHK-02 (A):** system amount = ledger balance of that cash account including all postings up to that date.
- **BR-CHK-03 (A):** difference = counted − system. It is shown and stored, never posted automatically.
- **BR-CHK-04 (P):** the dashboard shows "No cash count for (date)" when yesterday has none. The Owner can dismiss it for a day the business was closed; the dismissal is stored as a cash_counts row with business_closed = true and no counted amount.
- **BR-CHK-05 (A):** rate-pending entries older than `rate_pending_age_days` are shown in red.
- **BR-CHK-06 (A):** advances older than `advance_age_days` are shown in red (age method in Section 12).

### 10.11 Month-end close

- **BR-MEC-01 (P):** months close in order. The first month is the month of `system_start_date`.
- **BR-MEC-02 (A):** if rate-pending entries dated in the month remain, the Owner must tick "Close with pending rates" (they post later per BR-LED-08).
- **BR-MEC-03 (P):** no payment dated in the month may be "Awaiting approval". Block.
- **BR-MEC-04 (A):** trial balance must balance. Block.
- **BR-MEC-05 (A):** for each active bank account, the statement closing balance is entered. Difference must be zero. Block. Missing entries (such as bank charges) are entered first.
- **BR-MEC-06 (A):** for each active cash account, a cash count for the month's last day is entered. Difference must be zero, or the Owner posts the difference as cash shortage or excess (PR-23, PR-24) with a note. Block until then.
- **BR-MEC-07 (A):** after closing, no entry may be created, edited or cancelled with an entry date or posting date in that month. Corrections go into the open month as adjustments or rate corrections.
- **BR-MEC-08 (A):** only the Owner can reopen, only the latest closed month, with a reason. Close and reopen events are logged.
- **BR-MEC-09 (A):** opening balances lock when the first month is closed.

## 11. Page specifications

The system has 48 pages (A, Requirements v1.1 Appendix A). Routes are proposed (P). "Mobile-first" pages are designed for a phone screen first (A). A record in a route (`{id}`) and a record in an address-bar filter are named by the record's `public_id`, never by its internal number (BR-SEC-19).

### 11.1 Page list

| # | Page | Route | Permission | Mobile-first |
| --- | --- | --- | --- | --- |
| 1 | Login | `/login` | Public | Yes |
| 2 | Dashboard | `/dashboard` | Logged in | Yes |
| 3 | Purchases list | `/purchases` | `purchase.view` | No |
| 4 | New purchase | `/purchases/create` | `purchase.create` | **Yes** |
| 5 | Purchase detail | `/purchases/{id}` | `purchase.view` | No |
| 6 | Sales list | `/sales` | `sale.view` | No |
| 7 | New sale | `/sales/create` | `sale.create` | **Yes** |
| 8 | Sale detail | `/sales/{id}` | `sale.view` | No |
| 9 | Payments list | `/payments` | `payment.view` | No |
| 10 | New payment / receipt | `/payments/create` | `payment.create` | **Yes** |
| 11 | Payment detail | `/payments/{id}` | `payment.view` | No |
| 12 | Expenses list | `/expenses` | `expense.view` | No |
| 13 | Quick expense | `/expenses/create` | `expense.create` | **Yes** |
| 14 | Expense detail | `/expenses/{id}` | `expense.view` | No |
| 15 | Transfers list | `/transfers` | `transfer.view` | No |
| 16 | New transfer | `/transfers/create` | `transfer.create` | No |
| 17 | Parties list | `/parties` | `party.view` | No |
| 18 | Add / edit party | `/parties/create`, `/parties/{id}/edit` | `party.create`, `party.update` | No |
| 19 | Party profile | `/parties/{id}` | `party.view` | No |
| 20 | Merge parties | `/parties/merge` | `party.merge` | No |
| 21 | Partner money list | `/partners` | `partner.view` | No |
| 22 | New partner entry | `/partners/create` | `partner.manage` | No |
| 23 | Partner statement | `/partners/statement` | `partner.view` | No |
| 24 | Accounts list | `/accounts` | `account.view` | No |
| 25 | Account statement | `/accounts/{id}` | `account.view` | No |
| 26 | Rate pending list | `/rate-pending` | `purchase.view`, `sale.view` | No |
| 27 | Approvals | `/approvals` | `payment.approve` | Yes |
| 28 | Daily cash check | `/cash-counts` | `cashcount.create` | **Yes** |
| 29 | Daily summary | `/daily-summary` | `report.view` | No |
| 30 | Month-end close | `/month-end` | `period.close` | No |
| 31 | Months list | `/months` | `period.close` | No |
| 32 | Reports home | `/reports` | `report.view` | No |
| 33 | Pending balances | `/reports/pending-balances` | `report.view` | No |
| 34 | Expense report | `/reports/expenses` | `report.view` | No |
| 35 | Income vs spending | `/reports/income-vs-spending` | `report.view` | No |
| 36 | Trial balance | `/reports/trial-balance` | `report.view` | No |
| 37 | Rate report | `/reports/rates` | `report.view` | No |
| 38 | Weight difference | `/reports/weight-difference` | `report.view` | No |
| 39 | Adjustments report | `/reports/adjustments` | `report.view` | No |
| 40 | Business insights | `/insights` | `insights.view` | No |
| 41 | Audit log | `/audit-log` | `audit.view` | No |
| 42 | Company settings | `/settings` | `settings.manage` | No |
| 43 | Users and permissions | `/settings/users` | `users.manage` | No |
| 44 | Expense categories | `/settings/expense-categories` | `settings.manage` | No |
| 45 | Items | `/settings/items` | `settings.manage` | No |
| 46 | Accounts setup | `/settings/accounts` | `settings.manage` | No |
| 47 | Opening balances | `/settings/opening-balances` | `opening.manage` | No |
| 48 | Backup status | `/settings/backups` | `backup.view` | No |

### 11.2 Rules for all list pages (P)

- Default filter: current month. Filters: date range, party, status (active, cancelled, rate pending, awaiting approval where relevant).
- Search by voucher number and party.
- Totals row for kg and amount (active entries only).
- 50 rows per page, newest first.
- Each row opens the detail page.

### 11.3 Dashboard (page 2)

- **Today:** kg bought per item, kg sold per product, money received, money paid, expenses total.
- **Balances:** each cash and bank account (`dashboard.balances`, Owner).
- **Alerts (each links to its list):** rate-pending count (red if aged), payments awaiting approval (Owner), new parties to review (Owner), bank detail changes to review (Owner), overdue buyers, buyers over credit limit, aged advances, missing cash count, negative cash balance, trial balance error (Owner).
- **Quick buttons:** New purchase, New sale, New payment, Quick expense.
- **BR-DSH-01 (C):** the Accountant's dashboard is the simple one: Today, the alerts the Accountant can act on, and the quick buttons. The balances block needs `dashboard.balances`, which only the Owner has; without it the server does not compute the balances. The Accountant still sees balances on the Accounts page (`account.view`).

### 11.4 Form: New purchase (page 4)

| Field | Input | Required | Rule |
| --- | --- | --- | --- |
| Date | Date picker, default today | Yes | BR-GEN-02 |
| Supplier | Party picker (suppliers, active) | Yes | BR-PUR-01, BR-PTY-09 |
| Item | Waste / Wood | Yes | BR-PUR-01 |
| Kg | Number keypad | Yes | BR-PUR-02 |
| Rate | Number keypad. Hint below: "Last rate: Rs X on (date)" | No | BR-PUR-03, 05, 06 |
| Total | Read-only preview, or "Rate pending" | – | BR-GEN-06 |
| Slip number | Text | Per setting | BR-PUR-07 |
| Slip photo | Camera or file | Per setting | BR-PUR-07 |
| Our weight (kg) | Number, shown if plant has scale | No | BR-PUR-07 |
| Note | Text | No |  |
| Paid now | Toggle. Opens: amount, account, reference, handled by | No | BR-PUR-10 |

Buttons: Save, Save and add another. Warnings before save: unusual rate, duplicate entry, negative cash.

### 11.5 Form: New sale (page 7)

Same as New purchase with: Buyer instead of Supplier; Item = Protein product / Oil/Fat; "Our weight" labelled "Weight that left the plant"; "Received now" instead of "Paid now"; extra warning for credit limit (BR-SAL-06).

### 11.6 Detail pages (5, 8, 11, 14)

Show every field, voucher number, status badge, created by and time, attachments, linked records (payment, adjustments, transport expenses), journal lines (Owner only, P), and change history. Actions by permission: Edit, Add rate (if pending), Add adjustment (purchase and sale, rate fixed), Cancel (reason), Share slip as PDF (purchase and sale).

### 11.7 Form: Agreed adjustment (opened from purchase or sale detail)

| Field | Required | Rule |
| --- | --- | --- |
| Date | Yes | BR-ADJ-05 |
| Amount (maximum shown) | Yes | BR-ADJ-03 |
| Reason: quality / weight / freight / other | Yes | BR-ADJ-04 |
| Note: what was agreed | Yes | BR-ADJ-04 |

### 11.8 Form: New payment / receipt (page 10)

| Field | Required | Rule |
| --- | --- | --- |
| Direction: Paid / Received | Yes | BR-PAY-01 |
| Date | Yes | BR-GEN-02 |
| Party (all types except partner) | Yes | BR-PAY-01 |
| Current balance and balance after (read-only) | – | BR-PAY-05 |
| Amount | Yes | BR-PAY-02, 11 |
| Account | Yes | BR-PAY-02, 03 |
| Reference number | Warning if empty on bank | BR-PAY-04 |
| Handled by (employee) | No | BR-PAY-12 |
| Photo | No |  |
| Note | No |  |

If the save goes to approval, the message says: "Saved. Waiting for owner approval. Balances will change after approval."

### 11.9 Form: Quick expense (page 13)

| Field | Required | Rule |
| --- | --- | --- |
| Date (default today) | Yes | BR-EXP-09 |
| Category (quick buttons, then full list) | Yes | BR-EXP-01 |
| Amount | Yes | BR-EXP-01 |
| Note | Yes | BR-EXP-02 |
| Payment mode: Paid / Unpaid / Paid by employee | Yes | BR-EXP-04 to 06 |
| Account (if Paid) | Conditional | BR-EXP-04 |
| Party | Conditional | BR-EXP-03, 05, 06 |
| Link to purchase or sale | No | BR-EXP-08 |
| Handled by | No | BR-EXP-07 |
| Receipt photo | No |  |

### 11.10 Other forms

- **New transfer (16):** date, from account, to account, amount, reference, note.
- **New partner entry (22):** date, partner, investment or withdrawal, amount, account, note.
- **Add / edit party (18):** fields from Section 9.2 shown by type. Duplicate warning (BR-PTY-03). Opening balance is not on this form (BR-OPB-01).
- **Daily cash check (28):** date, cash account, system amount (read-only), counted amount, difference (live), note. History list below.
- **Opening balances (47):** one row per money account and party with amount and side; the Opening Balance Equity figure; "Mark complete" button (BR-OPB-04).
- **Company settings (42):** every key in Section 9.6, grouped as Limits, Formats, Weight, Alerts.

### 11.11 Party profile (page 19)

Tabs: Details · Khata · Rate history · Weight differences · Change history. Header shows code, type, balance in words (Section 8.2), pending-rate kg, payments awaiting approval. Buttons: new entry for this party, share statement as PDF.

### 11.12 Month-end close (page 30)

A step page for the oldest open month: (1) rate-pending check, (2) approvals check, (3) trial balance, (4) bank statement balance for each bank, (5) cash count for each cash account with an option to post shortage or excess, (6) Close. Each step shows pass or fail. Close is enabled only when all blocks pass (BR-MEC-02 to 06).

## 12. Reports and calculations

Money figures come only from journal lines; kg figures come only from purchases and sales (A). Every report excludes cancelled entries and payments awaiting approval.

### 12.1 Data rules for all reports

- **BR-RPT-01 (A):** money reports use `posting_date`. Kg reports use `entry_date`. (They differ only when a rate is added after its month closed, BR-LED-08.)
- **BR-RPT-02 (P):** kg totals include rate-pending entries, because their kg is known. Rate averages exclude them.
- **BR-RPT-03 (P):** rate averages use the original entry amounts, before agreed adjustments. Adjustments are reported separately (R-12).
- **BR-RPT-04 (A):** party balances are shown in words, never with a minus sign (Section 8.2).
- **BR-RPT-05 (A):** numbers and dates follow `number_format` and `date_format`.
- **BR-RPT-09 (C):** the account statement (R-06) shows 100 movements per page. Opening, closing and the in and out totals always cover the whole range; a later page starts with "Brought forward", the balance after the rows before it.
- **BR-RPT-10 (C):** the rate report (R-10) and the weight difference report (R-11) list each entry for a range of at most 3 months. For a longer range they show one line per month in each party (and item) group, with the same totals, weighted average and %, and the note "Periods longer than 3 months show one line per month." The party profile keeps the entry-by-entry view.
- **BR-RPT-11 (C):** one statement PDF (§13.1) covers at most 3 months; a longer range is refused with the glossary message. Without a chosen period it covers the 3 months up to today, never before `system_start_date`. A range of at most 3 months ends on its last day: 1 January to 31 March is 3 months; 1 January to 1 April is longer.

### 12.2 Report list

| ID | Report | Filters | What it shows |
| --- | --- | --- | --- |
| R-01 | Party khata (page 19) | Party, date range, show cancelled | Opening balance, then every purchase or sale, rate-pending entry (kg only, no amount), payment, adjustment, unpaid or employee-paid expense, rate correction and merge transfer, in date order, with running balance. Awaiting payments shown grey, outside the balance |
| R-02 | Pending balances (33) | As-of date, party type | Per party: balance in words, amount, pending-rate kg, oldest advance age, overdue amount (buyers) |
| R-03 | Rate pending list (26) | Type, party | Entry, date, party, item, kg, age in days (red if over `rate_pending_age_days`) |
| R-04 | Daily summary (29) | Date | Kg bought per item, kg sold per product, money received, money paid, expenses by category, closing balance per money account, pending-rate count |
| R-05 | Expense report (34) | Date range, category, party | Total per category, then each expense with note, mode and party |
| R-06 | Account statement (25) | Account, date range | Opening, each movement, running balance, closing; 100 movements per page (BR-RPT-09) |
| R-07 | Trial balance (36) | As-of date, "show parties" | Each ledger account's total debit, total credit and balance. "Show parties" splits control accounts by party. Footer: total debit = total credit |
| R-08 | Income vs Spending (35) | Month or range | Income, spending and the difference (12.3) |
| R-09 | Partner statement (23) | Partner, date range | Investments, withdrawals, net per partner |
| R-10 | Rate report (37) | Item, party, date range | Each fixed rate over time and the weighted average; one line per month over 3 months (BR-RPT-10) |
| R-11 | Weight difference (38) | Type, party, date range | Their kg vs our kg per party, difference and % (12.4); one line per month over 3 months (BR-RPT-10) |
| R-12 | Adjustments report (39) | Type, party, reason, date range | Total, count and % of sales or purchases per party and reason |
| R-13 | Business insights (40) | Period (default `insights_period_months` = 3) | Indicators in 12.5, each labelled "rough indicator" |

### 12.3 Income vs Spending (R-08)

BR-RPT-08: the page title is always "Income vs Spending". A fixed banner says: "This is not profit. Stock is not recorded, so months with heavy buying look worse and months with heavy selling look better." (C + A)

```latex
\text{Income} = \text{Sales 4000} + \text{Sales 4010} - \text{Sales Adjustments 4900}
```

```latex
\text{Spending} = \text{Purchases 5000} + \text{Purchases 5010} - \text{Purchase Adjustments 5900} + \sum \text{Expense accounts 6000 and above}
```

```latex
\text{Result} = \text{Income} - \text{Spending}
```

Partner money (3000, 3100), transfers and opening balances never appear here, because their accounts are not income or expense. A line under the result shows kg still rate-pending, because their amounts are not yet included.

### 12.4 Rates and weights

Weighted average rate for a set of fixed-rate entries:

```latex
\text{Average rate} = \frac{\sum \text{amount}}{\sum \text{kg}}
```

Weight difference, only for entries where our weight was entered. A positive number always means the business lost kg:

```latex
\text{Purchase difference} = \text{supplier kg} - \text{our kg} \qquad \text{Sale difference} = \text{our kg} - \text{buyer kg}
```

```latex
\text{Difference \%} = \frac{\sum \text{difference}}{\sum \text{our kg}} \times 100
```

### 12.5 Business insights (R-13)

All figures cover the selected period (default 3 months) and are labelled "rough indicator" (A).

| Indicator | Formula |
| --- | --- |
| Kg ratio, protein | Σ protein kg sold ÷ Σ waste kg bought |
| Kg ratio, oil/fat | Σ oil/fat kg sold ÷ Σ waste kg bought |
| Kg ratio, combined | Σ product kg sold ÷ Σ waste kg bought |
| Wood per kg of waste (by month) | Σ wood kg bought ÷ Σ waste kg bought |
| Rate gap (by month) | Average selling rate per product vs average buying rate of waste (12.4) |
| Rough cost per kg sold | (Income vs Spending "Spending" for the period) ÷ Σ product kg sold |
| Supplier comparison | Per supplier: kg, average rate, advance outstanding, weight difference % |
| Buyer comparison | Per buyer: average days to pay, overdue amount, adjustments total and % of his sales, share of total sales % |

### 12.6 Ageing method: oldest first (P)

Used for overdue buyers, average days to pay and advance age.

1. For one party, list everything that increases what they owe (opening balance, sales, payments to them) and everything that reduces it (receipts, adjustments, deliveries), each with its date and amount.
2. Match reductions to increases, oldest first.
3. **Overdue (buyers with payment terms):** any unmatched part of a sale older than `payment_terms_days` is overdue.
4. **Days to pay:** for each sale fully matched in the period, the days from the sale date to the date of the receipt that completed it. Average weighted by sale amount.
5. **Advance age:** for a party whose balance is an advance, the age in days of the oldest unmatched payment (or receipt for a buyer).

## 13. PDF documents

Two PDFs are generated on the server: the party statement and the entry slip (D-35). Both are shared by download or the phone's share button; automatic WhatsApp sending is out of scope. PDFs print the party's English `name` only; the Urdu name (`name_ur`) is shown on screen only, because the PDF library does not join Urdu letters correctly (P).

### 13.1 Party statement

| Part | Content |
| --- | --- |
| Header | Company name, "Statement of Account", party name, code, mobile, period (from – to), at most 3 months (BR-RPT-11) |
| Opening | Balance at the start of the period, in words (Section 8.2) |
| Rows | Date, voucher number, description (item, kg × rate, or payment method and reference), amount that increases the balance, amount that reduces it, running balance in words |
| Pending | Rate-pending entries listed with kg and "Rate pending", no amount |
| Closing | Balance at the end of the period, in words |
| Footer | "Generated on (date, time) by (user)" and page number |

Not shown (P): cancelled entries, payments awaiting approval, internal notes.

### 13.2 Entry slip (purchase or sale)

| Part | Content |
| --- | --- |
| Header | Company name, "Purchase Slip" or "Sale Slip" |
| Body | Voucher number, date, party name and code, item, kg, rate (or "Rate pending"), amount, weight slip number, amount paid or received now |
| Balance | Party balance after this entry, in words |
| Footer | "Generated on (date, time)" |

Not shown (P): "our weight", because it is internal control data.

### 13.3 Other printing

Trial balance, Income vs Spending and all report pages use the browser's print function with a print-friendly layout (P).

## 14. Security, audit log and backups

Every action is logged permanently, every request is permission-checked on the server, and data is backed up off the server every hour (A, P for frequency). Numeric limits marked P are proposals you can change. How each rule is enforced and proven: `docs/security.md` §4. Secrets: `docs/secrets.md`. Data rules: `docs/data-handling.md`.

### 14.1 Audit log

- **BR-AUD-01 (A):** these actions are written to `audit_logs` with user, time, IP address and device: login, failed login, logout, create, edit (old and new value of every changed field), cancel (with reason), rate added or changed, payment approved or rejected, party reviewed, party merged, bank details changed, settings changed, user added or changed, month closed, month reopened, opening balances marked complete, unusual-rate warning confirmed, duplicate warning confirmed, cash count entered, server command run (`server_command`: create owner, reset password, reset two-factor), user sessions ended.
- **BR-AUD-02 (A):** audit rows can never be changed or deleted (database trigger, Section 9.7).
- **BR-AUD-03 (A):** only the Owner sees the audit log. Filters: user, action, date range, record.
- **BR-AUD-04 (A):** each detail page shows that record's own change history to anyone who can view the record.

### 14.2 Security

- **BR-SEC-01 (P):** HTTPS only. HTTP redirects to HTTPS.
- **BR-SEC-02 (P):** passwords follow the starter kit's production rule: at least 12 characters with upper and lower case, a number and a symbol, and not found in known data breaches (only the first 5 characters of the password's SHA-1 hash are sent to the breach service; if it cannot be reached the password is accepted). Stored with bcrypt.
- **BR-SEC-03 (P):** login limited to 5 attempts per minute per user and IP address.
- **BR-SEC-04 (P):** a session ends after 60 minutes without activity.
- **BR-SEC-05 (A):** every route and action checks permission on the server (Section 7).
- **BR-SEC-06 (P):** uploads accept JPG, PNG, WEBP and PDF up to 5 MB, checked by file content, stored on a private disk with random file names, shown only through a permission-checked route. Photos are resized in the browser (longest side 2000 px) before upload, and every image is re-encoded on the server, which removes location data. The kit's built-in serving of the local disk (`'serve' => true`) is switched off.
- **BR-SEC-07 (P):** CSRF protection on every form (Laravel default).
- **BR-SEC-08 (A, P for method):** backups run automatically, are encrypted, and are stored outside the server; a restore is tested once a month. Details: BR-OPS-01 to BR-OPS-05.
- **BR-SEC-09 (P):** two-step login (authenticator app codes, the starter kit's feature) is available to every user. When `owner_two_factor_required` is true (O-31), an Owner without it is sent to the security settings page until it is turned on.
- **BR-SEC-10 (P):** the server has a firewall (only SSH rate-limited, HTTP, HTTPS), SSH key login for one admin user only (no root, no passwords), automatic security updates with a night-time reboot when needed, UTC time kept synchronised, and separate operating-system users for the web server, the app, deploys and backups (`infra/provision`).
- **BR-SEC-11 (P):** secrets are listed in `docs/secrets.md` and live only where that file says: never in the code repository, CI, a Claude session or its environment variables, chat or e-mail. The CI secret scan blocks keys, `APP_KEY` values, monitor URLs and `.env` files.
- **BR-SEC-12 (P):** the application's database role (`zb_app`) cannot change table structure, remove or disable triggers, truncate, or delete rows except in framework housekeeping tables (sessions, cache, jobs, role links); it cannot update journal, audit, rate-change or period-event rows. Migrations run as `zb_migrator`; backups read as `zb_backup`. All three connect only through the local Unix socket with peer authentication: no database password exists, and PostgreSQL listens on no network address (D-43).
- **BR-SEC-13 (P):** when a user is disabled, their role changes, or their password is reset or changed, every other session of that user ends at once. A disabled user is logged out on the next request.
- **BR-SEC-14 (P):** every response carries security headers: HSTS (1 year), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` (camera, microphone, location off), `X-Robots-Tag: noindex`; HTML pages also carry a Content Security Policy with a new nonce per request (ADR-0008). `robots.txt` disallows everything.
- **BR-SEC-15 (P):** the session cookie is `Secure`, `HttpOnly`, `SameSite=Lax`; session data is encrypted in the database.
- **BR-SEC-16 (P):** staging and production run with `APP_ENV=production` and `APP_DEBUG=false`: users never see technical error details. Models are strict (no lazy loading, no silently discarded attributes) outside production.
- **BR-SEC-17 (P):** logs never contain passwords, tokens, session IDs, request bodies, party names, mobiles, bank details or amounts. Application logs rotate daily and are kept 14 days; web server logs leave out query strings.
- **BR-SEC-19 (C):** links, record URLs and address-bar filters name a record by its random `public_id` (UUID v4), never by its internal number. An unknown or malformed public id answers 404. Form bodies and background lookups keep internal numbers; the protection stays the login, the permission on every route (BR-SEC-05) and the company scope.
- **BR-SEC-18 (P):** dependency lock files are committed; CI checks known vulnerabilities (`composer audit`, `npm audit`) Dependabot opens grouped pull requests for minor and patch updates monthly, and its alerts e-mail security warnings at once. Only packages named in a task card are installed.

### 14.3 Operations (P unless stated)

- **BR-OPS-01:** the database is backed up every hour (`pg_dump`, without session and cache rows) and uploads every day (monthly full archive plus daily additions). Every backup is encrypted with a public key before it leaves the server and uploaded with a storage key that cannot delete or overwrite earlier backups.
- **BR-OPS-02:** the private key that decrypts backups is never stored on any server or in the repository. Two copies exist, held by two different people (O-37).
- **BR-OPS-03:** retention by the storage's lifecycle rules: hourly copies 2 days, daily 35 days, monthly 7 years (O-35), uploads archives 100 days. Object Lock or versioning stops early deletion.
- **BR-OPS-04 (A for monthly):** once a month a backup is restored on staging into a temporary database, checked (every journal balances, `zb:verify`), and the temporary database is dropped. Date and counts are recorded in `docs/release-checks.md`, never amounts or names (that file is in git).
- **BR-OPS-05:** recovery targets: at most 1 hour of entries lost (RPO) and running again within 4 hours on a new server (RTO) (O-36). A full rebuild drill is done before go-live and once a year.
- **BR-OPS-06:** `/up` checks the database too; an external monitor checks it every 5 minutes and alerts the O-33 recipients.
- **BR-OPS-07:** each backup job pings a dead-man monitor; a missed or failed backup alerts within 2 hours (database) or 26 hours (uploads). The backup status page shows the last success and the last error.
- **BR-OPS-08:** `php artisan zb:verify` runs daily: every journal balances, trial balance totals are equal, voucher numbers have no gaps or duplicates, every line on a party control account names a party (so party balances add up to the control account), and every posted entry points to its own journal (reversed when cancelled). Any failure alerts.
- **BR-OPS-09:** a health check every 15 minutes alerts on disk above 80%, a stopped service, unsynchronised clock, a certificate expiring within 14 days, an old backup, or `/up` failing.
- **BR-OPS-10:** releases are built by CI from version tags on `main`, checked by SHA-256, and deployed by a person with `zb-deploy-release` (backup, maintenance mode, migrate as `zb_migrator`, system data (BR-OPS-11), grants, caches, atomic switch, smoke test, automatic switch-back). Every migration must keep working with the previous release, because rollback switches code only. `migrate:rollback` is never run in production. Nobody edits production data by hand. The repository is private on GitHub Free, which has no branch protection for private repositories (ADR-0012): code reaches `main` through pull requests that the developer merges when every check is green; the protection hook stops Claude from changing `main`; the CI main guard fails when a commit reaches `main` without a merged pull request or protected files changed without `[spec-change]`; and the release workflow builds only from a commit whose CI run on `main` is green.
- **BR-OPS-11:** system data is created on every server by `SystemDataSeeder`: the company row, every settings key (with `null` for OPEN values), the roles and permissions of §7, the system ledger accounts of §8.1 and the four system items (BR-ITM-01). It only adds rows that are missing, and it sets the Owner and Accountant permissions to exactly the §7 lists (roles are fixed in version 1). It never changes a settings value, a user, a party or an entry, and never creates users or test data. `zb-deploy-release` runs it on every deploy as `zb_migrator`, right after the migrations, so a new release that adds a permission or a system account works at once. Test and demo data come only from `DatabaseSeeder` and the other local seeders, which refuse to run when `APP_ENV=production`.

## 15. Frontend design system

The interface uses the starter kit's shadcn/ui and Tailwind setup with the accounting-specific rules below (A). Before building all pages, three screens are designed and shown to the client: Dashboard, New Purchase on a phone, and Party Khata (A).

### 15.1 Layout

- **Desktop:** left sidebar with these groups, each item hidden if the user lacks permission: Dashboard · Daily entries (Purchases, Sales, Payments, Expenses, Transfers) · People (Parties, Partners) · Money (Accounts) · Checks (Rate pending, Approvals, Cash check, Daily summary) · Month-end · Reports · Audit log · Settings.
- **Top bar:** party search (opens the party profile) and a "New entry" menu.
- **Mobile:** bottom bar with New purchase, New sale, New payment, Quick expense; sidebar opens from a menu button.
- **Owner settings are built in React** with the same components. No separate admin panel with a different look (A).

### 15.2 Numbers and money

- Numbers are right-aligned with tabular figures (CSS `tabular-nums`) so digits line up.
- Lakh format (1,00,000) when `number_format` = lakh, using the `en-IN` number format in JavaScript and the same grouping in PDFs. International format otherwise.
- Money in and out are shown with words and colour together ("Received" green, "Paid" red). Colour is never the only signal.
- Party balances use words from Section 8.2, never a minus sign.

### 15.3 Status badges (P for colours)

| Badge | Colour | Used on |
| --- | --- | --- |
| Rate pending | Amber | Purchases, sales |
| Awaiting approval | Amber | Payments |
| Advance | Blue | Party balances |
| Overdue | Red | Buyers |
| Needs review | Amber | Parties |
| Rejected | Red outline | Payments |
| Cancelled | Grey, crossed out | All entries |

### 15.4 Forms

- Party picker: shadcn Command (searchable list) showing "Name – Area – Mobile" (BR-PTY-09).
- Kg, rate and amount fields open the number keypad on phones (`inputmode="decimal"`).
- Large touch targets on mobile-first pages.
- Live total preview (kg × rate). The saved value is the server's (BR-GEN-06).
- Warnings (unusual rate, duplicate, credit limit, negative cash) open a dialog with "Go back" and "Save anyway".
- Enter moves to the next field. Ctrl+S saves on desktop (P).
- Validation errors appear under the field in plain words.

### 15.5 Feedback

- A toast after every save, showing the voucher number.
- Loading placeholders while lists load.
- A confirmation dialog with a reason box before every cancel.
- Dark mode from the starter kit.

### 15.6 Shared components (P)

`PartyPicker`, `MoneyInput`, `KgInput`, `RateInput` (with last-rate hint), `StatusBadge`, `BalanceInWords`, `KhataTable`, `DateRangeFilter`, `AttachmentUploader`, `ConfirmWithReason`, `WarningDialog`.

### 15.7 Brand

The company name is ZB Poultry Protein Mill (C). The logo is used minimally because it is a raster image with a white background (C); colour tokens are sampled from it (P).

- **Name:** the sidebar shows a small "ZB" monogram tile and the full name as text. Page titles never repeat the company name.
- **Logo, two places only:** the login page (at most 120 px wide) and the PDF header (at most 48 px tall). Nowhere else in the app.
- **Logo background:** always white, because the bird in the logo is white. In dark mode the logo sits on a white tile.

| Token | Hex | Use |
| --- | --- | --- |
| `brand-deep` | #04291A | Sidebar background, page headings |
| `brand-green` | #0A5223 | Primary buttons, links, focus ring |
| `brand-gold` | #D89707 | Active menu marker and small highlights only. Never text on white (contrast too low) |
| `brand-red` | #CB1222 | Not used in the interface, so it is never confused with errors or "Paid" |

- **Typeface (P):** IBM Plex Sans, self-hosted: `fontsource('IBM Plex Sans')` from `laravel-vite-plugin/fonts` reading the `@fontsource/ibm-plex-sans` npm package (ADR-0010), with tabular figures for every number. No font or script is loaded from another site.
- Status badge colours (15.3) stay separate from brand colours.

## 16. Non-functional requirements

The system must be fast on a phone, correct to the paisa, and maintainable by a developer other than you (A, P for numbers).

| ID | Area | Requirement | Status |
| --- | --- | --- | --- |
| NFR-01 | Speed | Mobile-first pages open in under 2 seconds on a 4G connection. Saving an entry takes under 1 second on the server | P |
| NFR-02 | Volume | Reports stay under 3 seconds with 100,000 entries per year. The client's real volume is not known; this is a safe margin, not an estimate | P |
| NFR-03 | Browsers | Latest Chrome on Android; latest Chrome and Edge on Windows | P |
| NFR-04 | Language | English interface. All text fields accept Urdu (UTF-8). All interface text is kept in translation files so Urdu can be added later | A |
| NFR-05 | Currency and time | PKR only. Times stored in UTC and shown in Asia/Karachi | A |
| NFR-06 | Accuracy | No float type anywhere for money, kg or rate. Rounding only at the step defined by `amount_rounding` | A |
| NFR-07 | Code quality | Laravel Pint for PHP style; Larastan level 7; vite-plus `vp check` (Oxlint and Oxfmt) and TypeScript strict mode for React (all in the starter kit) | P |
| NFR-08 | Documentation | A README (setup, deploy, backup restore), this specification kept current, and the posting rules (Section 8.3) mirrored in code comments | A |
| NFR-09 | Future mobile app | Business logic in Action classes so a future API reuses it without changes | A |
| NFR-10 | Future second plant | `company_id` on every record (D-37) | A |
| NFR-11 | Front-end delivery | Built files have content hashes and are cached for one year; text files are compressed; each page loads only its own code; no request to another site. JavaScript transferred for the login page and the dashboard is measured and recorded in T8.2 | P |
| NFR-12 | Supported versions | Every component in production is inside its vendor's security support (dates in `docs/stack-baseline.md`); the runbook schedules upgrades before each end date | P |

## 17. Testing and acceptance data

The system is accepted only when all automated tests pass and acceptance cases AT-1 to AT-6 give exactly the expected figures below. Setting values used in tests are test values only, not the client's real values.

### 17.1 Automated tests (required)

| ID | What is tested |
| --- | --- |
| T-01 | Each posting rule PR-01 to PR-27 creates the correct accounts, sides, party and item on each line, and balances |
| T-02 | The database refuses an unbalanced journal (BR-LED-03) |
| T-03 | The database refuses UPDATE or DELETE on journal, audit, rate change and period event tables |
| T-04 | Voucher numbers stay gapless when a save fails and when two saves run at the same time |
| T-05 | Rate-pending entries create no journal; adding the rate creates it; `rate_changes` is written |
| T-06 | Editing a posted entry creates a reversal and a new journal; cancelling creates a reversal |
| T-07 | Every create, edit or cancel dated in a closed month is refused |
| T-08 | Payment approval rules BR-PAY-06, 07, 08, 10 and BR-USR-01 |
| T-09 | Every "No" in the permission matrix (Section 7) is refused for the Accountant on the server |
| T-10 | No transaction can be saved while a required setting is empty (BR-GEN-07) |
| T-11 | Warnings: unusual rate, duplicate entry, credit limit, negative cash |
| T-12 | Ageing method (Section 12.6) on prepared data |
| T-13 | Report formulas R-07, R-08, R-10, R-11 and R-13 on prepared data |
| T-14 | Both PDFs generate with correct figures |
| T-15 | Party merge posts the balance transfer and leaves history untouched |
| T-16 | Security headers and CSP nonce on every page; no debug output in production mode (BR-SEC-14, BR-SEC-16) |
| T-17 | Sessions end when a user is disabled, demoted or their password is reset (BR-SEC-13); temporary passwords must be changed (BR-USR-08) |
| T-18 | A repeated idempotency key saves nothing new (BR-GEN-11) |
| T-19 | CI privilege check: after the real migrations the web role cannot alter, truncate, disable triggers or delete business rows (BR-SEC-12) |
| T-20 | `zb:verify` finds a planted imbalance, gap and sub-ledger mismatch (BR-OPS-08) |

### 17.2 AT-1: two days of trading

Setup: Cash 80,000 and HBL 3,00,000 opening; partners Ali capital 2,00,000 and Usman capital 1,80,000. All amounts Rs.

| # | Day | Entry |
| --- | --- | --- |
| 1 | 1 | Purchase: Aslam, waste 800 kg @ 50 = 40,000, paid now 40,000 from Cash |
| 2 | 1 | Purchase: Karim, waste 600 kg @ 48 = 28,800, paid now 10,000 from Cash |
| 3 | 1 | Payment: Bashir, paid 20,000 from HBL (advance) |
| 4 | 1 | Purchase: Karim, waste 400 kg, rate pending |
| 5 | 1 | Sale: ABC Feeds, protein 300 kg @ 210 = 63,000 |
| 6 | 1 | Sale: Rehman Feeds, protein 200 kg @ 205 = 41,000, received now 41,000 into HBL |
| 7 | 1 | Expense: Wages 8,000, paid from Cash |
| 8 | 1 | Expense: Food 2,500, paid from Cash |
| 9 | 1 | Expense: Tea 600, paid from Cash |
| 10 | 2 | Purchase: Aslam, waste 1,000 kg @ 52 = 52,000 |
| 11 | 2 | Purchase: Bashir, wood 3,000 kg @ 9 = 27,000 |
| 12 | 2 | Rate added to #4: 400 kg @ 49 = 19,600 |
| 13 | 2 | Receipt: ABC Feeds 40,000 into HBL |
| 14 | 2 | Receipt: Rehman Feeds 30,000 into HBL (advance) |
| 15 | 2 | Partner investment: Ali 1,00,000 into HBL |
| 16 | 2 | Partner withdrawal: Usman 10,000 from Cash |
| 17 | 2 | Transfer: 20,000 from HBL to Cash |
| 18 | 2 | Payment: Karim, paid 38,400 from HBL |
| 19 | 2 | Expense: Electricity 15,000, paid from HBL |
| 20 | 2 | Expense: Food entered as 20,000 from Cash, then edited to 2,000 |
| 21 | 2 | Expense: Vehicle Fuel 5,000, paid from Cash |

**Expected results:**

| Check | Expected |
| --- | --- |
| Cash | 21,900 |
| HBL | 4,17,600 |
| Aslam | We owe 52,000 |
| Bashir | We owe 7,000 |
| Karim | 0 |
| ABC Feeds | Owes us 23,000 |
| Rehman Feeds | Advance 30,000 |
| Partner Capital | Ali 3,00,000; Usman 1,80,000 |
| Partner Withdrawals | Usman 10,000 |
| Purchases – Waste / Wood | 1,40,400 / 27,000 |
| Sales – Protein | 1,04,000 |
| Expenses | Wages 8,000; Electricity 15,000; Food 4,500; Tea 600; Vehicle Fuel 5,000 |
| Opening Balance Equity | 0 |
| Trial balance with parties shown | Debit 6,73,000 = Credit 6,73,000 |
| Income vs Spending | Income 1,04,000; Spending 2,00,500; Result −96,500 |
| Kg | Waste bought 2,800; wood 3,000; protein sold 500 |
| #20 audit | One edit record: amount 20,000 → 2,000; one reversal journal |
| #12 rate log | One `rate_changes` row: old empty, new 49 |

### 17.3 AT-2: one day with oil, adjustment and unpaid transport

Setup: Cash 80,000 and HBL 3,00,000 opening, no partner balances (so Opening Balance Equity holds 3,80,000).

| # | Entry |
| --- | --- |
| 1 | Purchase: Aslam, waste 800 kg @ 50 = 40,000, paid now 40,000 from Cash |
| 2 | Purchase: Karim, waste 600 kg @ 48 = 28,800, paid now 10,000 from Cash |
| 3 | Payment: Bashir, paid 20,000 from HBL (advance) |
| 4 | Purchase: Karim, waste 400 kg, rate pending |
| 5 | Sale: ABC Feeds, protein 300 kg @ 210 = 63,000 |
| 6 | Sale: Rehman Traders, oil/fat 100 kg @ 300 = 30,000, received now into HBL |
| 7 | Adjustment on #5: 2,000, reason quality, note "Moisture, agreed with buyer" |
| 8 | Expense: Transport/Freight 4,000, unpaid, party "Test Transporter" (other), linked to sale #5 |
| 9 | Expense: Wages 8,000, paid from Cash |

**Expected results:**

| Check | Expected |
| --- | --- |
| Cash | 22,000 |
| HBL | 3,10,000 |
| Karim | We owe 18,800, plus 400 kg rate pending |
| Bashir | Advance 20,000 |
| ABC Feeds | Owes us 61,000 |
| Test Transporter | We owe 4,000 |
| Aslam, Rehman Traders | 0 |
| Trial balance, account level | Debit 4,77,000 = Credit 4,77,000 |
| Income vs Spending | Income 91,000; Spending 80,800; Result 10,200; note "400 kg rate pending not included" |
| Kg | Waste bought 1,800; protein sold 300; oil/fat sold 100 |

### 17.4 AT-3 to AT-6: controls

| ID | Steps | Expected |
| --- | --- | --- |
| AT-3 | After AT-1, run month-end. Enter HBL statement 4,17,100 and counted cash 20,900 | Close blocked. After an HBL Bank Charges expense of 500 and an Owner cash shortage of 1,000 (PR-23), all checks pass and the month closes. Any new entry dated in that month is refused |
| AT-4 | Test setting `payment_approval_limit` = 50,000. Accountant pays Aslam 60,000 | Saved as awaiting approval; Aslam's balance unchanged. Owner approves: journal posted, balance reduced by 60,000 |
| AT-5 | A purchase of 1,000 kg @ 50 in a closed month is corrected to 48 | Original untouched. A rate correction (RCO) in the open month posts debit Suppliers 2,000, credit Purchases – Waste 2,000 |
| AT-6 | Two supplier profiles for the same person: S-0005 (we owe 10,000) merged into S-0002 (we owe 5,000) | S-0002 shows we owe 15,000 with a transfer line; S-0005 is inactive, marked merged, history unchanged |

## 18. Deployment and environments

Three server-side environments are used: your computer, a staging server and the live server (A). The full picture, including cloud sessions, the laptop and CI, is in `docs/environments.md`; the architecture in `docs/architecture.md`; step-by-step operation in `docs/operations/`. The hosting provider and domain are O-30; size and region O-34.

| Environment | Purpose | Data |
| --- | --- | --- |
| Local | Development | Test data only |
| Staging | Client reviews each release before it goes live; monthly restore test into a temporary database | **Test data only** (P). Restored real data exists only for the minutes of the restore test |
| Production | Live system | Real data |

### 18.1 Server (P, D-41)

- One VPS, Ubuntu 26.04 LTS, built by `infra/provision` (proposed 2 vCPU, 4 GB RAM, 80 GB SSD).
- nginx (HTTPS with Let's Encrypt, renewed automatically), PHP-FPM 8.5 running as its own user, PostgreSQL 18 on the local socket only.
- systemd timers: Laravel scheduler every minute, database backup hourly, uploads backup daily, health check every 15 minutes.
- No Node.js, no Redis, no queue worker, no Docker (ADR-0003, ADR-0004).

### 18.2 Releasing and deploying (P, D-42)

1. Every change reaches `main` through a pull request with green CI (both PHP versions, PostgreSQL 18, guards, privilege check, protected files). The main guard reports any other way in, and step 2 refuses red commits.
2. When CI on `main` is green for the newest commit, you push a version tag (`v1.4.0`). The release workflow checks that CI run, runs the tests again and publishes the release archive and its SHA-256 checksum.
3. You deploy to **staging** with `infra/local/ship-release.sh` (download, check, copy, `zb-deploy-release`). The script: verifies the checksum; unpacks a new release folder; backs up the database; puts the site in maintenance mode; migrates as `zb_migrator`; adds missing system data (BR-OPS-11); re-applies the grants; builds the config, route and event caches; switches to the new release; reloads PHP; leaves maintenance mode; checks `/up`, switching back automatically if it fails.
4. The client approves on staging. You deploy the same archive to production the same way.
5. If anything is wrong: `zb-deploy-release --rollback` (code only, BR-OPS-10).

### 18.3 Rules

- Every change goes to staging first. The client approves on staging before it goes live (A).
- Never test on the live server.
- Database changes only through migrations stored in the code repository (A). Each migration must work with the previous release (BR-OPS-10).
- Nobody edits production data by hand; corrections are entries in the app.
- Claude Code never deploys and has no access to any server (CLAUDE.md).

## 19. Delivery plan and go-live checklist

The build runs in 10 phases, each ending with a gate that must pass before the next starts (A for order). Dates are not set in this document; they are agreed with the client after Phase 0.

### 19.1 Phases

1. **Sign-off.** Client confirms Requirements v1.2 and answers the OPEN items. You approve or change every P item. *Gate: signed v1.2, Section 4 answered.*
2. **Foundation.** Starter kit, PostgreSQL, roles and permissions, settings and setup gate, ledger tables, Posting Service, triggers, voucher numbering, audit log. *Gate: T-02, T-03, T-04, T-10 pass.*
3. **Master data.** Parties, items, money accounts, expense categories, opening balances. *Gate: opening balance postings PR-17 to PR-22 pass T-01.*
4. **Design approval** (can run beside Phase 3). Dashboard, New Purchase on phone, Party Khata shown to the client. *Gate: client approves the look.*
5. **Core entries.** Purchases, sales, rate pending, rate changes, payments, approvals, agreed adjustments, rate corrections. *Gate: T-05 to T-08 pass.*
6. **Other entries.** Expenses (all three modes), partner money, transfers. *Gate: AT-1 and AT-2 match exactly.*
7. **Checks and reports.** Dashboard alerts, daily cash check, daily summary, all reports, both PDFs. *Gate: T-11 to T-14 pass.*
8. **Controls.** Month-end close, reopen, party merge, insights, audit log page, backup status, integrity check. *Gate: AT-3 to AT-6 pass, T-15 and T-20 pass, a backup is restored on staging.*
9. **Trial run.** One month using the system alongside the current Urdu register. Compare cash, bank and party balances every week. Every difference is explained and fixed. *Gate: one full month closes with zero unexplained differences.*
10. **Go-live.** Checklist below completed.

### 19.2 Go-live checklist

- [ ] Requirements v1.2 signed by the client
- [ ] Every OPEN item in Section 4 answered and entered in Settings
- [ ] Every P item approved or changed by you
- [ ] All automated tests pass
- [ ] AT-1 to AT-6 pass on staging
- [ ] Users created; every user has logged in once and changed the password
- [ ] Bank and cash accounts created (O-08)
- [ ] All parties created with correct types and mobiles
- [ ] Expense category list confirmed by the client (O-11)
- [ ] Opening balances entered, Opening Balance Equity figure confirmed by the Owner, marked complete
- [ ] Hourly database and daily uploads backups running; `zb-backup-verify-offsite` shows three PASS lines; storage versioning/Object Lock and lifecycle rules checked (screenshot kept)
- [ ] One restore tested on staging (BR-OPS-04) and one full rebuild drill timed against the 4-hour target (BR-OPS-05)
- [ ] Backup private key and APP_KEY: two copies each, holders recorded (O-37)
- [ ] Monitoring: a test alert reached the Owner and the developer (O-33)
- [ ] HTTPS working, security headers present, server firewall on, SSH key-only login tested (BR-SEC-01, 10, 14)
- [ ] `zb-health-check` reports OK; `zb:verify` passes
- [ ] Two-step login set up for every Owner if O-31 = yes
- [ ] Every account in `docs/secrets.md` is in the company's name with two-factor login
- [ ] Owner and Accountant trained on: every entry form, rate pending, approvals, wallet rule (BR-PAY-09), daily cash check, month-end close
- [ ] Trial-run month closed with zero unexplained differences
- [ ] Handover checklist (`docs/operations/handover.md`) signed

## 20. Out of scope and change control

Anything not described in this document is not part of version 1. Asking for it after sign-off is a change request with its own price and schedule (A).

### 20.1 Not needed, based on client answers (C)

- Tax deducted by buyers
- Cheque tracking (received or issued, O-21)
- Separate mobile wallet accounts
- Returns module
- Loans, taken or given
- Parties who are both supplier and buyer

### 20.2 Kept for future versions (C + A)

- Stock tracking and true profit
- Profit calculation and distribution to partners
- Production tracking (waste in, product out)
- Urdu interface (text files are ready for it, NFR-04)
- Separate mobile app (logic is ready for an API, NFR-09)
- Payroll
- Tax invoices
- Automatic WhatsApp or SMS messages
- Second plant or company (data is ready, D-37)
- Write-off of unrecoverable amounts (O-28)
- Adjustments that increase an amount (O-27)
- Other income, such as bank profit (Section 8.1)
- Export to Excel or CSV

### 20.3 Change control

1. The client asks for a change in writing.
2. You note which sections, rules, tables, pages and tests it affects.
3. You give the client the cost and time.
4. After written approval, this specification gets a new version with a change log row, and Requirements gets a matching version.
5. Only then is the change built, tested on staging and released.

### 20.4 Change log

| Version | Date | Change |
| --- | --- | --- |
| 1.0 (update 8) | 14 October 2026 | M9 (G-31), approved by the client in writing: simple dashboard for the Accountant (`dashboard.balances`, BR-DSH-01); account statement in pages of 100 (BR-RPT-09); monthly lines in the rate and weight reports over 3 months (BR-RPT-10); statement PDF at most 3 months (BR-RPT-11); random public ids in links and filters (BR-SEC-19, `public_id` in §9.1). Cards `docs/cards/M9.md` |
| 1.0 (update 7) | 13 October 2026 | G-30: cloud sessions install PHP packages through `scripts/cloud-composer-cache.sh` (zips built from git into Composer's cache), because the cloud GitHub proxy refuses GitHub zip downloads; the cloud start script runs it; card T0.1 gets cloud blocks for steps 2 and 4; `docs/stack-baseline.md` records the proxy fact and the starter kit's `laravel/pao`, `.npmrc` and `pnpm-workspace.yaml` (kept). No change to the system's rules, laptop or CI |
| 1.0 (update 6) | 13 October 2026 | Pack v4.1: Claude Code builds in cloud sessions and opens pull requests; the developer merges on GitHub and pulls and runs on a Linux Mint laptop. Cloud sessions are prepared by `scripts/cloud-session-start.sh` (PostgreSQL, both test databases, packages, `.env`). Main guard checks every push to `main`; Dependabot pull requests monthly, grouped (BR-SEC-18). Keeps the v4 documents (`README.md`, `docs/README.md`, `docs/frontend.md`, `docs/backend.md`) and the laptop setup (`scripts/setup-local.sh`) |
| 1.0 (update 5) | 13 October 2026 | Pack v4, laptop edition: Claude Code runs on the developer's Linux Mint laptop (no cloud sessions). Code reaches `main` only through `scripts/finish-task.sh`; CI checks every push to `main`; protected files change only with `[spec-change]`. Dependabot pull requests replaced by alerts plus a monthly update session (BR-SEC-18). New documents: `README.md`, `docs/README.md`, `docs/frontend.md`, `docs/backend.md`, `docs/templates/task-report.md`. Laptop app uses database `zb_mill_dev`; tests `zb_mill_test` |
| 1.0 (update 4) | 13 October 2026 | Corrections G-25 to G-29 (`docs/requirements-audit.md`). New: BR-OPS-11 (system data on servers through `SystemDataSeeder`, run by `zb-deploy-release`; local seeders refuse production). Changed: GitHub Free accepted, with a push guard in the hook, a CI main guard and a release gate instead of branch protection (ADR-0012), CI trimmed for 2,000 free Actions minutes, Dependabot monthly (BR-SEC-18); cloud-session network facts; local test user gets the Owner role in local and testing only (T1.3); trial-month notes and restore records in git hold voucher numbers and counts only, never names or amounts (T8.5, BR-OPS-04, runbook §4, `docs/data-handling.md` rule 9) |
| 1.0 (update 3) | 11 October 2026 | Production readiness after a full requirements audit (`docs/requirements-audit.md`, gaps G-01 to G-24). New: D-41 to D-44; O-32 to O-37; BR-USR-07 to 09; BR-GEN-11; BR-SEC-13 to 18; §14.3 BR-OPS-01 to 10; NFR-11, NFR-12; T-16 to T-20; settings key `owner_two_factor_required`; users `must_change_password`, `password_changed_at`; transaction tables `idempotency_key`. Changed: BR-SEC-02 (kit password rule), BR-SEC-06 (resize and re-encode photos), BR-SEC-08 to 12, BR-AUD-01 (no backup download; server commands audited), `backup.view` (status only), §5 (real starter-kit facts: Inertia 3, Wayfinder, vite-plus, Larastan level 7, Pest 4; PHP 8.5 and PostgreSQL 18 on servers; own backup scripts instead of spatie/laravel-backup; Inertia `useForm` instead of React Hook Form + Zod), §13 (English name on PDFs), §15.7 (font via laravel-vite-plugin), §18 rewritten (CI-built releases, human deploy, staging with test data), §19.2 checklist extended. Architecture, ADRs, security, secrets, data-handling, environments and operations documents added |
| 1.0 (update 2) | 7 October 2026 | Added BR-USR-06 (no self-registration or self-service reset); cash_counts.business_closed for dismissed days; unusual-rate check uses weighted average. Detailed task cards added in the repository (docs/cards) |
| 1.0 (update) | 5 October 2026 | Company name confirmed as ZB Poultry Protein Mill; brand rules added (15.7) |
| 1.0 | 5 October 2026 | First version, based on Requirements v1.1 |
