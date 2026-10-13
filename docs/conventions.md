# Engineering conventions (binding)

These conventions fill the gaps the specification leaves to "how". They are binding for every task. Order of authority when two documents disagree:

1. `docs/spec.md` (what the system must do)
2. `docs/cards/M*.md` (exactly what each task builds)
3. this file (how code is written)
4. `docs/glossary.md` (words used in the interface)

If two of these contradict each other, **stop and ask**. Never pick one silently.

---

## 1. Folders, namespaces and names

| Thing | Location / namespace | Name pattern | Example |
| --- | --- | --- | --- |
| Domain module | `app/Domain/<Module>/` | PascalCase plural noun | `app/Domain/Purchases/` |
| Eloquent model | `App\Domain\<Module>\Models` | Singular | `App\Domain\Purchases\Models\Purchase` |
| Action (one use case) | `App\Domain\<Module>\Actions` | Verb + noun | `CreatePurchase`, `AddPurchaseRate`, `CancelPurchase` |
| Policy | `App\Domain\<Module>\Policies` | Model + `Policy` | `PurchasePolicy` |
| Enum | `App\Domain\<Module>\Enums` | Singular | `RateStatus`, `PartyType` |
| Report / read query | `App\Domain\Reports\Queries` | Noun | `TrialBalance`, `PartyKhata` |
| Ledger | `App\Domain\Ledger\` | — | `PostingService`, `PostingRequest`, `PostingLine`, `Models\JournalEntry`, `Models\JournalLine`, `Models\LedgerAccount` |
| Shared support | `App\Support\` | — | `Decimal`, `Warnings\WarningRequired` |
| Controller | `App\Http\Controllers\` | Module + `Controller` | `PurchaseController` |
| Form request | `App\Http\Requests\<Module>\` | `Store`/`Update` + model + `Request` | `StorePurchaseRequest` |
| React page | `resources/js/pages/<module>/` | `index.tsx`, `create.tsx`, `show.tsx`, `edit.tsx` | `resources/js/pages/purchases/create.tsx` |
| Shared React component | `resources/js/components/app/` | PascalCase | `PartyPicker.tsx` |
| Frontend helpers | `resources/js/lib/` | camelCase | `format.ts`, `money.ts` |
| Migration | `database/migrations/` | Laravel default timestamped | `..._create_purchases_table.php` |
| Test | `tests/Feature/<Module>/`, `tests/Unit/`, `tests/Acceptance/`, `tests/Arch/` | `<Thing>Test.php` | `tests/Feature/Purchases/CreatePurchaseTest.php` |

- Every PHP file in `app/Domain` and `app/Support` starts with `declare(strict_types=1);`.
- Route names: `<module>.<action>` in plural, for example `purchases.index`, `purchases.create`, `purchases.store`, `purchases.show`, `purchases.edit`, `purchases.update`, `purchases.cancel`, `purchases.rate`.
- URL paths are exactly those in spec §11.1.
- Database table and column names are exactly those in spec §9. Do not rename, abbreviate or add columns that the spec or a card does not list.

## 2. How a save works (every create, edit, cancel)

```
Controller (thin)
  -> FormRequest validates field shapes (required, types, lengths)
  -> Policy checks permission
  -> Action::handle(...) does everything else inside ONE DB::transaction():
       1. Settings::assertComplete()                 (BR-GEN-07)
       2. Periods::assertDateOpen($entryDate)        (BR-GEN-02, BR-MEC-07)
       3. creates only: Idempotency::existing(Model::class, $user, $key)
          -> if found, return it and stop           (BR-GEN-11, §2a)
       4. business rule checks and warnings          (spec §10)
       5. VoucherNumberService::next('<PREFIX>')     (BR-GEN-05)
       6. save the business record (with idempotency_key on creates)
       7. PostingService::post(...) if it has an amount and is approved
       8. AuditLogger::log(...)
  -> Controller redirects with a flash message that includes the voucher number
