# Architecture – ZB Poultry Protein Mill Accounts System

How the whole system fits together: the application, the server, the data, the build and release path, backups and monitoring. The business rules are in `docs/spec.md`; this document explains **where they run and how they are protected**. Decisions and their reasons are in `docs/adr/`.

## 1. Quality goals (in priority order)

| # | Goal | What it means here | How the design meets it |
| --- | --- | --- | --- |
| 1 | **Correct to the paisa** | Debits always equal credits; balances never drift | Double-entry ledger, database triggers, no floats, server recalculates (ADR-0002) |
| 2 | **Data is never lost or changed silently** | No deletes, full history, recoverable after any disaster | Append-only tables, audit log, hourly encrypted off-site backups the server cannot delete (ADR-0006) |
| 3 | **Secure by default** | Only two kinds of users; nothing exposed that does not need to be | Server-side permissions, three database roles, no DB passwords, strict headers, SSH keys only (`docs/security.md`) |
| 4 | **Simple to run by one person** | One server, no moving parts that need daily care | One VPS, distribution packages, systemd timers, one-command deploy and rollback (ADR-0003, 0004, 0005) |
| 5 | **Fast on a phone** | Entry forms open in under 2 s on 4G (NFR-01) | Inertia + React with per-page code, cached hashed assets, compression, no third-party requests (ADR-0010) |
| 6 | **Changeable without rebuilding** | New rules, a second plant or a mobile app later | Modules, Action classes, settings in the database, `company_id` everywhere (spec §6) |

## 2. System context

```text
        Owner / Accountant (phone, desktop browser)
                         │  HTTPS only (TLS 1.2/1.3, HSTS)
                         ▼
 ┌──────────────────────────── VPS: Ubuntu 26.04 LTS ─────────────────────────────┐
 │  nginx ──(Unix socket)──► PHP-FPM 8.5 "zb-mill" pool ──(Unix socket, peer)──►   │
 │  :80 → redirect           Laravel 13 app (user zbmill)        PostgreSQL 18    │
 │  :443 static + PHP                                           (no TCP listener) │
 │                                                                                │
 │  systemd timers: scheduler (1 min) · DB backup (hourly) · files backup (daily) │
 │                  health check (15 min)                                         │
 └───────┬───────────────────────────────┬────────────────────────────┬───────────┘
         │ encrypted backups (upload-only key)                         │ pings
         ▼                               │                            ▼
  Off-site object storage (O-32)          │                     Monitoring service (O-33)
  versioned / Object Lock                 │                     alerts Owner + developer
                                          │
  Let's Encrypt (certificate renewal) ◄───┘

  GitHub: code, CI, release archives ──► downloaded by the HUMAN ──► scp + zb-deploy-release
  Claude Code (cloud sessions): writes code on task branches and opens pull requests; you merge.
  Never reaches a server; can push only its own task branch.
```

**Trust boundaries:** the internet ↔ nginx; nginx ↔ PHP (different OS users); PHP ↔ PostgreSQL (different database roles); the server ↔ backup storage (upload only); GitHub ↔ server (no connection at all; a person carries the release).

## 3. What runs on the server

| Process | OS user | Talks to | Can write |
| --- | --- | --- | --- |
| nginx | `www-data` | PHP-FPM socket `/run/php/zb-mill.sock` | its logs only |
| PHP-FPM pool `zb-mill` | `zbmill` | PostgreSQL as role `zb_app` | `shared/storage` (uploads, logs, framework files) |
| Laravel scheduler (timer, every minute) | `zbmill` | same as the app | same as the app |
| Migrations and system data (during deploy only, BR-OPS-11) | `zbdeploy` | PostgreSQL as `zb_migrator` | release folders, `bootstrap/cache` |
| Backups (timers) | `zbbackup` | PostgreSQL as `zb_backup` (read only); storage | `/var/lib/zb-mill/backup`, status files |
| Health check (timer) | `root` | local services, monitor | nothing |
| PostgreSQL | `postgres` | Unix socket only | its data |

Nobody logs in as these users. The only SSH login is your admin account (key only, `sudo`).

**Folders**

```text
/srv/zb-mill/
  releases/<UTC timestamp>/   code of each release (read-only for the web user)
  current -> releases/<id>    what is live
  shared/.env                 settings and APP_KEY (zbdeploy:zbmill 0640)
  shared/storage/             uploads, logs, framework files (zbmill, 2770)
  deploy.log                  who deployed what, when
/etc/zb-mill/                 zb-mill.conf, backup public key, secrets/ (storage key, monitor URLs)
/var/lib/zb-mill/             backup work folder, status files read by the app
/usr/local/sbin/zb-*          server commands (deploy, backup, restore, checks)
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
        5. PostingService → journal entry + lines, debit = credit  (only writer of the ledger)
        6. audit log row                       (BR-AUD-01)
     COMMIT — the database checks every journal balances (deferred trigger) before it accepts
  ▼
Redirect → Inertia reloads the page data → toast "Saved. PUR-000123"
```

