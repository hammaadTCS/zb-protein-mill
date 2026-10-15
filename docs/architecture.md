# Architecture – ZB Poultry Protein Mill Accounts System

How the whole system fits together: the application, the server, the data, the build and release path, backups and monitoring. The business rules are in `docs/spec.md`; this document explains **where they run and how they are protected**. Decisions and their reasons are in `docs/adr/`.

## 1. Quality goals (in priority order)

| # | Goal | What it means here | How the design meets it |
| --- | --- | --- | --- |
| 1 | **Correct to the paisa** | Debits always equal credits; balances never drift | Double-entry ledger, database triggers, no floats, server recalculates (ADR-0002 replaced by ADR-0016) |
| 2 | **Data is never lost or changed silently** | No deletes, full history, recoverable after any disaster | Append-only tables, audit log, daily and monthly encrypted off-site backups the server cannot delete (ADR-0006 replaced by ADR-0015) |
| 3 | **Secure by default** | Only two kinds of users; nothing exposed that does not need to be | Server-side permissions, one database user with a password, headers set by the app, Hostinger account with two-step login and SSH-key login only (`docs/security.md`) |
| 4 | **Simple to run by one person** | One shared-hosting account, no moving parts that need daily care | One Hostinger account with no operating system to manage, one hPanel cron job, one-command deploy and rollback (ADR-0003 replaced by ADR-0013; ADR-0004, 0005 still hold) |
| 5 | **Fast on a phone** | Entry forms open in under 2 s on 4G (NFR-01) | Inertia + React with per-page code, cached hashed assets, compression, no third-party requests (ADR-0010) |
| 6 | **Changeable without rebuilding** | New rules, a second plant or a mobile app later | Modules, Action classes, settings in the database, `company_id` everywhere (spec §6) |

## 2. System context

```text
        Owner / Accountant (phone, desktop browser)
                         │  HTTPS only (host's free certificate, HSTS from the app)
                         ▼
 ┌──────────── Hostinger "Unlimited" shared hosting account (not a VPS) ─────────────┐
 │  LiteSpeed ──► PHP 8.5 (sodium, proc_open) ──► Laravel 13 app                     │
 │  free certificate      app in ~/accounts, outside public_html   MariaDB 11.8      │
 │                        ~/accounts/current -> releases/<version>/                   │
 │                        one database, one user (all rights on its own database)     │
 │                                                                                   │
 │  hPanel cron (every minute) ─► php artisan schedule:run                           │
 │      zb:verify (daily) · zb:backup (daily + monthly) · health check (15 min)       │
 └───────┬───────────────────────────────┬────────────────────────────┬──────────────┘
         │ encrypted backups (upload-only key)                         │ pings
         ▼                               │                            ▼
  Off-site object storage (O-32)          │                     Monitoring service (O-33)
  versioned / Object Lock                 │                     alerts Owner + developer
                                          │
  GitHub: code, CI, release archives ──► downloaded by the HUMAN ──► SSH + zb-deploy-release
  Claude Code (cloud sessions): writes code on task branches and opens pull requests; you merge.
  Never reaches a server; can push only its own task branch.
```

**Trust boundaries:** the internet ↔ the host's LiteSpeed; the host's PHP ↔ MariaDB (one database user with a password); the host ↔ backup storage (upload only); GitHub ↔ host (no connection at all; a person carries the release).

## 3. What runs on the server

| Process | Account | Talks to | Can write |
| --- | --- | --- | --- |
| LiteSpeed + PHP 8.5 | the hosting account (one Linux user) | MariaDB as the one database user | `~/accounts/shared/storage` (uploads, logs, framework files) |
| Laravel scheduler (hPanel cron, every minute) | the hosting account | same as the app | same as the app |
| Migrations and system data (during deploy only, BR-OPS-11) | the hosting account, over SSH | MariaDB as the same database user | release folders, `bootstrap/cache` |
| Backups (`zb:backup`, driven by the scheduler) | the hosting account | MariaDB (read), storage (upload only) | `~/accounts/shared/storage` backup work folder and status files |
| Health check (scheduler, every 15 minutes) | the hosting account | `/up`, monitor | status files |
| MariaDB | the host's database server | localhost only, remote access off | its data |

There are no separate operating-system users and no `sudo`: one hosting account owns the code, the uploads and the database. The only SSH login is that account, with a key (BR-SEC-10).