```

- Controllers never call `DB::`, never build journal lines and never compute money.
- Actions are plain classes with one public method `handle(...)`. They receive the acting `User` explicitly as the first argument.
- Actions throw `Illuminate\Validation\ValidationException` for rule failures, so messages appear under the right form field.

### 2a. Idempotency key (BR-GEN-11, ADR-0011)

- Every create form makes `idempotency_key = crypto.randomUUID()` when it opens and sends it with the form; "Save and add another" makes a new one after success.
- Form requests validate it as `required|uuid` on create routes.
- `App\Support\Idempotency::existing(string $modelClass, User $user, string $key): ?Model` looks up the row with that key **in the user's company created by the same user**. If found, the Action returns it unchanged and the controller flashes "Already saved. {voucher}" (glossary).
- A unique violation on (`company_id`, `idempotency_key`) during insert (two requests at the same moment) is caught in the Action and answered the same way.

## 3. Money, kg and rate

- Database: `numeric(16,2)` money, `numeric(14,3)` kg, `numeric(14,4)` rate. In Laravel migrations: `$table->decimal('amount', 16, 2)`, `$table->decimal('kg', 14, 3)`, `$table->decimal('rate', 14, 4)`.
- Eloquent casts: `'amount' => 'decimal:2'`, `'kg' => 'decimal:3'`, `'rate' => 'decimal:4'`. These give **strings**.
- PHP arithmetic uses `Brick\Math\BigDecimal` only (installed with `brick/money`). Rounding mode is always `RoundingMode::HALF_UP`.
- `App\Support\Decimal` is the only helper for money maths. It must contain at least:
  - `Decimal::amount(string $kg, string $rate): string` = kg × rate rounded per setting `amount_rounding` (`whole_rupee` → scale 0 then formatted with `.00`; `two_decimals` → scale 2). Always returns a string with exactly 2 decimals.
  - `Decimal::add`, `sub`, `compare`, `isPositive`, `sum(array)`.
- Never: `float`, `double`, `(float)`, `floatval`, `round()`, `number_format()` on money, PHP `+ - * /` on money or kg strings.
- JSON to the browser: money, kg and rate are always **strings**, never numbers.
- React: never uses `parseFloat`, `Number()` or `+` on money for anything that is saved. The live total preview uses `resources/js/lib/money.ts` (string-based integer maths in paisa). The server's total is the saved value.

## 4. Dates and time

- `entry_date` and `posting_date` are `date` columns (no time). Stored and compared as `Y-m-d` strings or `CarbonImmutable` at start of day.
- "Today" means today in `Asia/Karachi`: `CarbonImmutable::now('Asia/Karachi')->toDateString()`. Use the helper `App\Support\Today::date()` everywhere, so tests can freeze it with `Carbon::setTestNow()`.
- Timestamps (`created_at` etc.) are stored in UTC; the interface shows them in Asia/Karachi.
- `config/app.php` timezone stays `UTC`.

## 5. Warnings (things the user may confirm and continue)

Warnings are not errors. One mechanism for all of them (unusual rate, duplicate party, duplicate entry, credit limit, negative cash, bank reference missing):

- Each warning has a fixed code: `unusual_rate`, `duplicate_party`, `duplicate_entry`, `credit_limit`, `negative_cash`, `missing_reference`, `overpayment_info`.
- Every form request accepts an optional array field `confirmed_warnings` (list of codes).
- An Action that detects a warning whose code is NOT in `confirmed_warnings` throws `App\Support\Warnings\WarningRequired` with: `code`, `message` (plain English from `docs/glossary.md` §4), and `details` (array).
- The exception renders as HTTP 422 with JSON `{ "warning": { "code", "message", "details" } }` for Inertia; the page opens `WarningDialog` with "Go back" and "Save anyway". "Save anyway" resubmits with the code added to `confirmed_warnings`.
- When a save succeeds with confirmed warnings, the Action writes one audit row per confirmed code: action `warning_confirmed`, `new_values = { code, details }`.
- `overpayment_info` is information only: it is shown, never blocks, never needs confirming.

## 6. Ledger rules in code

- Only `App\Domain\Ledger\PostingService` creates `JournalEntry` and `JournalLine` rows. Their models throw `LogicException` in the `creating` event unless `PostingService::isPosting()` is true.
- `PostingService::post(PostingRequest $request): JournalEntry` and `PostingService::reverse(JournalEntry $original, string $postingDate, string $description): JournalEntry`.
- `PostingRequest` fields: `postingDate` (Y-m-d), `sourceType` (morph alias string, see §7), `sourceId` (int), `description` (string), `lines` (list of `PostingLine`).
- `PostingLine` fields: `accountCode` (string, for example `'2000'`) **or** `ledgerAccountId` (int), `side` (`debit`|`credit`), `amount` (string, 2 decimals, > 0), `partyId` (?int), `itemId` (?int).
- After inserting the lines, PostingService runs `SET CONSTRAINTS journal_balance_check IMMEDIATE` so the database balance check runs inside the same transaction (this also makes the check work inside tests, which never commit).
- Posting rules PR-01 to PR-27 are implemented as named static builders in `App\Domain\Ledger\PostingRules` (one method per rule ID, for example `PostingRules::pr01PurchaseFixedRate(Purchase $p): PostingRequest`). Each method has a doc comment quoting the rule ID and the debit/credit from spec §8.3.

## 7. Morph aliases (`source_type`, `auditable_type`, `attachable_type`)

Use `Relation::enforceMorphMap` in `AppServiceProvider` with exactly these aliases:

`purchase`, `sale`, `adjustment`, `payment`, `expense`, `partner_transaction`, `transfer`, `opening_balance`, `rate_correction`, `cash_count`, `period`, `party`, `item`, `money_account`, `expense_category`, `setting`, `user`, `party_merge`, `month_end_adjustment`.

`party_merge` and `month_end_adjustment` are journal sources without their own table; their `source_id` is the target party id and the period id respectively.

## 8. Voucher numbers and codes

| Doc type key | Prefix | Width | Example |
| --- | --- | --- | --- |
| `purchase` | PUR | 6 | PUR-000001 |
| `sale` | SAL | 6 | SAL-000001 |
| `adjustment` | ADJ | 6 | ADJ-000001 |
| `payment_paid` | PAY | 6 | PAY-000001 |
| `payment_received` | RCV | 6 | RCV-000001 |
| `expense` | EXP | 6 | EXP-000001 |
| `partner_transaction` | PTN | 6 | PTN-000001 |
| `transfer` | TRF | 6 | TRF-000001 |
| `opening_balance` | OPB | 6 | OPB-000001 |
| `rate_correction` | RCO | 6 | RCO-000001 |
| `cash_adjustment` | CSH | 6 | CSH-000001 |
| `journal` | JV | 6 | JV-000001 |
| `party_supplier` | S | 4 | S-0001 |
| `party_buyer` | B | 4 | B-0001 |
| `party_partner` | P | 4 | P-0001 |
| `party_employee` | E | 4 | E-0001 |
| `party_other` | O | 4 | O-0001 |

- One sequence per company per doc type. Never reset. A failed save rolls the number back (it is inside the same transaction).
- A payment that changes direction on edit keeps its original voucher number.

## 9. Permissions in code

- Every controller action calls `$this->authorize(...)` or uses `Gate`, with the permission keys from spec §7 expanded as: `<area>.view`, `<area>.create`, `<area>.update`, `<area>.cancel` (for `purchase`, `sale`, `payment`, `expense`, `transfer`), `adjustment.view|create|cancel`, plus the single keys listed in spec §7.
- "Add rate" and "edit" both use `<area>.update`.
- The sidebar and buttons hide what the user cannot do, but the server check is the real protection.
- Owner-only screens and sections use the permission keys, never `hasRole('owner')`.

## 10. Inertia and frontend

- Shared props (via `HandleInertiaRequests`): `auth.user` (id, name, roles, permissions list), `settings.formats` (`kg_decimals`, `rate_decimals`, `amount_rounding`, `number_format`, `date_format`), `flash.success`, `flash.voucher`, `app.env_name` (`staging` shows a banner; from `ZB_ENV_NAME`). Never share secrets, `.env` values or other users' data.
- Forms use Inertia's `useForm` (spec §5, D-44). Server validation errors always show under the field.
- Routes in React come from **Wayfinder** (generated in `resources/js/actions` and `resources/js/routes`; git-ignored, rebuilt by `npm run build` or `php artisan wayfinder:generate --with-form`). Never hard-code URLs; never add Ziggy.
- No `dangerouslySetInnerHTML`, no inline event-handler strings, no `eval`, no scripts, styles, fonts or images from other sites (CSP, BR-SEC-14). Inline `<script>` in Blade must carry `nonce="{{ Vite::cspNonce() }}"`.
- Lint and format with `npm run check` (vite-plus); fix with `npm run check:fix`. Type check with `npm run types:check`. Unit tests with `npm test` (`vp test`, from T2.1).
- All interface words come from `docs/glossary.md`. Do not invent labels.
- Brand: `docs/brand.md`. Logo only on login and PDFs.
- Mobile-first pages (spec §11.1) are designed at 375 px width first.
- Numbers: right-aligned, `tabular-nums`, formatted only through `resources/js/lib/format.ts`.

## 11. Tests

- Pest 4, on PostgreSQL (test role `zb_test`, database `zb_mill_test`; never the production role names). Use `RefreshDatabase`.
- Seed in every feature test: `SystemDataSeeder` (company, settings keys, roles and permissions, system ledger accounts, items, as far as the tasks so far have built them; BR-OPS-11), then the test settings (`TestSettingsSeeder`). Put this in `tests/Pest.php` with `beforeEach`. Never seed test data from `SystemDataSeeder`.
- Helpers in `tests/Pest.php`: `owner(): User`, `accountant(): User`, `freezeToday(string $ymd)`, `ledgerBalance(string $code, ?Party $party = null): string` (debit minus credit), `partyBalance(Party $p): array{words: string, amount: string}`.
- Money assertions compare **exact strings**, for example `expect($cash)->toBe('21900.00')`.
- Test names start with the rule or test ID: `it('BR-PUR-03 computes amount on the server', ...)`, `it('T-05 rate pending creates no journal', ...)`.
- Acceptance tests (`tests/Acceptance`) read `docs/acceptance/*.json` and run every entry through the real Actions. They never contain their own copies of numbers.
- Test settings values (from `TestSettingsSeeder`) are **test values only**, never the client's:

| Key | Test value |
| --- | --- |
| `company_name` | ZB Poultry Protein Mill |
| `system_start_date` | 2026-01-01 |
| `payment_approval_limit` | 50000.00 |
| `new_party_first_payment_limit` | 100000.00 |
| `expense_party_required_limit` | 100000.00 |
| `unusual_rate_percent` | 30 |
| `unusual_rate_lookback` | 5 |
| `rate_pending_age_days` | 7 |
| `advance_age_days` | 30 |
| `kg_decimals` | 3 |
| `rate_decimals` | 2 |
| `amount_rounding` | two_decimals |
| `number_format` | lakh |
| `date_format` | d-m-Y |
| `plant_has_scale` | true |
| `slip_no_required` | false |
| `slip_photo_required` | false |
| `insights_period_months` | 3 |
| `opening_balances_completed` | false |
| `owner_two_factor_required` | false |

Tests for a specific limit rule override the one setting they test inside that test only.

## 12. Security and operations in code

- **Config:** call `env()` only inside `config/*.php`. Servers cache the configuration, so `env()` elsewhere returns null in production.
- **Database connections:** `pgsql` (web role) for everything; `pgsql_migrator` exists only for `migrate --database=pgsql_migrator` during deploys (T1.11). Code never selects a connection by name.
- **Never** disable, drop or bypass database triggers, use `SET session_replication_role`, or run `TRUNCATE` in application code. Tests that must plant bad data for `zb:verify` do it in the test only, through the test role.
- **Migrations work with the previous release** (BR-OPS-10): add tables and nullable or defaulted columns first; stop using a column in one release and remove it in a later one; never rename in place (add new, copy, switch, remove later). Every migration has a working `down()` for development, but production never runs it.
- **Housekeeping deletes:** the only allowed deletes are framework housekeeping (sessions, cache). Ending a user's sessions goes through `App\Support\Sessions::endFor(User $user, ?string $exceptSessionId = null)`, whose delete line carries `// guard:allow sessions are housekeeping (BR-SEC-13)`.
- **Logs:** log record type and ID, never personal data, amounts, passwords, tokens or request bodies (BR-SEC-17). `Log::warning('purchase {id} rate change refused', ['id' => $id])`.
- **Uploads:** only through `StoreAttachment` (T2.8): content-checked type, 5 MB, images re-encoded with GD, UUID name, disk `attachments` with permissions `file.private = 0640`, `dir.private = 0750` (so the backup user can read them).
- **Outbound calls:** the application makes no HTTP calls to other services, except the kit's password breach check (BR-SEC-02). Anything else needs a spec change.
- **Server commands** (`zb:*` artisan commands) that change users write an audit row with action `server_command` and `user_id` null.

## 13. Things that are always forbidden

- Deleting financial or master rows (`delete()`, `forceDelete()`, `destroy()`, `truncate()`, `SoftDeletes`), cascading deletes in migrations.
- Floats for money, kg or rate (see §3).
- Writing journal rows outside `PostingService`.
- Adding a package, table, column, route, page, setting or permission that the spec or the current card does not list.
- Changing an existing test to make it pass.
- Editing protected files (CLAUDE.md lists them).
- Real client data, real passwords, real server details anywhere in the repository.
- Loading anything (scripts, fonts, images, styles) from another site, or sending data to one.
- `env()` outside `config/`; disabling or bypassing database triggers; hand-written SQL that changes production data.

`scripts/guard.sh` checks many of these automatically in CI. A line that must break a guard rule for a reviewed reason carries the comment `guard:allow <reason>`; every such line is reviewed by the human.