**Modules** (spec §6): `app/Domain/<Module>/` with models, actions, policies; one query class per report. Money: `App\Support\Decimal` over `brick/math` `BigDecimal`; strings in JSON; never floats (CLAUDE.md rule 1).

**Truth sources:** money from journal lines only; kilograms from business tables only; settings from the `settings` table, never from code.

**Packages beyond the kit** (each added by its task card only): `spatie/laravel-permission` (T1.3), `brick/money` (T1.7), `barryvdh/laravel-dompdf` (T6.10), `pestphp/pest` 4 (T0.1), `@fontsource/ibm-plex-sans` (T0.1). No Redis, no queue worker, no backup package (ADR-0004, ADR-0006).

## 5. Front end

| Topic | Choice | Why |
| --- | --- | --- |
| Pages | `resources/js/pages/<module>/index.tsx, create.tsx, show.tsx` | One file per screen; Vite loads each page's code only when needed |
| Routes in JS | Wayfinder (generated typed functions) | Comes with the kit; a renamed route breaks the TypeScript build instead of a link at runtime |
| Forms | Inertia `useForm`, server validation is final | Errors come back from Laravel in plain words (spec §15.4) |
| Numbers | `lib/format.ts` and `lib/money.ts` on strings; `BigInt` for the live total preview | No floating-point mistakes in what the user sees |
| Components | shadcn/ui from the kit + project components (spec §15.6) | One look, accessible primitives |
| Speed | React Compiler (kit), per-page code splitting, hashed files cached 1 year by nginx, gzip, self-hosted IBM Plex Sans | NFR-01, NFR-11. No request leaves for fonts or scripts from other sites |
| Security | No `dangerouslySetInnerHTML`; all scripts carry the CSP nonce; no inline event handlers | The CSP blocks injected scripts (BR-SEC-14) |
| Lint, format, test | `vp check` (Oxlint + Oxfmt), `tsc --noEmit`, `vp test` (Vitest) | The kit's toolchain (`docs/stack-baseline.md`) |

## 6. Data

| Store | Holds | Protection |
| --- | --- | --- |
| PostgreSQL `zb_mill` | Every business record, the ledger, audit log, users, settings, sessions, cache | Roles + grants (`infra/files/postgres`), immutability triggers (spec §9.7), daily integrity check `zb:verify` (T7.8) |
| `shared/storage/app/private` | Slip and receipt photos, PDFs | Private disk, random names, permission-checked download route, images re-encoded (BR-SEC-06) |
| `shared/storage/logs` | Application errors (warnings and up) | 14 days, no personal data in messages (BR-SEC-17) |
| Off-site storage | Encrypted backups | Public-key encryption; server key cannot delete; versioning/Object Lock; lifecycle retention (BR-OPS-01 to 03) |

Database roles (ADR-0007): `zb_migrator` owns the schema (migrations only); `zb_app` may read, insert and update, delete only on housekeeping tables, never touch journal or audit rows; `zb_backup` reads everything, writes nothing. All three log in through the Unix socket with **peer authentication**: no password exists to leak. Proven by `infra/tests/test-postgres.sh` and, against the real migrations, by CI (`infra/tests/ci-db-privileges.sh`, from task T1.11).

## 7. Build, release and deploy

```text
Claude Code cloud session ─► task branch ─► check-all.sh ─► push branch ─► pull request
  (hook: never on main, no tags, no merge)         │ CI on the pull request: PHP 8.3 and 8.5,
                                                   │ PostgreSQL 18, guards, protected files
                                                   ▼ you review and merge (only when all green)
             main ─► CI on main (PHP 8.5) + main guard (every commit came from a merged PR?)
                                                   │ you pull and run on the laptop (START-HERE Step 8)
                                                   ▼
you push tag vX.Y.Z ─► Release workflow: refuses unless CI on main is green for this commit
                       (ADR-0012), tests again, builds zb-mill-vX.Y.Z.tar.gz (vendor without
                       dev packages, built front end), SHA256SUMS, GitHub release
                                                   │ you download (infra/local/ship-release.sh)
                                                   ▼
STAGING: zb-deploy-release ─► client checks ─► PRODUCTION: zb-deploy-release
  verify checksum → unpack → link shared files → backup → maintenance mode → migrate (zb_migrator)
  → add missing system data (zb_migrator, BR-OPS-11) → grants → config/route/event caches → switch symlink → reload PHP-FPM → live → smoke test
  (automatic switch-back if the smoke test fails; `zb-deploy-release --rollback` any time)
```

Rules: no credentials for any server exist in GitHub or Claude; migrations must keep working with the previous release, because rollback switches code only (BR-OPS-10, ADR-0005).

