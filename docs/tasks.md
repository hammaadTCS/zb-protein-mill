# Build tasks – ZB Poultry Protein Mill Accounts System

74 tasks in 9 milestones, in build order. Each task depends only on tasks above it. The full instructions for each task are in its card (`docs/cards/`); this file is the index and the only document Claude ticks.

## Rules for every task

1. Start from a green `main`: all tests pass before anything changes.
2. One task = one Claude session = one branch = one pull request, merged by the human on GitHub when every check is green.
3. Build only what the card's "Build exactly" lists.
4. Tests first for anything that posts money; full suite before finishing; existing tests are never edited to pass.
5. The task is done only when the card's "Done when" is proven.
6. Tick the box here in the same pull request.

Tasks marked **[Human]** are yours; Claude only prepares what the card says.

## M0: Setup  ·  card file `docs/cards/M0.md`

- [x] **T0.1** Laravel app, React starter kit, Pest, PostgreSQL, CI. *Done when:* `composer install`, `npm ci`, `npm run build`, `php artisan migrate`, `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `npm run check`, `npm run types:check` all pass locally and in CI (both PHP jobs); `bash scripts/secret-scan.sh` passes; `docs/installed-versions.md` exists.
- [x] **T0.2** Project files check, brand icons, remove kit features the spec forbids. *Done when:* all tests pass; the removed features are unreachable; favicon shows the ZB monogram.
- [x] **T0.3** Architecture tests and guard script in CI. *Done when:* CI shows Pint, Larastan, vite-plus check, TypeScript, guard, secret scan and tests all green.

## M1: Accounting core and security baseline (no screens)  ·  card file `docs/cards/M1.md`

- [x] **T1.1** Companies and company scope. *Done when:* tests pass; every later business model uses `BelongsToCompany`.
- [x] **T1.2** Settings, setup gate, test settings. *Done when:* tests pass; no production default exists for any OPEN value.
- [x] **T1.3** Roles and permissions. *Done when:* tests pass.
- [x] **T1.4** Ledger accounts. *Done when:* tests pass.
- [x] **T1.5** Journal tables and database protections. *Done when:* tests pass.
- [x] **T1.6** Voucher numbering. *Done when:* tests pass.
- [x] **T1.7** Posting Service. *Done when:* tests pass; arch test confirms only the Ledger module touches journal models.
- [x] **T1.8** Audit log. *Done when:* tests pass.
- [x] **T1.9** Periods and date rules. *Done when:* tests pass.
- [x] **T1.10** Trial balance query. *Done when:* tests pass.
- [x] **T1.11** Database roles: migrator connection and privilege check. *Done when:* tests pass; the CI step "Database privilege check" is green and its log lists every table.
- [x] **T1.12** Security baseline. *Done when:* tests pass; after `npm run build`, with `CSP_ENABLED=true php artisan serve` running, `curl -si http://127.0.0.1:8000/login` shows the CSP header and the same nonce on every `<script>` tag (paste the relevant lines in the pull request).
- [x] **T1.13** Idempotency helper. *Done when:* tests pass.

## M2: First complete slice  ·  card file `docs/cards/M2.md`

- [x] **T2.1** App shell, brand, formatting helpers. *Done when:* `npm test`, `npm run check`, `npm run types:check` and PHP tests pass; login and the empty dashboard checked by you at phone width (375 px) and on desktop before you finish the task.
- [x] **T2.2** Money accounts. *Done when:* tests pass; page works on desktop.
- [x] **T2.3** Items. *Done when:* tests pass.
- [x] **T2.4** Parties backend. *Done when:* tests pass.
- [x] **T2.5** Parties UI. *Done when:* tests pass; add-party works on phone width.
- [x] **T2.6** Opening balances. *Done when:* tests pass.
- [x] **T2.7** Purchases with fixed rate, payments table, "paid now". *Done when:* tests pass.
- [x] **T2.8** Purchase pages and attachments. *Done when:* tests pass; page 4 checked by you at 375 px and on desktop before you finish the task.
- [x] **T2.9** Party profile with khata, trial balance page. *Done when:* tests pass.
- [ ] **T2.10** Slice review with the client [Human]. *Done when:* feedback file is committed; no accepted change is left without a decision.

## M3: Core entries  ·  card file `docs/cards/M3.md`

