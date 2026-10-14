# Release checks

Evidence that the release rules hold. Rows marked **verify on staging** need a real server and are filled in by the developer in T8.3 (and again before go-live, T8.6). Never write amounts, names, server addresses or secrets in this file: it is in git.

## Security

Checked on 13 October 2026 on branch `claude/lucid-ptolemy-w3fhsy` (T8.1). "Where enforced" follows `docs/security.md` §4. Test names are Pest test names (`php artisan test --filter="…"`).

| Rule | Where enforced | How verified | Result |
| --- | --- | --- | --- |
| BR-SEC-01 HTTPS only, HTTP redirects | `infra/files/nginx` site (HTTP → HTTPS redirect, HSTS) | `infra/tests/test-web.sh`; on the server: `curl -I http://…` gives 301 to https | Repository: pass (infra tests in CI). **Verify on staging** |
| BR-SEC-02 password rule, bcrypt | `AppServiceProvider` `Password::defaults` (kit production rule); hashing driver bcrypt (framework default) | `BR-SEC-02 in production a password without a symbol is refused`; `BR-SEC-02 passwords are stored with bcrypt` | Pass |
| BR-SEC-03 login limit 5/min per user and IP | `FortifyServiceProvider` rate limiters (`Limit::perMinute(5)`) | `BR-SEC-03 login is throttled after 5 failures` | Pass |
| BR-SEC-04 session ends after 60 minutes idle | `SESSION_LIFETIME=60` in `.env.production.example`; `config/session.php` default is now 60 too (was the kit's 120 when the variable is missing) | `BR-SEC-04 a session ends after 60 minutes idle, also without the variable` | Pass (default fixed in T8.1) |
| BR-SEC-05 permission on every route and action | `->can()` on every route, policies, `Gate::authorize` in controllers | `T-09 every application route is classified`; `T-09 BR-SEC-05 the accountant gets 403 where the matrix says No; the owner never gets 403`; `T-09 the accountant may use every route whose permission the matrix gives them` | Pass |
| BR-SEC-06 uploads | `StoreAttachment` (type by content, 5 MB, re-encode images, random names, private disk), `config/filesystems.php` (`serve` off), nginx body limit | `BR-SEC-06 a 6 MB file is refused`; `… an .exe is refused`; `… an .exe renamed to .jpg is refused`; `… a JPEG with an Exif block is stored without it`; `… a PDF is stored as received`; `BR-SEC-06 the local disk is not served publicly`; `test-web.sh` (413 above 8 MB) | Pass. Nginx limit: **verify on staging** |
| BR-SEC-07 CSRF on every form | Laravel `PreventRequestForgery` in the `web` group | `BR-SEC-07 every web route goes through the CSRF check` | Pass |
| BR-SEC-08 automatic encrypted off-site backups, monthly restore | `infra/bin/zb-backup-db`, `zb-backup-files`, systemd timers; page 48 shows status (T7.6) | `infra/tests/test-backup-restore.sh`; `BR-OPS-07 …` backup page tests; monthly restore (runbook §4) | Repository: pass. **Verify on staging** (first restore in T8.3) |
| BR-SEC-09 two-step login, Owner forced when O-31 = yes | Fortify 2FA (kit); `EnsureOwnerTwoFactor` middleware | `BR-SEC-09 an owner without two-factor is sent to security settings when owner_two_factor_required is true` and the three other BR-SEC-09 tests | Pass |
| BR-SEC-10 server hardening | `infra/provision` (firewall, SSH keys only, automatic updates, UTC, separate users) | `infra/tests/test-provision.sh`, `test-deploy.sh`; checklist on the server | **Verify on staging** |
| BR-SEC-11 secrets only where `docs/secrets.md` says | `.gitignore`, `scripts/secret-scan.sh`, CI secret scan | `bash scripts/secret-scan.sh` (passes); `infra/tests/test-guardrails.sh` | Pass |
| BR-SEC-12 database roles | `infra/files/postgres` (three roles, peer auth, no listener, grants after each migration) | `BR-SEC-12 the migrator connection differs from pgsql only in username and password`; `infra/tests/test-postgres.sh`; CI privilege check (`ci-db-privileges.sh`) | Repository: pass. **Verify on staging** (no TCP listener, peer auth) |
| BR-SEC-13 sessions end on disable, role change, password reset or change | `Sessions::endFor`, `EnsureUserIsActive` | `BR-SEC-13 a disabled user is logged out on the next request`; `BR-SEC-13 changing my password ends my other sessions but keeps this one`; `T-17 BR-SEC-13 changing a role deletes their sessions`; `T-17 BR-SEC-13 disabling a user deletes their sessions`; `T-17 BR-USR-08 resetting a password deletes their sessions and sets must_change_password` | Pass |
| BR-SEC-14 security headers and CSP with nonce; robots.txt | nginx security headers snippet; `ContentSecurityPolicy` middleware (`CSP_ENABLED=true` in production); `public/robots.txt` | `BR-SEC-14 an HTML page has a CSP header with a nonce, and the inline script carries the same nonce`; `BR-SEC-14 every request gets a new nonce`; `BR-SEC-14 production sends the Content Security Policy`; `robots.txt disallows everything`; `test-web.sh` (headers) | Pass. Headers on the real site: **verify on staging** (`curl -I https://…`) |
| BR-SEC-15 cookie Secure, HttpOnly, SameSite=Lax; encrypted sessions | `.env.production.example`, `config/session.php` | `BR-SEC-15 production cookies are Secure, HttpOnly, SameSite=Lax and sessions are encrypted` | Pass |
| BR-SEC-16 no debug output in production; strict models elsewhere | `APP_DEBUG=false` in `.env.production.example`; `Model::shouldBeStrict(! production)` in `AppServiceProvider`; plain error pages | `BR-SEC-16 the 500 page shows no exception text when debug is off`; `BR-SEC-16 production runs without debug output and models are strict elsewhere` | Pass |
| BR-SEC-17 nothing personal or secret in logs; daily rotation, 14 days | `config/logging.php` (`daily`, `LOG_DAILY_DAYS=14`, `LOG_STACK=daily` in production); no `Log::` calls with names or amounts; nginx access log without query strings | `BR-SEC-17 a failed login writes nothing to the log`; `BR-SEC-17 production logs rotate daily and are kept 14 days`; `zb:verify` prints IDs only (T7.8); nginx log format: **verify on staging** | Pass (app). Nginx: **verify on staging** |
| BR-SEC-18 lock files, audits, Dependabot, only card-named packages | `composer.lock`, `package-lock.json` committed; CI `composer audit --locked`, `npm audit --omit=dev --audit-level=high`; `.github/dependabot.yml`; `scripts/check-all.sh` runs both audits | CI and `check-all.sh` output (both audits pass) | Pass. The only packages added were those named by cards (Pest, IBM Plex font, spatie/laravel-permission, brick/money, barryvdh/laravel-dompdf); recharts was **not** added (T7.5) |
| Extra: Inertia devtools routes (`_inertia/devtools/*`) | Inertia registers them only when `APP_ENV=local` unless `INERTIA_DEVTOOLS_ENABLED` is set; production does not set it | `the Inertia devtools are never on in production`; on staging `php artisan route:list` shows no `_inertia` route | Pass. **Verify on staging** (route list) |

## Operations

| Rule | Where enforced | How verified | Result |
| --- | --- | --- | --- |
| BR-OPS-01 hourly database, daily uploads backups, encrypted, upload-only key | `infra/bin/zb-backup-db`, `zb-backup-files`, systemd timers | `infra/tests/test-backup-restore.sh`; `zb-backup-verify-offsite` on the real storage | **Verify on staging** |
| BR-OPS-02 private key never on a server or in the repository, two holders | `docs/secrets.md`; secret scan | `bash scripts/secret-scan.sh`; holders recorded at go-live (O-37) | Repository: pass. Holders: **go-live (T8.6)** |
| BR-OPS-03 retention by lifecycle rules, Object Lock or versioning | Storage bucket settings (`docs/operations/server-setup.md`) | `zb-backup-verify-offsite` three PASS lines; screenshot kept | **Verify on staging** |
| BR-OPS-04 monthly restore on staging with `zb:verify` | runbook §4; `zb-restore --verify` uses `zb:verify --db-name` (T7.8) | `T-20 --db-name checks the named database and writes no status file`; restore record below | App: pass. Restore: **verify on staging** (T8.3) |
| BR-OPS-05 RPO 1 hour, RTO 4 hours, rebuild drill | Hourly backups; runbook §9 | Rebuild drill before go-live | **Go-live (T8.6)** |
| BR-OPS-06 `/up` checks the database; external monitor every 5 minutes | `/up` (`bootstrap/app.php`); `AppServiceProvider` runs `select 1` on `DiagnosingHealth` | `BR-OPS-06 /up returns 500 when the database query fails`; monitor set up on the server | App: pass. Monitor: **verify on staging** |
| BR-OPS-07 backup dead-man pings; status page with last success and last error | `infra/bin/zb-backup-*` (pings, status files); page 48 and the `BackupLate` alert (T7.6) | `BR-OPS-07 fresh success shows OK`; `BR-OPS-07 a success 3 hours old shows Late`; `BR-OPS-07 a failure shows the error text`; `missing folder shows not configured`; `spec §7 there is no download route` | App: pass. Pings: **verify on staging** |
| BR-OPS-08 daily `zb:verify`, alert on failure | `App\Domain\Ledger\IntegrityCheck`, `zb:verify` scheduled 20:30 UTC; `zb-health-check` reads `storage/app/zb-verify.json` | T-20 tests (after AT-1 all OK; each planted fault fails exactly its check; schedule entry); `php artisan schedule:list` shows `30 20 * * * php artisan zb:verify`; all checks OK on the development data | App: pass. Alert: **verify on staging** |
| BR-OPS-09 health check every 15 minutes | `infra/bin/zb-health-check`, systemd timer | `infra/tests/test-health-check.sh` | **Verify on staging** |
| BR-OPS-10 releases from CI tags, SHA-256, deploy script, migrations safe with the previous release | `.github/workflows/release.yml`, `infra/bin/zb-deploy-release`; every migration's `up()` only adds tables, columns, indexes or triggers (column drops exist only in `down()`) | `infra/tests/test-deploy.sh`; migrations reviewed in each task (no column drops or renames) | Repository: pass. First deploy: **verify on staging** (T8.3) |

## Restore tests

| Date | Who | Backup used (file name only) | `zb:verify` result | Row counts checked |
| --- | --- | --- | --- | --- |
| | | | | |

## Performance

Measured on 13 October 2026 (T8.2) in the cloud development container: 4 vCPU Intel Xeon 2.10 GHz, 15 GB RAM, PHP 8.3.6, PostgreSQL 16.15, everything on one machine. Data: `PerformanceSeeder`, 100,000 invented entries over 12 months saved through the real Actions in 47 minutes — 60,000 purchases, 20,000 sales, 15,000 payments (plus 23,836 paid-now and received-now payments), 5,000 expenses; 122,654 journals, 245,308 journal lines, 136,337 audit rows. `php artisan zb:verify` on this database: all five checks OK in 0.9 seconds.

Timing: `php artisan zb:time-pages` (the slowest of three runs, server time including every query and the page data, logged in as the Owner). "Default" means the page as it opens; "12 months" means the whole year chosen as the date range.

### Before and after the fixes

| Page | Before | After (100,000 entries) |
| --- | --- | --- |
| Business insights, 12 months | 10,535 ms at only ~9,000 entries | 2,324 ms |
| Rate report, 12 months | 3,932 ms at ~9,000 entries | 14,756 ms (see below) |
| Dashboard | 1,045 ms at ~18,000 entries | 1,543 ms |
| Statement PDF, 12 months | 1,769 ms at ~18,000 entries | 20,811 ms (see below) |

Fixes (no index was needed; every slow page was slow because of code): settings read once per GET request; rate corrections loaded in one query; business insights summed in PostgreSQL; ageing run once per buyer and only for parties that can be overdue or hold an advance.

### Every page after the fixes

| Page | ms | Under 3 s |
| --- | --- | --- |
| Dashboard | 1,543 | yes |
| Purchases / Sales / Payments / Expenses / Transfers lists | 22–110 | yes |
| Parties list | 95 | yes |
| Supplier profile and khata (whole history, ~1,000 entries) | 933 | yes |
| Buyer profile and khata | 720 | yes |
| Partners, Partner statement | 21–36 | yes |
| Accounts | 111 | yes |
| Account statement (default: this month) | 241 | yes |
| Account statement, 12 months (17,926 rows) | 4,812 | **no** |
| Rate pending | 560 | yes |
| Approvals, Cash check | 23–26 | yes |
| Daily summary | 128 | yes |
| Pending balances | 1,302 | yes |
| Expense report, 12 months | 1,251 | yes |
| Trial balance (with and without parties) | 98–115 | yes |
| Income vs Spending, 12 months | 111 | yes |
| Rate report (default: this month) | 508 | yes |
| Rate report, 12 months (57,000 entries listed) | 14,756 | **no** |
| Weight difference (default: this month) | 237 | yes |
| Weight difference, 12 months | 5,321 | **no** |
| Adjustments report, 12 months | 75 | yes |
| Business insights (default: 3 months) | 2,160 | yes |
| Business insights, 12 months | 2,324 | yes |
| Audit log | 147 | yes |
| Month-end close, Months | 25–98 | yes |
| Statement PDF, this month | 324 | yes |
| Statement PDF, 12 months (one busy supplier, over 1,000 rows) | 20,811 | **no** |

**Result:** every page as it opens, and every summary report over 12 months, is under 3 seconds. Four views that **list every single entry of a whole year** are not: the account statement (3.2 MB of page data), the rate report (5.6 MB), the weight difference report and a 12-month statement PDF. They would also be too heavy for a phone even if the server were instant. Fixing them changes what the pages show, so it is a decision for the developer and client (see `docs/work-log.md`, T8.2): page the rows (for example 100 per page, with totals over the whole range), summarise long ranges per month, or limit the range (for example 3 months per statement PDF).

### Front-end size (NFR-11)

From `npm run build` (gzip):

| Page | JavaScript | CSS |
| --- | --- | --- |
| Login | 197.7 kB (16 files) | 15.6 kB + 0.4 kB fonts |
| Dashboard | 196.2 kB (15 files) | 15.6 kB + 0.4 kB fonts |

The largest single file is the Wayfinder route chunk (109.5 kB gzip): the signed-in menu imports the route helpers of every page, and the bundler puts them in one shared file that the login page also loads. Each page's own code is split out (login 1.1 kB, dashboard 2.1 kB). Files have content hashes for one-year caching. Possible improvement (not done, it changes the starter kit's layout loading): load the signed-in layout only after login.

