# Data handling

What data the system holds, where it is, who can see it, how long it is kept, and the rules for logs, backups, exports and test data.

## 1. Data inventory

| Data | Examples | Personal? | Where | Who can see it |
| --- | --- | --- | --- | --- |
| Financial records | Purchases, sales, payments, expenses, ledger, balances | Business | MariaDB | Owner; Accountant except partner money and insights (spec §7) |
| Party details | Name (English, optional Urdu), mobile, area, bank account details, notes | **Yes** | MariaDB | Owner, Accountant |
| Partner details | Names, profit share % | **Yes** | MariaDB | Owner |
| User accounts | Name, e-mail, role, password hash, two-factor secret (encrypted) | **Yes** | MariaDB | Owner (no password or secret is ever shown) |
| Audit log | Who did what, old and new values, IP address, device | **Yes** | MariaDB (append-only) | Owner; each record's own history to anyone who can see the record |
| Uploads | Weight-slip photos, receipts, PDFs | Possibly (faces, names on slips) | `~/accounts/shared/storage/app/private` | Anyone who can see the parent record |
| Sessions | Login sessions (encrypted payload) | Yes | MariaDB `sessions` | Nobody; never backed up |
| Application logs | Errors and warnings | Must not contain any (rule 3.2) | `~/accounts/shared/storage/logs`, 14 days | Hosting account |
| Web server logs | Address, time, path (no query string), status, browser | IP address | Hostinger access logs (14 days) | Hosting account |
| Backups | All of the above except sessions and cache | Yes | Off-site storage, encrypted | Only holders of the backup private key |

**Not collected, by decision:** CNIC (F-22), dates of birth, addresses beyond "area", location from photos (removed on upload), analytics or tracking of any kind, third-party scripts or fonts.

## 2. Retention (how long)

| Data | Kept | Basis |
| --- | --- | --- |
| Financial records, parties, audit log, uploads | For the life of the system; **never deleted** by the app | D-24; Income Tax Ordinance s.174(3) six-year minimum (`stack-baseline.md`); exact period O-35 |
| Database backups | **Daily 35 days, monthly 7 years (P)** | BR-OPS-03; O-35 |
| Uploads backups | 35 days (daily), 7 years (monthly) | BR-OPS-03 |
| Application logs | 14 days | BR-SEC-17 |
| Web server logs | 14 days (host default rotation) | BR-SEC-17 |
| Sessions | 60 minutes idle, then removed by the framework | BR-SEC-04 |

Every backup is encrypted with the backup public key before it leaves the host (**sodium** sealed box, so the host cannot read its own backups), stored **off-site** with an upload-only key, and kept by the storage's lifecycle rules. The private key is never on any host: two copies exist offline, one held by each partner (O-37, BR-OPS-02). At most **one day** of entries can be lost on a restore (owner's decision, 15 October 2026; BR-OPS-05).

When a party asks to be "removed": the records stay (tax law and the books need them); the party is made **inactive** so it cannot be used again (BR-GEN-08). Changing that policy needs the client's lawyer, not code.

## 3. Rules for developers (Claude and humans)

1. **Real data never leaves production**, except inside encrypted backups. Not into a Claude session or prompt, the cloud environment's settings, a test, a fixture, a pull request, a screenshot, an issue, or a chat.
2. **Logs never contain** passwords, tokens, session IDs, `APP_KEY`, full request bodies, party names, mobiles, bank details or amounts. Log the record type and ID instead (`purchase 1234 failed: …`).
3. **Staging holds test data only.** The monthly restore test restores real backups into a temporary database on staging, checks it and **drops it** in the same run (`zb:restore` does this by default).
4. **Cloud sessions and the laptop use seeded test data only.** Acceptance data in `docs/acceptance/` is invented. Every seeder that creates test or demo data (`DatabaseSeeder`, `TestSettingsSeeder`, `DemoSeeder`, `PerformanceSeeder`) refuses to run when `APP_ENV=production`; servers run only `SystemDataSeeder`, which creates no users and no test data (BR-OPS-11).
5. **No deletes.** Corrections are reversal entries (ADR-0002 replaced by ADR-0016). Nobody edits production data with SQL.
6. **Exports leave our control.** PDFs shared by WhatsApp (D-35) contain a party's balance; users share them on purpose. PDFs show the English name only (G-10).
7. **Uploads are re-encoded** on the server; original files with GPS data are never stored (BR-SEC-06).
8. **Personal data in errors:** validation messages may repeat what the user typed back to that user; they are never logged.
9. **Notes in the repository hold no real data.** `docs/feedback/`, `docs/release-checks.md`, pull requests and commit messages are on GitHub, which many tools and people can read. They never contain real party names, mobiles, bank details or amounts. During the trial month (T8.5), record each difference by voucher number, date and kind (for example "rate entered twice", "cash count entered late", "system bug: fix task T-x"). The names and figures stay in the company's own comparison sheet, outside the repository. Restore tests record counts and "balanced: yes", never totals.

## 4. Who can access what, outside the app

| Person | Production data access | How |
| --- | --- | --- |
| Owner, Accountant | Through the app only | Login |
| Hosting account holder (developer, later company IT) | Can reach the database through hPanel/phpMyAdmin and by SSH | Hostinger login (two-step) + SSH key; with the one all-rights database user they can change data, which the daily `zb:verify`, the off-site backups and the paper register detect |
| Backup key holders | Can read backups | Private key, only during a restore |
| Claude Code | **None** | No server access, no real data in the repository |
| Hosting and storage providers | Encrypted backups only (cannot read them); the shared hosting provider can technically access the account's disk and database | Provider contract (O-34) |

## 5. Data location

The host's region is chosen with the client (O-34). Whether any Pakistani rule requires financial or personal data to stay in Pakistan has **not** been checked; the client's lawyer should confirm before go-live. Backups go to the storage provider's region (O-32); they are encrypted before they leave the host.