**Folders**

```text
~/accounts/                    (outside public_html; the subdomain points at current/public)
  releases/<version>/          code of each release
  current -> releases/<version>  what is live (roll-back repoints this link)
  shared/.env                  settings, APP_KEY and the database password (0640)
  shared/storage/              uploads, logs, framework files
  shared/status/               backup status files read by the app (ZB_BACKUP_STATUS_DIR)
  shared/secrets/              backup public key, storage key, monitor URLs
  public/                      the only folder reachable from the web (its .htaccess drives LiteSpeed)
```

## 4. Inside the application

Laravel 13 with the official React starter kit: Inertia 3 renders React 19 pages from Laravel controllers, so there is **one application and no separate API** (ADR-0001).

```text
Browser (React page, Inertia)
  │  form post (CSRF token, idempotency key)
  ▼
Middleware: HTTPS/host checks · session · CSRF · auth · active-user check · must-change-password
            · security headers + CSP nonce · rate limits (login, 2FA)
  ▼
Controller (thin) → Form Request (validation, plain-English messages)
  → Policy (permission from the database, company scope)
  → Action class: ONE database transaction
        1. open-month and date checks          (BR-GEN-02, period lock)
        2. idempotency key check               (BR-GEN-11)
        3. next voucher number, row lock       (BR-GEN-05)
        4. business record                     (purchases, sales, payments…)
        5. PostingService → journal entry + lines, debit = credit, then the entry's seal row  (only writer of the ledger, BR-LED-11)
        6. audit log row                       (BR-AUD-01)
     COMMIT — a BEFORE INSERT trigger on journal_seals re-sums the lines and refuses the whole save when they are fewer than two or debit ≠ credit (BR-LED-03, BR-LED-11)
  ▼
Redirect → Inertia reloads the page data → toast "Saved. PUR-000123"
```

**Modules** (spec §6): `app/Domain/<Module>/` with models, actions, policies; one query class per report. Money: `App\Support\Decimal` over `brick/math` `BigDecimal`; strings in JSON; never floats (CLAUDE.md rule 1).

**Truth sources:** money from journal lines only; kilograms from business tables only; settings from the `settings` table, never from code.

**Packages beyond the kit** (each added by its task card only): `spatie/laravel-permission` (T1.3), `brick/money` (T1.7), `barryvdh/laravel-dompdf` (T6.10), `pestphp/pest` 4 (T0.1), `@fontsource/ibm-plex-sans` (T0.1). No Redis, no queue worker, no backup package: backups are the application commands `zb:backup` and `zb:restore` (ADR-0004, ADR-0015).

## 5. Front end

| Topic | Choice | Why |
| --- | --- | --- |
| Pages | `resources/js/pages/<module>/index.tsx, create.tsx, show.tsx` | One file per screen; Vite loads each page's code only when needed |
| Routes in JS | Wayfinder (generated typed functions) | Comes with the kit; a renamed route breaks the TypeScript build instead of a link at runtime |
| Forms | Inertia `useForm`, server validation is final | Errors come back from Laravel in plain words (spec §15.4) |
| Numbers | `lib/format.ts` and `lib/money.ts` on strings; `BigInt` for the live total preview | No floating-point mistakes in what the user sees |
| Components | shadcn/ui from the kit + project components (spec §15.6) | One look, accessible primitives |
| Speed | React Compiler (kit), per-page code splitting, hashed files cached 1 year by LiteSpeed, compression, self-hosted IBM Plex Sans | NFR-01, NFR-11. No request leaves for fonts or scripts from other sites |
| Security | No `dangerouslySetInnerHTML`; all scripts carry the CSP nonce; no inline event handlers | The CSP blocks injected scripts (BR-SEC-14) |
| Lint, format, test | `vp check` (Oxlint + Oxfmt), `tsc --noEmit`, `vp test` (Vitest) | The kit's toolchain (`docs/stack-baseline.md`) |

## 6. Data