### M9 re-measure (T9.2, T9.4, T9.5)

Measured on 14 October 2026 on the developer's laptop: Intel Core i5-6440HQ (4 cores, 2016), 7 GB RAM, PHP 8.3.6, PostgreSQL 16, everything on one machine. This machine is several times slower than the cloud container above (the same `PerformanceSeeder` took 234 minutes here). Data: `PerformanceSeeder`, 100,000 entries over 12 months, database `zb_mill_perf`. Timing: `php artisan zb:time-pages`, slowest of three runs. "Before" is commit `89da53a` (the end of T8.2), run with `INERTIA_DEVTOOLS_ENABLED=false` so both columns measure the same thing (see the note under the table); "after" is the M9 code (`eb804ae`). Nothing else ran on the machine during either run.

| Page | Before (ms) | After (ms) | Under 3 s |
| --- | --- | --- | --- |
| Dashboard | 6,465 | 1,419 | yes |
| Purchases / Sales / Payments / Expenses / Transfers lists | 41–102 | 49–99 | yes |
| Purchases, 12 months | 156 | 155 | yes |
| Parties list | 193 | 201 | yes |
| Supplier profile and khata | 1,499 | 1,557 | yes |
| Buyer profile and khata | 899 | 898 | yes |
| Partners, Partner statement | 40–96 | 48–102 | yes |
| Accounts | 351 | 292 | yes |
| Account statement (this month) | 451 | 241 | yes |
| Account statement, 12 months | 6,842 | 383 | yes |
| Rate pending | 1,417 | 1,423 | yes |
| Approvals, Cash check | 32–46 | 33–46 | yes |
| Daily summary | 446 | 351 | yes |
| Pending balances | 6,006 | 1,126 | yes |
| Expense report, 12 months | 1,235 | 1,288 | yes |
| Trial balance (with and without parties) | 341–344 | 299–300 | yes |
| Income vs Spending, 12 months | 349 | 311 | yes |
| Rate report (this month) | 814 | 804 | yes |
| Rate report, 12 months | 19,170 | 1,117 | yes |
| Weight difference (this month) | 344 | 299 | yes |
| Weight difference, 12 months | 6,778 | 377 | yes |
| Adjustments report, 12 months | 53 | 39 | yes |
| Business insights (3 months) | 8,279 | 2,229 | yes |
| Business insights, 12 months | 11,072 | 2,662 | yes |
| Audit log | 274 | 222 | yes |
| Month-end close, Months | 70–282 | 72–276 | yes |
| Statement PDF, this month | 1,461 | 1,481 | yes |
| Statement PDF, 12 months (before) / 3 months, the longest BR-RPT-11 allows (after) | 62,851 | 6,984 | **no** |

