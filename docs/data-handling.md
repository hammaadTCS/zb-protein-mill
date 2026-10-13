# Data handling

What data the system holds, where it is, who can see it, how long it is kept, and the rules for logs, backups, exports and test data.

## 1. Data inventory

| Data | Examples | Personal? | Where | Who can see it |
| --- | --- | --- | --- | --- |
| Financial records | Purchases, sales, payments, expenses, ledger, balances | Business | PostgreSQL | Owner; Accountant except partner money and insights (spec §7) |
| Party details | Name (English, optional Urdu), mobile, area, bank account details, notes | **Yes** | PostgreSQL | Owner, Accountant |
| Partner details | Names, profit share % | **Yes** | PostgreSQL | Owner |
| User accounts | Name, e-mail, role, password hash, two-factor secret (encrypted) | **Yes** | PostgreSQL | Owner (no password or secret is ever shown) |
| Audit log | Who did what, old and new values, IP address, device | **Yes** | PostgreSQL (append-only) | Owner; each record's own history to anyone who can see the record |
| Uploads | Weight-slip photos, receipts, PDFs | Possibly (faces, names on slips) | `shared/storage/app/private` | Anyone who can see the parent record |
| Sessions | Login sessions (encrypted payload) | Yes | PostgreSQL `sessions` | Nobody; never backed up |
| Application logs | Errors and warnings | Must not contain any (rule 3.2) | `shared/storage/logs`, 14 days | Server admin |
| Web server logs | Address, time, path (no query string), status, browser | IP address | `/var/log/nginx`, Ubuntu rotation (14 days) | Server admin |
| Backups | All of the above except sessions and cache | Yes | Off-site storage, encrypted | Only holders of the backup private key |

**Not collected, by decision:** CNIC (F-22), dates of birth, addresses beyond "area", location from photos (removed on upload), analytics or tracking of any kind, third-party scripts or fonts.

## 2. Retention (how long)

| Data | Kept | Basis |
| --- | --- | --- |
| Financial records, parties, audit log, uploads | For the life of the system; **never deleted** by the app | D-24; Income Tax Ordinance s.174(3) six-year minimum (`stack-baseline.md`); exact period O-35 |
| Database backups | Hourly 2 days, daily 35 days, monthly 7 years (P) | BR-OPS-03; O-35 |
| Uploads backups | 100 days (the newest monthly chain always holds every file) | BR-OPS-03 |
| Application logs | 14 days | BR-SEC-17 |
| nginx logs | 14 days (Ubuntu default rotation) | BR-SEC-17 |
| Sessions | 60 minutes idle, then removed by the framework | BR-SEC-04 |

When a party asks to be "removed": the records stay (tax law and the books need them); the party is made **inactive** so it cannot be used again (BR-GEN-08). Changing that policy needs the client's lawyer, not code.

## 3. Rules for developers (Claude and humans)

1. **Real data never leaves production**, except inside encrypted backups. Not into a Claude session or prompt, the cloud environment's settings, a test, a fixture, a pull request, a screenshot, an issue, or a chat.
2. **Logs never contain** passwords, tokens, session IDs, `APP_KEY`, full request bodies, party names, mobiles, bank details or amounts. Log the record type and ID instead (`purchase 1234 failed: …`).
3. **Staging holds test data only.** The monthly restore test restores real backups into a temporary database on staging, checks it and **drops it** in the same run (`zb-restore` does this by default).
4. **Cloud sessions and the laptop use seeded test data only.** Acceptance data in `docs/acceptance/` is invented. Every seeder that creates test or demo data (`DatabaseSeeder`, `TestSettingsSeeder`, `DemoSeeder`, `PerformanceSeeder`) refuses to run when `APP_ENV=production`; servers run only `SystemDataSeeder`, which creates no users and no test data (BR-OPS-11).
5. **No deletes.** Corrections are reversal entries (ADR-0002). Nobody edits production data with SQL.
6. **Exports leave our control.** PDFs shared by WhatsApp (D-35) contain a party's balance; users share them on purpose. PDFs show the English name only (G-10).
7. **Uploads are re-encoded** on the server; original files with GPS data are never stored (BR-SEC-06).
8. **Personal data in errors:** validation messages may repeat what the user typed back to that user; they are never logged.
9. **Notes in the repository hold no real data.** `docs/feedback/`, `docs/release-checks.md`, pull requests and commit messages are on GitHub, which many tools and people can read. They never contain real party names, mobiles, bank details or amounts. During the trial month (T8.5), record each difference by voucher number, date and kind (for example "rate entered twice", "cash count entered late", "system bug: fix task T-x"). The names and figures stay in the company's own comparison sheet, outside the repository. Restore tests record counts and "balanced: yes", never totals.

## 4. Who can access what, outside the app

| Person | Production data access | How |
| --- | --- | --- |
| Owner, Accountant | Through the app only | Login |
| Server administrator (developer, later company IT) | Can reach the database on the server | SSH key + sudo; every login is in the system journal |
| Backup key holders | Can read backups | Private key, only during a restore |
| Claude Code | **None** | No server access, no real data in the repository |
| Hosting and storage providers | Encrypted backups only (cannot read them); the VPS provider can technically access the server disk | Provider contract (O-34) |

## 5. Data location

The server's region is chosen with the client (O-34). Whether any Pakistani rule requires financial or personal data to stay in Pakistan has **not** been checked; the client's lawyer should confirm before the server is bought. Backups go to the storage provider's region (O-32); they are encrypted before they leave the server.
