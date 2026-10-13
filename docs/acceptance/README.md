# Acceptance data (binding, protected)

These JSON files are the contract for spec §17. Every number in them was verified by an independent simulation of the posting rules before the files were written. Tests must **load** these files and must never copy numbers into test code.

| File | Cases | Used from task |
| --- | --- | --- |
| `at1.json` | AT-1 two days of trading | T4.8 (also partial checks in T2.7–T4.5) |
| `at2.json` | AT-2 oil, adjustment, unpaid transport | T4.8 |
| `controls.json` | AT-3 month-end, AT-4 approval, AT-5 rate correction, AT-6 merge | T4.7 (AT-5 partly), T7.7 |

## Runner (built in task T4.8, extended in T7.7)

`tests/Support/AcceptanceRunner.php` reads a file and executes it through the **real Action classes** (never HTTP, never direct model writes):

1. `freezeToday(today)`.
2. Create money accounts (`CreateMoneyAccount`), parties (`CreateParty`), opening balances (`SetOpeningBalance`), then mark opening balances complete.
3. Run `entries` (or `steps`) in order. Each entry runs as the user in `"as"` (`owner` by default, `accountant` when stated).
4. Keep a map from `ref` to the created record so later entries can target it.
5. Never pass `confirmed_warnings`. The data is built so no warning fires. If a `WarningRequired` is thrown, the test fails: it means the code fires a warning the spec does not expect.
6. Assert every key in `expected`.

## Entry types

| `type` | Action | Fields |
| --- | --- | --- |
| `purchase` | `CreatePurchase` | `date`, `party`, `item` (item name), `kg`, `rate` (null = rate pending), optional `paid_now {amount, money_account, reference_no?}` |
| `sale` | `CreateSale` | same as purchase, `received_now` instead of `paid_now` |
| `add_rate` | `AddPurchaseRate` / `AddSaleRate` | `target` (ref), `rate`, `date` (the day the rate is added) |
| `edit_purchase` | `UpdatePurchase` | `target`, `changes` |
| `payment` | `CreatePayment` | `direction` (`paid`/`received`), `date`, `party`, `amount`, `money_account`, `reference_no?` |
| `approve` | `ApprovePayment` | `target` |
| `expense` | `CreateExpense` | `date`, `category` (name), `amount`, `payment_mode` (`paid`/`unpaid`/`paid_by_employee`), `money_account?`, `party?`, `linked_sale?`, `linked_purchase?`, `note` |
| `edit_expense` | `UpdateExpense` | `target`, `changes` |
| `partner` | `CreatePartnerTransaction` | `partner_type`, `date`, `party`, `amount`, `money_account` |
| `transfer` | `CreateTransfer` | `date`, `from`, `to`, `amount`, `reference_no?` |
| `adjustment` | `CreateAdjustment` | `target`, `date`, `amount`, `reason`, `note` |
| `set_today` | — | `date` |
| `bank_statement` | `RecordBankStatement` | `period` (YYYY-MM), `money_account`, `statement_balance` |
| `cash_count` | `CreateCashCount` | `date`, `money_account`, `counted_amount` |
| `cash_shortage` | `PostCashDifference` | `period`, `money_account`, `amount`, `note` |
| `close_month` | `CloseMonth` | `period`, `expect.closed`, `expect.failing_checks?` |
| `rate_correction` | `CreateRateCorrection` | `target`, `date`, `new_rate`, `reason` |
| `merge` | `MergeParties` | `source`, `target` |
| `attempt` | the action inside | `action` (an entry), `expect_error_contains` (text that must appear in the validation error) |

## Expected keys

| Key | Meaning |
| --- | --- |
| `money_accounts.<key>` | Ledger balance of that cash or bank account (debit minus credit), exact string |
| `parties.<key>` | `words` and `amount` from the balance words rule (glossary §2); `pending_kg` = rate-pending kg; optional `status`, `merged_into` |
| `partners.<key>` | Invested, withdrawn, net (R-09) |
| `ledger_accounts.<code>` | `side` (`debit`, `credit` or `zero`) and absolute `amount`. `money:<key>` = that money account's ledger account; `category:<name>` = that expense category's ledger account |
| `trial_balance` | Totals at account level and with parties shown (R-07) |
| `income_vs_spending` | R-08 for the date range |
| `kg` | Totals of active entries by item: `<Item name>_bought` and `<Item name>_sold` |
| `vouchers` | Voucher number per entry `no`; `<no>.paid_now` / `<no>.received_now` for the linked payment |
| `audit`, `reversal_journals`, `rate_changes` | Exact log rows expected |
| `insights` | R-13 values for the whole data range, 4 decimals for ratios, 2 for rupees (checked from task T7.5) |

Checks for a report that does not exist yet are skipped **only** by the task that builds the runner (T4.8) and must be switched on by the task that builds that report (T6.7 for income vs spending, T7.5 for insights). Each of those tasks lists this in its card.