What changed it:
- Dashboard, pending balances and business insights: the oldest-first figures of spec §12.6 (overdue, average days to pay) now come from one PostgreSQL query for all buyers (`Ageing::overdueMany`, `averageDaysToPayMany`), instead of a PHP match per buyer over its whole history. A one-off comparison on this database (30 buyers, 4 dates and periods, 240 values) found no difference from the old calculation; `AgeingOverdueManyTest` keeps proving it.
- Account statement, rate report, weight difference: BR-RPT-09 and BR-RPT-10 (100 rows per page; monthly lines over 3 months).
- Statement PDF: BR-RPT-11 (at most 3 months).

**Still over 3 seconds: the 3-month statement PDF of the busiest supplier** (349 rows, 15 pages): 0.5 s for the figures and 5.8 s inside the PDF library (dompdf), about 17 ms per row on this machine. Measured alternatives: separate borders 5.0 s, fixed table layout 6.2 s, no row page-break rule 5.8 s, no font subsetting 5.9 s, the table split into chunks of 40 rows 4.3 s (but every chunk repeats the header row, a visible change). A month of the same supplier takes 1.5 s. This needs a decision: see `docs/work-log.md`, M9.

**Note on DevTools.** The same "before" code with Inertia DevTools on (its default for `APP_ENV=local` before T9.1) was much slower on the laptop: for example, the expense report 4,651 ms instead of 1,235 ms, and the supplier khata 4,322 ms instead of 1,499 ms, because DevTools wrote every page's data to disk. Servers never had it on (`APP_ENV=production`), so the table above leaves it out of both columns. Since T9.1 it is off unless a developer turns it on.

**Laptop speed with `composer run dev`** (server time of a full page load, local database with demo data):

| Mode | /login | /dashboard |
| --- | --- | --- |
| Vite dev server running, SSR on (before T9.2) | 0.5–16.7 s | 0.36–2.3 s |
| Vite dev server running, SSR off (T9.2) | 0.12–0.19 s | 0.30–0.62 s |
| Built files, no Vite | 0.11–0.17 s | 0.32–0.55 s |

Eight requests at once (hover prefetches): 1.1–1.5 s with one `artisan serve` worker, 0.5–0.8 s with `PHP_CLI_SERVER_WORKERS=4` (T9.2). OPcache was already on for `artisan serve`.

**Front-end size, re-measured** (gzip level 9 of the built files the page loads): login 190.8 kB JavaScript (16 files), dashboard 189.4 kB (15 files), CSS 14.8 kB. Correction to the note above: the largest shared file (105.5 kB) is named after Wayfinder but holds React, ReactDOM, the Inertia client and the Wayfinder runtime, which every page needs; the route helpers and the signed-in layout are in `app-*.js` (57.4 kB). Loading the signed-in layout lazily would take part of that off the first login only (the files are cached for a year), and would add a request before every signed-in page's first view, so it was not done.