## 8. Backup and recovery

| What | When | Where | Kept (P, O-35) |
| --- | --- | --- | --- |
| Database (pg_dump, encrypted) | Every hour | `db/hourly/` | 2 days |
| Same file | First run after 21:00 UTC | `db/daily/` | 35 days |
| Same file | First run each month | `db/monthly/` | 7 years |
| Uploads (tar, monthly full + daily incremental, encrypted) | 21:30 UTC daily | `files/<YYYY-MM>/` | 100 days (the newest chain always holds every file) |

Recovery targets (P, O-36): lose at most **1 hour** of entries; be running again within **4 hours** on a new server. Procedure: `docs/operations/runbook.md` → "Server lost". Proven monthly by a restore test on staging (BR-OPS-04) and before go-live by a full rebuild drill.

## 9. Monitoring

| Signal | Source | Alert when |
| --- | --- | --- |
| Site up | External uptime monitor → `https://<domain>/up` (checks the database too) | Down for 5 minutes |
| Database backup | `zb-backup-db` pings its monitor every hour | No success ping for 2 hours, or a fail ping |
| Uploads backup | `zb-backup-files` pings daily | No success ping for 26 hours |
| Server health | `zb-health-check` every 15 minutes: disk < 80%, services, clock, certificate > 14 days, backup ages, `/up` | Any problem |
| Ledger integrity | `php artisan zb:verify` daily (T7.8) | Any unbalanced journal, gap or mismatch |

Alert recipients and service are O-33. Until set, `zb-*` commands log a warning that nobody is alerted.

## 10. Limits and how it grows

| If… | Then… |
| --- | --- |
| A second plant is added | Already modelled: `company_id` on every row (D-37). Needs company switching in the UI and per-company settings |
| A mobile app is wanted | Add API routes that call the same Action classes (NFR-09). Add token auth (Sanctum) then |
| Many more users or slow pages | Measure first (T8.2). Then: more PHP workers, bigger server, Redis for cache and sessions |
| Heavy background work (bulk PDFs, messages) | Turn on a queue worker (systemd unit) and switch `QUEUE_CONNECTION` |
| The server must never be down | Second server + managed PostgreSQL. Not needed for two users; cost and complexity are high |

## 11. Where everything lives in the repository

| Path | What | Changed by |
| --- | --- | --- |
| `app/`, `resources/`, `routes/`, `database/`, `config/`, `tests/` | The application (from T0.1) | Claude, task by task |
| `docs/spec.md`, `docs/cards/`, `docs/acceptance/` … | Rules, tasks, expected numbers | You only (`[spec-change]` PR) |
| `docs/architecture.md`, `docs/adr/` | This document and the decision records | You only |
| `docs/security.md`, `docs/secrets.md`, `docs/data-handling.md`, `docs/environments.md` | Security, secrets, data rules, settings per environment | You only |
| `docs/operations/` | Server setup, runbook, handover | You only |
| `infra/` | Server configuration, `zb-*` commands, provisioning, tests | You only |
| `.github/` | CI, Infra tests, release build | You only |
| `scripts/`, `.claude/` | Laptop setup, checks, merge script, protection hook, Claude settings | You only |
| `.env.production.example` | Template for the server's `.env` (no values) | You only |

## 12. Decision records

| ADR | Decision |
| --- | --- |
| [0001](adr/0001-one-laravel-inertia-app.md) | One Laravel + Inertia + React application, no separate API |
| [0002](adr/0002-ledger-enforced-by-database.md) | Double-entry ledger enforced by the database, not only by code |
| [0003](adr/0003-single-vps-ubuntu-packages.md) | One VPS, Ubuntu 26.04, distribution packages, no containers |
| [0004](adr/0004-no-redis-no-queue-worker.md) | No Redis and no queue worker in version 1 |
| [0005](adr/0005-ci-built-releases-human-deploy.md) | CI builds releases; a person deploys; symlink switch and rollback |
| [0006](adr/0006-encrypted-offsite-backups.md) | Hourly backups encrypted with a public key, off-site, delete-proof |
| [0007](adr/0007-peer-auth-three-db-roles.md) | Unix-socket peer authentication and three database roles |
| [0008](adr/0008-security-headers-csp-nonce.md) | Security headers in nginx; CSP with a per-request nonce in the app |
| [0009](adr/0009-pest4-php-platform.md) | Pest 4 and a PHP 8.3 platform lock; tests on 8.3 and 8.5 |
| [0010](adr/0010-self-hosted-fonts.md) | Self-hosted fonts from npm; no third-party requests |
| [0011](adr/0011-idempotency-key-column.md) | Double-submit protection with an idempotency key column |
| [0012](adr/0012-github-free-compensating-controls.md) | GitHub Free: pull requests, hook, main guard and release gate instead of branch protection |