- [x] **T3.1** Rate pending and adding the rate later. *Done when:* tests pass.
- [x] **T3.2** Edit and cancel purchases. *Done when:* tests pass.
- [x] **T3.3** Purchase warnings and weight-slip settings. *Done when:* tests pass.
- [x] **T3.4** Sales. *Done when:* tests pass.
- [x] **T3.5** Payments and receipts (backend). *Done when:* tests pass.
- [x] **T3.6** Payment pages. *Done when:* tests pass; screenshots at 375 px.
- [x] **T3.7** Payment approvals. *Done when:* tests pass.
- [x] **T3.8** Negative cash warning and shared dialogs. *Done when:* tests pass.
- [x] **T3.9** Change history and journal lines on detail pages. *Done when:* tests pass.

## M4: Remaining entries  ·  card file `docs/cards/M4.md`

- [x] **T4.1** Expense categories. *Done when:* tests pass.
- [x] **T4.2** Expenses (backend). *Done when:* tests pass.
- [x] **T4.3** Expense pages. *Done when:* tests pass; you note in the pull request the timed check showing a Tea expense saved on a phone in under 10 seconds.
- [x] **T4.4** Partner money. *Done when:* tests pass.
- [x] **T4.5** Transfers. *Done when:* tests pass.
- [x] **T4.6** Agreed adjustments. *Done when:* tests pass.
- [x] **T4.7** Rate corrections for closed months. *Done when:* tests pass.
- [x] **T4.8** Acceptance runner, AT-1 and AT-2. *Done when:* both pass in CI.

## M5: Controls and settings  ·  card file `docs/cards/M5.md`

- [x] **T5.1** Company settings page. *Done when:* tests pass.
- [x] **T5.2** Users page and account safety commands. *Done when:* tests pass.
- [x] **T5.3** Permission tests across every route. *Done when:* T-09 passes and covers every route.
- [x] **T5.4** Party review and bank-change alerts. *Done when:* tests pass.
- [x] **T5.5** Audit log page. *Done when:* tests pass.
- [x] **T5.6** Dashboard. *Done when:* tests pass; screenshot at 375 px.

## M6: Checks and reports  ·  card file `docs/cards/M6.md`

- [x] **T6.1** Daily cash check. *Done when:* tests pass.
- [x] **T6.2** Daily summary. *Done when:* tests pass.
- [x] **T6.3** Ageing service. *Done when:* T-12 passes.
- [x] **T6.4** Pending balances report. *Done when:* tests pass.
- [x] **T6.5** Accounts list and account statement. *Done when:* tests pass.
- [x] **T6.6** Expense report. *Done when:* tests pass.
- [x] **T6.7** Income vs Spending. *Done when:* tests pass.
- [x] **T6.8** Rate, weight difference and adjustments reports. *Done when:* T-13 passes.
- [x] **T6.9** Reports home and printing. *Done when:* tests pass; printed samples of the trial balance and pending balances checked by you before you finish the task.
- [x] **T6.10** Statement and entry slip PDFs. *Done when:* T-14 passes.

## M7: Month-end and remaining controls  ·  card file `docs/cards/M7.md`

- [x] **T7.1** Month-end close (backend). *Done when:* tests pass.
- [x] **T7.2** Month-end pages and reopen. *Done when:* tests pass.
- [x] **T7.3** Opening balances lock. *Done when:* tests pass.
- [x] **T7.4** Merge duplicate parties. *Done when:* tests pass.
- [x] **T7.5** Business insights. *Done when:* tests pass.
- [ ] **T7.6** Backup status page. *Done when:* tests pass. **[Human]** after T8.3: one restore done on staging (runbook §4) and recorded in `docs/release-checks.md`.
- [x] **T7.7** Acceptance AT-3 to AT-6. *Done when:* all four pass in CI.
- [ ] **T7.8** Integrity check command. *Done when:* tests pass; the schedule is listed by `php artisan schedule:list`.

## M8: Release and handover  ·  card file `docs/cards/M8.md`

- [ ] **T8.1** Security checklist. *Done when:* every row filled; tests pass.
- [ ] **T8.2** Performance check. *Done when:* every report and list loads in under 3 seconds on the measured machine (NFR-02), with the timings and sizes recorded.
- [ ] **T8.3** Staging server and first deploy [Human + Claude]. *Done when:* staging works from the documents alone; release checks updated.
- [ ] **T8.4** Client review on staging [Human]. *Done when:* every comment has a decision.
- [ ] **T8.5** Trial month alongside the Urdu register [Human]. *Done when:* the month closes in the system with zero unexplained differences.
- [ ] **T8.6** Production server and go-live [Human]. *Done when:* every box is ticked.
- [ ] **T8.7** Handover to the company [Human]. *Done when:* the handover sheet is signed and stored with the release checks.