| Store | Holds | Protection |
| --- | --- | --- |
| MariaDB `zb_mill` | Every business record, the ledger, audit log, users, settings, sessions, cache | One database user (ADR-0014), immutability triggers and the seal-row trigger (spec §9.7, BR-LED-11), daily integrity check `zb:verify` (T7.8) |
| `shared/storage/app/private` | Slip and receipt photos, PDFs | Private disk, random names, permission-checked download route, images re-encoded (BR-SEC-06) |
| `shared/storage/logs` | Application errors (warnings and up) | 14 days, no personal data in messages (BR-SEC-17) |
| Off-site storage | Encrypted backups | Public-key encryption the server cannot undo; server key cannot delete; versioning/Object Lock; lifecycle retention (BR-OPS-01 to 03) |

Database access (ADR-0007 replaced by ADR-0014): the host gives **one** database user that has all rights on its own database only, so the VPS's three-role split is not possible. The password is a strong random value in `.env` outside the web folder (BR-SEC-12). Because that one user can in principle change or drop the protections, the compensating controls are the daily `zb:verify` books check (BR-OPS-08), the off-site backups (BR-OPS-01) and the paper register kept by the other partner. Trigger creation depends on `trust_function_creators` and the binary log staying off; the deployment runbook records this and `zb:verify` refuses to run when a protection trigger is missing.

## 7. Build, release and deploy

```text
Claude Code cloud session ─► task branch ─► check-all.sh ─► push branch ─► pull request
  (hook: never on main, no tags, no merge)         │ CI on the pull request: PHP 8.3 and 8.5,
                                                   │ MariaDB 11.8, guards, protected files
                                                   ▼ you review and merge (only when all green)
             main ─► CI on main (PHP 8.5) + main guard (every commit came from a merged PR?)
                                                   │ you pull and run on the laptop (START-HERE Step 8)
                                                   ▼
you push tag vX.Y.Z ─► Release workflow: refuses unless CI on main is green for this commit
                       (ADR-0012), tests again, builds zb-mill-vX.Y.Z.tar.gz (vendor without
                       dev packages, built front end), SHA256SUMS, GitHub release
                                                   │ you deploy over SSH (infra/hostinger/ship-release.sh)
                                                   ▼
STAGING: zb-deploy-release ─► client checks ─► PRODUCTION: zb-deploy-release
  verify checksum → unpack a new releases/<version>/ folder → link shared files → backup → maintenance mode → migrate
  → add missing system data (BR-OPS-11) → config/route/event caches → switch the `current` link → live → smoke test
  (automatic switch-back if the smoke test fails; `zb-deploy-release --rollback` repoints the link any time)
```

Rules: no credentials for any server exist in GitHub or Claude; migrations must keep working with the previous release, because rollback switches code only (BR-OPS-10, ADR-0005).

## 8. Backup and recovery

| What | When | Where | Kept (P, O-35) |
| --- | --- | --- | --- |
| Database (`mariadb-dump`, gzip, then **sodium**-encrypted) + uploads | Daily, after the day's `zb:verify` | `db/daily/` | 35 days |
| Same file | 1st of each month | `db/monthly/` | 7 years |
| Uploads (tar, encrypted) | With the daily and monthly database backup | `files/` | 35 days (daily), 7 years (monthly) |

Every copy is encrypted with the backup public key **before it leaves the host** and uploaded with a **storage key that cannot delete or overwrite** an earlier copy; naming and retention are done by `zb:backup` itself (ADR-0015). Hostinger's own daily backups are an extra copy (D-4). Recovery targets (P, O-36): lose at most **1 day** of entries (owner's decision, 15 October 2026); be running again within **4 hours** on a new host. Procedure: `docs/operations/runbook.md` → "Host lost". Proven monthly by a restore test on staging (BR-OPS-04) and before go-live by a full rebuild drill.

## 9. Monitoring

| Signal | Source | Alert when |
| --- | --- | --- |
| Site up | External uptime monitor → `https://<domain>/up` (checks the database too) | Down for 5 minutes |
| Backup (daily and monthly) | `zb:backup` pings its dead-man monitor on each run | No success ping for 26 hours, or a fail ping |
| Host health | the scheduler health check every 15 minutes: disk above 80% (where the host exposes it), certificate > 14 days, backup ages, `/up` | Any problem |
| Ledger integrity | `php artisan zb:verify` daily (T7.8) | Any unbalanced journal, gap or mismatch |

Everything above is driven by the one **hPanel cron** job that runs `php artisan schedule:run` every minute (D-7). Alert recipients and service are O-33. Until set, the commands log a warning that nobody is alerted.

## 10. Limits and how it grows

