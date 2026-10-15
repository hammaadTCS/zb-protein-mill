# ZB Poultry Protein Mill – Accounts Management System

**Requirements and Scope Document**

**Prepared for:** ZB Poultry Protein Mill

**Prepared by:** [Your Name]

**Date:** 11 October 2026

**Version:** 1.2 (Security, backups and server added). Markdown copy of `docs/client/Accounts_System_Requirements_v1.2.docx`; the Word file is what the client signs.

# 1. Purpose of This Document

This document explains our understanding of your business and how the new accounts system will work. Version 1.1 includes your answers to our follow-up questions and the changes they made to the design. Version 1.2 adds how your data is kept safe: logins, backups, the server, and what you receive at handover.

Please read it and confirm. Once confirmed, development will follow this document. Any new requirement after confirmation will be discussed and handled as a separate change.

# 2. Our Understanding of Your Business

- You buy chicken waste and wood from different suppliers. There are no middlemen.

- The waste is boiled into a protein product. Wood is used as fuel. Oil/fat is also produced and sold separately.

- Protein product is sold to feed factories. Oil/fat is sold to its own buyers.

- Suppliers and buyers are always different people.

- Rates change from person to person and from day to day. One rate per delivery.

- Sometimes the rate is decided after delivery. Any deduction or rejection is settled on site, and the final agreed figure is written down.

- For payment, the supplier's weight is final for purchases, and the buyer's weight is final for sales.

- Payments are made and received in cash or by bank transfer. No cheques. Buyers pay the full agreed amount, with no tax deducted.

- Payments can be full, partial or in advance.

- The business pays for some transport.

- Partners invest money and sometimes withdraw it. The business has no loans.

# 3. Goals of the System

- Record every rupee coming in and going out.

- Know at any time who owes the business and whom the business owes.

- See cash and bank balances at any time.

- At month end, all accounts balance (debits equal credits) and match the real bank statement and cash in hand.

- Protect records from mistakes and misuse.

- Give the owner useful indicators about the business from the data already recorded.

# 4. What Version 1 Includes

| **Area** | **What it does** |
| --- | --- |
| Parties | One list of suppliers, buyers, partners, employees and others, each with a profile and khata |
| Items | Waste (raw material), Wood (fuel), Protein product, Oil/Fat |
| Purchases | Waste and wood: kg, rate, total. Rate can be added later. Weight slip number and photo |
| Sales | Protein product and oil/fat: kg, rate, total. Rate can be added later. Weight slip number and photo |
| Agreed adjustments | Record an amount agreed between both sides after a purchase or sale (quality, weight, freight, other), without changing the original entry |
| Payments and receipts | Full, partial or advance. Cash or bank transfer, with transaction reference |
| Expenses | Category, amount, required note. Paid now, or unpaid (owed to a party such as a transporter) and paid later |
| Transport | Transport expenses, optionally linked to a purchase or sale, with transporter as a party |
| Partner money | Partner investments and withdrawals |
| Transfers | Cash to bank, bank to cash, bank to bank |
| Accounts | Cash and each bank account, with live balance |
| Reports | Khata, balances, expenses, trial balance, weight difference, business insights and more |
| Month-end close | Match bank and cash, check pending rates, then lock the month |

# 5. How the System Works: Step by Step

- **Setup:** Add parties, accounts and items. Enter starting balances on a fixed start date.

