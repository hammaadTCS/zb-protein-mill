# Glossary and interface words (binding)

The people using this system are the owner and the accountant of ZB Poultry Protein Mill. They think in terms of their register (khata), not accounting textbooks. Use exactly these words on screen, in PDFs, in messages and in code identifiers where shown. Do not invent synonyms.

## 1. Business terms

| Term on screen | Code identifier | Meaning |
| --- | --- | --- |
| Party | `party` | Any person or business the mill deals with |
| Supplier | `supplier` | Sells waste or wood to the mill |
| Buyer | `buyer` | Buys protein product or oil/fat from the mill |
| Partner | `partner` | Owner-investor of the business |
| Employee | `employee` | Staff member; can receive salary advances or cash to spend |
| Other | `other` | Transporters, shops, mechanics and anyone else |
| Waste | item `Waste` | Chicken waste (raw material) |
| Wood | item `Wood` | Fuel for boiling |
| Protein product | item `Protein product` | Main product sold to feed factories |
| Oil/Fat | item `Oil/Fat` | Second product, sold separately |
| Purchase | `purchase` | Waste or wood bought from a supplier |
| Sale | `sale` | Protein product or oil/fat sold to a buyer |
| Payment | `payment` (direction `paid`) | Money the mill pays out to a party |
| Receipt | `payment` (direction `received`) | Money the mill receives from a party |
| Expense | `expense` | Running cost (wages, food, electricity, transport…) |
| Partner investment | `partner_transaction` type `investment` | Money a partner puts in |
| Partner withdrawal | `partner_transaction` type `withdrawal` | Money a partner takes out |
| Transfer | `transfer` | Money moved between cash and bank, or bank and bank |
| Agreed adjustment | `adjustment` | Amount both sides agreed to deduct after a purchase or sale |
| Rate correction | `rate_correction` | Rate change for an entry in a closed month, posted in the open month |
| Opening balance | `opening_balance` | Balance on the system start date |
| Khata | — (page title "Khata") | A party's full history with running balance |
| Statement | — | PDF of the khata for a date range |
| Entry slip | — | PDF for one purchase or sale |
| Voucher number | `voucher_no` | Running number of every entry, for example PUR-000012 |
| Rate pending | `rate_status = pending` | Kg recorded, rate not decided yet |
| Our weight | `our_weight_kg` | Weight on the mill's own scale (optional) |
| Weight slip number | `slip_no` | Number on the weighbridge or scale slip |
| Paid now | — | Payment made at the same time as a purchase |
| Received now | — | Money received at the same time as a sale |
| Handled by | `handled_by_party_id` | Employee who physically gave or took the cash |
| Cash count | `cash_count` | Cash physically counted at day end |
| Month-end close | `period` close | Monthly matching and lock |
| Income vs Spending | report R-08 | Money earned minus money spent. **Not profit** |
| Brought forward | `brought_forward` | The balance at the top of a later page of a statement (BR-RPT-09) |

## 2. Balance words (never a minus sign)

| Party type | Ledger side | Words on screen |
| --- | --- | --- |
| Supplier, Other | credit | We owe |
| Supplier, Other | debit | Advance |
| Buyer | debit | Owes us |
| Buyer | credit | Advance |
| Employee | debit | Owes us |
| Employee | credit | We owe |
| Partner | — | Invested / Withdrawn / Net invested |
| Any | zero | Settled |

Format: `We owe Rs 52,000` / `Advance Rs 20,000` / `Settled`. When rate-pending kg exist, add a second line: `+ 400 kg rate pending`.

## 3. Buttons and actions

| Use | Never use |
| --- | --- |
| Save | Submit, OK |
| Save and add another | Save & new |
| Cancel entry (needs a reason) | Delete, Remove, Void |
| Add rate | Update rate (for a pending entry) |
| Edit | Modify |
| Approve / Reject | Accept / Decline |
| Close month | Lock period, Finalize |
| Reopen month | Unlock |
| Share PDF | Export, Download (for statements and slips) |
| Go back / Save anyway (warning dialog) | Cancel / Continue |
| Mark reviewed | Acknowledge |

The plain "Cancel" link that closes a dialog without saving is labelled **Close**, so it is never confused with "Cancel entry".

## 4. Messages (exact text)

| Code | Message |
| --- | --- |
| `unusual_rate` | This rate is {percent}% {higher/lower} than the recent average of Rs {average}. Check it before saving. |
| `duplicate_party` | A party with this {mobile/name} already exists: {name} ({code}). |
| `duplicate_entry` | A {purchase/sale} with the same party, item, kg and date already exists ({voucher}). |
| `credit_limit` | After this sale {buyer} will owe Rs {amount}, above the credit limit of Rs {limit}. |
| `negative_cash` | Cash will go below zero. An entry may be missing. |
| `missing_reference` | No transfer reference entered for a bank payment. |
| `overpayment_info` | This is more than the balance. The extra Rs {amount} will show as an advance. |
| Approval pending | Saved. Waiting for owner approval. Balances will change after approval. |
| Closed month | {Month Year} is closed. Make this change as a correction in an open month. |
| Setup incomplete | Setup is not complete. Fill in all required settings before entering transactions. |
| Concurrent edit | This entry was changed by another user. Reload. |
| Income vs Spending banner | This is not profit. Stock is not recorded, so months with heavy buying look worse and months with heavy selling look better. |
| Success toast | Saved. {voucher number} |
| Already saved (BR-GEN-11) | Already saved. {voucher number} |
| Temporary password (BR-USR-08) | Choose a new password to continue. The one you were given was temporary. |
| Two-step login required (BR-SEC-09) | Turn on two-step login to continue. It is required for owners. |
| Session ended (BR-SEC-13) | You have been logged out. Please log in again. |
| Account disabled | This account is disabled. Ask the owner. |
| Staging banner | TEST COPY – entries here are not real. |
| Last owner (BR-USR-04) | At least one active owner is required. |
| Monthly lines (BR-RPT-10) | Periods longer than 3 months show one line per month. |
| Statement too long (BR-RPT-11) | A statement PDF can cover at most 3 months. Choose a shorter period. |

## 5. Words never used anywhere in the interface

Profit (except inside the banner above), Loss, Debit, Credit (except on the Trial balance page and owner-only journal lines), Accounts payable, Accounts receivable, Customer, Vendor, Invoice, Bill, Cheque, Loan, Stock, Inventory, Delete, Void.

## 6. Internal and operations terms (never shown on screen)

These are code and operations words, not interface labels. They are recorded so documents and code use one name.

| Term | Code identifier | Meaning |
| --- | --- | --- |
| Seal row | `journal_seals` | One row written per journal entry at the end of its save; the database refuses the whole save when the entry has fewer than two lines or its debit does not equal its credit (BR-LED-11, §9.7) |
| Daily backup | `zb:backup` (daily) | Encrypted copy of the database and uploads, made once a day and kept 35 days (BR-OPS-01) |
| Monthly backup | `zb:backup` (monthly) | Encrypted copy made on the 1st of each month and kept 7 years (BR-OPS-01) |
| Release | `releases/<version>`, `current` | One deployed version folder; the `current` link points at the live one (BR-OPS-10) |
| Roll-back | `zb-deploy-release --rollback` | Repoints `current` to the previous release; one command (BR-OPS-10) |