| If… | Then… |
| --- | --- |
| A second plant is added | Already modelled: `company_id` on every row (D-37). Needs company switching in the UI and per-company settings |
| A mobile app is wanted | Add API routes that call the same Action classes (NFR-09). Add token auth (Sanctum) then |
| Many more users or slow pages | Measure first (T8.2). Then: a bigger hosting plan or a VPS, Redis for cache and sessions |
| Heavy background work (bulk PDFs, messages) | Turn on a queue worker and switch `QUEUE_CONNECTION` (not available on the current shared plan; needs a VPS or an external worker) |
| The host must never be down | Move to a VPS + managed MariaDB, or a load-balanced pair. Not needed for two users; cost and complexity are high |

## 11. Where everything lives in the repository

| Path | What | Changed by |
| --- | --- | --- |
| `app/`, `resources/`, `routes/`, `database/`, `config/`, `tests/` | The application (from T0.1) | Claude, task by task |
| `docs/spec.md`, `docs/cards/`, `docs/acceptance/` … | Rules, tasks, expected numbers | You only (`[spec-change]` PR) |
| `docs/architecture.md`, `docs/adr/` | This document and the decision records | You only |
| `docs/security.md`, `docs/secrets.md`, `docs/data-handling.md`, `docs/environments.md` | Security, secrets, data rules, settings per environment | You only |
| `docs/operations/` | Server setup, runbook, handover | You only |
| `infra/` | Hostinger deploy scripts (`infra/hostinger/`), `zb-*` commands, tests; VPS files kept as history under `infra/history/` | You only |
| `.github/` | CI, Infra tests, release build | You only |
| `scripts/`, `.claude/` | Laptop setup, checks, merge script, protection hook, Claude settings | You only |
| `.env.production.example` | Template for the host's `.env` (no values) | You only |

## 12. Decision records

| ADR | Decision |
| --- | --- |
| [0001](adr/0001-one-laravel-inertia-app.md) | One Laravel + Inertia + React application, no separate API |
| [0002](adr/0002-ledger-enforced-by-database.md) | Double-entry ledger enforced by the database, not only by code — *replaced by ADR-0016 (seal row)* |
| [0003](adr/0003-single-vps-ubuntu-packages.md) | One VPS, Ubuntu 26.04, distribution packages, no containers — *replaced by ADR-0013* |
| [0004](adr/0004-no-redis-no-queue-worker.md) | No Redis and no queue worker in version 1 |
| [0005](adr/0005-ci-built-releases-human-deploy.md) | CI builds releases; a person deploys; symlink switch and rollback |
| [0006](adr/0006-encrypted-offsite-backups.md) | Hourly backups encrypted with a public key, off-site, delete-proof — *replaced by ADR-0015* |
| [0007](adr/0007-peer-auth-three-db-roles.md) | Unix-socket peer authentication and three database roles — *replaced by ADR-0014* |
| [0008](adr/0008-security-headers-csp-nonce.md) | Security headers in nginx; CSP with a per-request nonce in the app — *replaced by ADR-0013 (headers set by the app, D-8)* |
| [0009](adr/0009-pest4-php-platform.md) | Pest 4 and a PHP 8.3 platform lock; tests on 8.3 and 8.5 |
| [0010](adr/0010-self-hosted-fonts.md) | Self-hosted fonts from npm; no third-party requests |
| [0011](adr/0011-idempotency-key-column.md) | Double-submit protection with an idempotency key column |
| [0012](adr/0012-github-free-compensating-controls.md) | GitHub Free: pull requests, hook, main guard and release gate instead of branch protection |
| [0013](adr/0013-mariadb-on-shared-hosting.md) | MariaDB 11.8 on the business's Hostinger "Unlimited" shared hosting account (replaces ADR-0003 and the nginx headers of ADR-0008) |
| [0014](adr/0014-one-database-user-with-a-password.md) | One database user with a password on its own database; compensating controls instead of the three-role split (replaces ADR-0007) |
| [0015](adr/0015-backups-from-php-sodium-daily-monthly.md) | Backups written from PHP with `sodium`, daily (35 days) and monthly (7 years), off-site (replaces ADR-0006) |
| [0016](adr/0016-seal-row-instead-of-deferred-constraint-trigger.md) | A seal row per journal entry instead of a deferred constraint trigger (replaces ADR-0002) |