- **Buying:** Enter supplier, item, kg (supplier's weight), rate, and slip number. Optionally enter our own weight. The total is added to the supplier's balance. If the rate is not decided, it goes to the Rate Pending list.

- **Paying a supplier:** Enter supplier, amount, cash or bank. Payment before delivery shows as an advance.

- **Selling:** Enter buyer, product, kg (buyer's weight), rate, and slip number. Optionally enter the weight that left the plant.

- **Receiving money:** Enter buyer, amount, cash or bank, and transfer reference. Partial payments allowed.

- **Agreed adjustments:** If both sides agree a deduction after the entry, record it as an adjustment linked to that purchase or sale.

- **Expenses:** Enter category, amount and a required note. Choose paid now (cash or bank) or unpaid (owed to a party).

- **Partner money and transfers:** Separate forms, so they are never mixed with sales or expenses.

- **Checking:** View any khata, pending balances and the daily summary. At day end, count the cash and enter it.

- **Month-end: **Fix pending rates, match bank and cash, review balances, then lock the month.

# 6. Example: One Day

Starting balances: Cash Rs 80,000, HBL Rs 3,00,000.

| **#** | **What happened** | **Result** |
| --- | --- | --- |
| 1 | Aslam: 800 kg waste @ Rs 50 = 40,000, paid in cash | Aslam: 0. Cash: 40,000 |
| 2 | Karim: 600 kg waste @ Rs 48 = 28,800, paid 10,000 cash | Karim owed 18,800. Cash: 30,000 |
| 3 | Bashir: advance Rs 20,000 from HBL, no wood yet | Bashir: advance 20,000. HBL: 2,80,000 |
| 4 | Karim: 400 kg waste, rate not decided | Shown in Rate Pending list |
| 5 | ABC Feeds: 300 kg protein @ Rs 210 = 63,000, on credit | ABC owes 63,000 |
| 6 | Rehman Traders: 100 kg oil @ Rs 300 = 30,000, paid into HBL | Rehman: 0. HBL: 3,10,000 |
| 7 | ABC Feeds and owner agree Rs 2,000 off for moisture | Adjustment recorded. ABC owes 61,000 |
| 8 | Transport to ABC Feeds Rs 4,000, transporter to be paid later | Transporter owed 4,000 |
| 9 | Wages Rs 8,000 cash. Note: "Daily wages, 8 labourers" | Cash: 22,000 |

End of day: Cash Rs 22,000, HBL Rs 3,10,000. We owe Karim Rs 18,800 plus 400 kg (rate pending) and the transporter Rs 4,000. ABC owes us Rs 61,000. Bashir has an advance of Rs 20,000.

# 7. Party Profiles

## Required

- Party code (created automatically)

- Name

- Type: supplier, buyer, partner, employee or other

- Mobile number

- Status: active or inactive

## Optional

- Name in Urdu

- Business name and contact person

- Area, address and city

- NTN

- Bank details

- Opening balance and date

- Notes

## Buyers and partners only

- Buyers: credit limit and payment terms (days)

- Partners: profit share % and joining date

## Rules

- Every entry is linked to a party selected from the list, never typed by hand.

- The selection list shows name, area and mobile, so similar names are not confused.

- The system warns if a mobile number already exists.

- Parties with history are never deleted, only marked inactive.

# 8. Users and Permissions

| **Action** | **Owner** | **Accountant** |
| --- | --- | --- |
| Add and edit parties | Yes | Yes (owner is alerted) |
| Purchases, sales, payments, expenses, adjustments | Yes | Yes |
| Add rate later, edit or cancel entries | Yes | Yes (logged) |
| Approve large payments | Yes | No |
| Partner investments and withdrawals | Yes | No (to be confirmed) |
| Close month | Yes | No (to be confirmed) |
| Reopen closed month | Yes | No |
| Merge duplicate parties | Yes | No |
| Manage users and settings | Yes | No |
| View reports | Yes | Yes |

# 9. Trade-Specific Controls

- **Weight proof:** slip number and photo on every purchase and sale.

- **Our weight (optional): **record the plant's own weight next to the supplier's or buyer's weight.

- **Duplicate warning:** same party, same kg, same day shows "Possible duplicate".

- **Entry slip:** after each purchase or sale, a simple slip can be shared with the party as PDF.

- **Rate pending age:** entries waiting for a rate too long are highlighted.

- **Advance age:** advances outstanding too long are highlighted.

- **Voucher numbers:** every entry gets a running number, so gaps are visible.

- **Cash given to staff:** recorded on the employee's khata, with expenses and returned cash entered against it.

# 10. Reports

- Party khata with running balance, shareable as PDF

- Pending balances: who owes us, whom we owe, advances, overdue buyers

- Rate Pending list

- Daily summary and daily cash check

- Expense report by category, including transport

- Cash and bank account statements

- Trial balance (proof that all accounts balance)

- Income vs Spending for the month (not profit, because stock is not recorded)

- Partner statement

- Rate report: rate history and average buying and selling rates

- Weight difference report: supplier's or buyer's weight vs our weight, per party

- Adjustments report: total agreed adjustments per party

## Business insights (rough indicators, not exact profit)

- **Kg ratio:** protein and oil kg sold compared to waste kg bought, over 3 months.

- **Wood per kg of waste:** fuel use trend by month.

- **Rate gap:** average buying rate vs average selling rate by month.

- **Rough cost per kg sold:** purchases plus expenses divided by kg sold, over 3 months.

- **Supplier comparison:** kg supplied, average rate, advances, weight differences.

- **Buyer comparison:** average days to pay, overdue amount, adjustments, share of total sales.

# 11. Safety and Control

- Every entry is balanced automatically by the system.

- Entries are never deleted. Mistakes are edited or cancelled, and the change is recorded.

- Every entry shows who created it and when.

- Daily cash check to catch differences on the same day.

- Month-end lock so old records cannot be changed.

- Owner is alerted when new parties are added.

- Backups run every day, and one copy is kept for each month for 7 years. They are encrypted and stored outside the server. Only two named people hold the key that opens them, and a restore is tested every month.

- If the hosting account is ever lost, the system can be rebuilt from the latest backup (target: at most one day of entries lost, running again within 4 hours).

- Strong passwords. The owner creates each user with a temporary password that the user must change at first login.

- Two-step login (a code from a phone app) is available for every user; you decide whether it is required for the owner (Section 14).

- A disabled user is logged out at once, on every device.

- The system runs on the business's own Hostinger "Unlimited" shared hosting account (the database is MariaDB 11.8, managed by the host, which keeps the server and its security updates up to date). The program files and the database password are kept outside the public web folder. Updates are installed by a script and can be undone in one command, and the connection is always encrypted (https).

- If something fails (backup, server, certificate), the owner and the developer are alerted.

- The hosting account, domain, backup storage and code accounts are in the company's name and are handed over to you, with written instructions.

# 12. Not Included in Version 1

## Not needed, based on your answers

- Tax deduction by buyers

- Cheque tracking

- Mobile wallet accounts

- Returns module (rejections settled on site)

- Loans

- Parties who are both supplier and buyer

## Kept for future versions

- **Stock tracking. **Because stock is not recorded, the monthly report shows "Income vs Spending", not true profit. Kg is recorded on every entry so stock can be added later.

- Production tracking (waste in, product out)

- Urdu interface (party names can be saved in Urdu)

- Separate mobile app (the system works in the mobile browser)

- Payroll and tax invoices

- Automatic WhatsApp messages

# 13. Decisions Confirmed

| **Topic** | **Decision** |
| --- | --- |
| Units | All quantities in kg |
| Rates | Entered on every entry. One rate per delivery. Can be added later |
| Advances | Allowed for suppliers and buyers |
| Middlemen | None. The person who brings the material is the supplier |
| CNIC | Not required |
| Who adds parties | Owner and accountant |
| Stock | Not recorded in version 1 |
| Expense categories | One simple list, no office/production split. Note required on every expense |
| Petty cash | Each expense entered separately |
| Language | English |
| Tax deducted by buyers | No. Buyers pay the full amount |
| Deductions and claims | Agreed between both sides and recorded as an adjustment |
| Cheques | None. Cash or bank transfer only |
| Mobile wallets | No separate accounts. Money goes to bank, or is spent and recorded as cash |
| Oil/fat | Sold separately as its own product |
| Weight for payment | Supplier's weight for purchases, buyer's weight for sales |
| Returns | None. Settled on site, final figure recorded |
| Loans | None |
| Transport | Business pays some transport. Can be paid later |
| Supplier and buyer | Always different people |

# 14. Assumptions and Items to Confirm

| **Item** | **Our assumption / Your answer** |
| --- | --- |
| Does the business ever pay suppliers by cheque? | Assumed: no |
| Is oil/fat sold in kg? | Assumed: yes |
| Is all partner money coming in recorded as investment? | Suggested: yes, unless owner marks it otherwise |
| Wallet money spent directly on an expense | Suggested: record as cash received, then cash expense |
| Does the plant have its own scale? | ________ |
| Payments above what amount need owner approval? | Rs ________ |
| Expenses above what amount must have a party? | Rs ________ |
| Who records partner money and closes the month? | Suggested: owner only |
| System start date (for opening balances) | ________ |
| List of bank accounts | ________ |
| Should the owner use two-step login (code on phone)? | Suggested: yes |
| Where should backups be stored (storage company)? | Suggested: Backblaze B2, or another with the same protections |
| Who receives alerts if something fails (phone and e-mail)? | Suggested: owner and developer |
| Who keeps the two copies of the backup key? | Suggested: owner (sealed envelope) and developer, then a second company person |
| How many years must records be kept? (ask your tax adviser) | We keep monthly backups for 7 years (the tax law minimum is 6) |
| If the hosting account is lost: at most one day of entries lost, running again within 4 hours. Acceptable? | Suggested: yes |
| Server company, size and location. Must the data stay in Pakistan? (ask your lawyer) | ________ |

# 15. Next Steps

- You review this document and confirm, or tell us what to change.

- You answer the items in Section 14 and provide opening balances as of the start date.

- We show the first screens (parties, purchases, sales, payments) for your feedback.

- We add expenses, adjustments, transport, partner money and transfers.

- We add reports, business insights and month-end close.

- Trial run: use the system alongside your current register for one month to confirm the numbers match.

- Go live.

# 16. Client Confirmation

I confirm that this document correctly describes the requirements for Version 1 of the system.

Name: ______________________________

Signature: __________________________

Date: ______________________________

# Appendix A: Pages in the System

Who: Both = owner and accountant. Owner = owner only.

## Access

| **#** | **Page** | **Who** | **What it contains** |
| --- | --- | --- | --- |
| 1 | Login | Both | Username and password. Every login recorded |
| 2 | Dashboard | Both | Today's totals, alerts, quick buttons; cash and bank balances for the Owner only (the Accountant sees them on the Accounts page) |

## Daily entries

| **#** | **Page** | **Who** | **What it contains** |
| --- | --- | --- | --- |
| 3 | Purchases list | Both | Filter by date, supplier, item, rate pending, cancelled |
| 4 | New purchase | Both | Supplier, item, kg, rate (optional), slip no., photo, our weight (optional), paid now, note. Last rate hint, unusual rate and duplicate warnings |
| 5 | Purchase detail | Both | Add rate, add adjustment, edit, cancel, share slip, history |
| 6 | Sales list | Both | Same filters as purchases |
| 7 | New sale | Both | Buyer, product, kg, rate (optional), slip no., photo, our weight (optional), received now. Credit limit warning |
| 8 | Sale detail | Both | Add rate, add adjustment, edit, cancel, share slip, history |
| 9 | Payments list | Both | Paid or received, by party, account, date |
| 10 | New payment / receipt | Both | Party, amount, account, transfer reference, handled by, photo, note. Approval if above limit |
| 11 | Payment detail | Both | Edit, cancel, history |
| 12 | Expenses list | Both | Filter by category and date, notes visible |
| 13 | Quick expense | Both | Category, amount, required note, paid now or unpaid, party, link to purchase or sale, photo |
| 14 | Expense detail | Both | Edit, cancel, history |
| 15 | Transfers list | Both | All movements between cash and banks |
| 16 | New transfer | Both | From, to, amount, reference, note |

## People

| **#** | **Page** | **Who** | **What it contains** |
| --- | --- | --- | --- |
| 17 | Parties list | Both | Search, filter by type and status, balances |
| 18 | Add / edit party | Both | Profile fields, duplicate warning |
| 19 | Party profile | Both | Details, khata, rate history, weight differences, history, share statement |
| 20 | Merge parties | Owner | Combine duplicate profiles |
| 21 | Partner money list | Owner | Investments and withdrawals |
| 22 | New partner entry | Owner | Partner, investment or withdrawal, amount, account |
| 23 | Partner statement | Owner | Invested, withdrawn, balance per partner |

## Money and checks

| **#** | **Page** | **Who** | **What it contains** |
| --- | --- | --- | --- |
| 24 | Accounts list | Both | Cash and banks with balances |
| 25 | Account statement | Both | Every movement with running balance |
| 26 | Rate pending list | Both | Entries without rate, with age. Add rate here |
| 27 | Approvals | Owner | Payments waiting for approval |
| 28 | Daily cash check | Both | Enter counted cash, see difference, history |
| 29 | Daily summary | Both | Kg bought and sold, money in and out, expenses, balances |
| 30 | Month-end close | Owner | Pending rates, trial balance, bank match, cash count, lock |
| 31 | Months list | Owner | Open and closed months, reopen with reason |

## Reports

| **#** | **Page** | **Who** | **What it contains** |
| --- | --- | --- | --- |
| 32 | Reports home | Both | Links to all reports |
| 33 | Pending balances | Both | Owed, owing, advances with age, overdue buyers |
| 34 | Expense report | Both | By category with notes, including transport |
| 35 | Income vs spending | Both | Monthly money in vs money out, labelled "not profit" |
| 36 | Trial balance | Both | Debit total equals credit total |
| 37 | Rate report | Both | Rate history and averages |
| 38 | Weight difference | Both | Their weight vs our weight, per party |
| 39 | Adjustments report | Both | Agreed adjustments per party |
| 40 | Business insights | Owner | Kg ratio, wood per kg, rate gap, rough cost per kg, supplier and buyer comparison |

## Audit and settings

| **#** | **Page** | **Who** | **What it contains** |
| --- | --- | --- | --- |
| 41 | Audit log | Owner | Every edit, cancellation, rate change, party change, login, month reopen |
| 42 | Company settings | Owner | Start date, approval limit, expense-party limit, warning limits |
| 43 | Users and permissions | Owner | Add users, set roles |
| 44 | Expense categories | Owner | One simple list, including Transport |
| 45 | Items | Owner | Waste, Wood, Protein product, Oil/Fat |
| 46 | Accounts setup | Owner | Cash and bank accounts |
| 47 | Opening balances | Owner | One-time entry on start date, locked after first month close |
| 48 | Backup status | Owner | When the last backups ran and whether they worked |

# Appendix B: Changes from earlier versions

## Changes in Version 1.1

- Oil/fat added as a separate product.

- Agreed adjustments added for purchases and sales.

- Expenses can now be unpaid and paid later (for example, transporters).

- Transport expense category, with optional link to a purchase or sale.

- Weight slip number, photo, and optional own weight added. Weight difference report added.

- Trade-specific controls added (Section 9).

- Adjustments report and business insights added.

- Confirmed that tax deduction, cheques, wallet accounts, returns, loans and dual-role parties are not needed.

- Full page list added (Appendix A).

## Changes in Version 1.2

- Backups every hour instead of daily, encrypted, stored where the server cannot delete them, restore tested monthly.

- Backup page shows backup status only; restoring is done by the two key holders.

- Temporary passwords, two-step login option for the owner, immediate logout of disabled users.

- Server, monitoring and handover arrangements added. New questions in Section 14.
