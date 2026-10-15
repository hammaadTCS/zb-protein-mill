# Session record – 15 October 2026

What was done in this session on the developer's laptop (OpenCode), how, and what could not be done. One section per step, in order. Environment: clean `main` at `76707e1`, PostgreSQL reachable (`zb_mill_test`, `zb_mill_dev`, `zb_mill_perf`).

Scope agreed at the start: do what can be done of the remaining tasks — **T8.2, T9.2, T9.3** — document each step, and write down, for every task that cannot be done here, the reason.

## Your answers to my questions (before anything was changed)

| # | Question | Your answer |
| --- | --- | --- |
| 1 | My instructions said MariaDB, `docs/conventions.md` §14 and `App\Support\Database\Sql`; the repository and this machine use PostgreSQL | The project keeps working exactly as it is; MariaDB/MySQL is only for the future Hostinger hosting (proposal `docs/proposals/G-32-shared-hosting.md`, its future tasks T10.1+) |
| 2 | Where this record lives | This file: `docs/session-record.md`, updated and committed with each task |
| 3 | T9.3 screenshots destination | `storage/app/screenshots/` (git-ignored, not committed — same precedent as T5.6) |
| 4 | Accountant login for the screenshot | Created locally through Settings → Users (test values only) |
| 5 | Dashboard data for the screenshot | Run `php artisan db:seed --class=DemoSeeder` first |
| 6 | If three fresh timing runs pass | Record all three runs in `docs/release-checks.md`, then tick T8.2 and T9.2 |
| 7 | Branch for this session's three tasks | One branch, one commit per task (`T8.2: …`, `T9.2: …`, `T9.3: …`) |

## Step 1 – Read (no changes)

Read in the order the task instructions ask: `CLAUDE.md`; card sections T2.10 (`docs/cards/M2.md`), T7.6 (`docs/cards/M7.md`), T8.1–T8.3 (`docs/cards/M8.md`); the M9 card text for T9.2 and T9.3 from `docs/proposals/G-31-m9.patch` (there is no `docs/cards/M9.md` yet — it ships with the not-yet-applied G-31 `[spec-change]` proposal); the spec sections and rules the cards name (NFR-02, NFR-11, BR-DSH-01, BR-RPT-09/10/11); `docs/conventions.md` in full; `docs/frontend.md`, `docs/glossary.md`, `docs/brand.md`, `docs/backend.md`; then the existing code the cards touch (`DashboardController`, `RolesAndPermissionsSeeder`, `AccountantDashboardTest`, `RolesAndPermissionsTest`, `TimePages`, `UserController`), plus `docs/release-checks.md`, the relevant `docs/work-log.md` sections and `docs/developer-guide.md`.

## Step 2 – Starting point

- `git status` → clean; `git branch --show-current` → `main`.
- `bash scripts/check-all.sh` → **ALL CHECKS PASSED** on untouched `main`:

```text
==================== Summary ====================
PASS  Secret scan
skip  Protected files check (run it on a task branch)
PASS  PHP tests (Pest, PostgreSQL)
PASS  PHP code style (Pint)
PASS  Static analysis (Larastan level 7)
PASS  Front-end build (also generates Wayfinder routes)
PASS  Front-end lint and format (vite-plus)
PASS  TypeScript
PASS  Front-end unit tests
PASS  Project guard
PASS  Known-vulnerable PHP packages (needs internet)
PASS  Known-vulnerable JS packages (needs internet)

ALL CHECKS PASSED.
```

(588 PHP tests, 6,522 assertions; the full output is in the session log.)

## Step 3 – Plan, questions, answers

The plan was written and stopped on before any change, with seven questions; your answers are the table above. Nothing in the repository was changed before them.

## Step 4 – Fresh page timings (evidence for T8.2 and T9.2)

- Command, run three times: `DB_DATABASE=zb_mill_perf php artisan zb:time-pages`.
- `zb_mill_perf` still holds the 100,000-entry set (122,654 journal entries; `PerformanceSeeder` data, unchanged).
- The command times every list and report page three times, prints the slowest, and exits non-zero if any page reaches 3,000 ms (NFR-02).
- **Result: exit 0 in all three runs — every page under 3 seconds each time.** Slowest pages: statement PDF, 3 months = 2,755 / 2,752 / 2,230 ms; business insights, 12 months = 1,007 / 984 / 845 ms; everything else ≤ 611 ms.
- The full table of all three runs is recorded in `docs/release-checks.md`, section "Re-run on 15 October 2026".
- The 14 October recording showed the 3-month statement PDF at 6,984 ms. No application code changed since then (documentation and CI-config commits only); the cause of the difference was not identified in this session — the machine's power or thermal state is possible but unproven. Both facts are written in `docs/release-checks.md`.

## Step 5 – Demo data for the dashboard screenshots

- Command: `php artisan db:seed --class=DemoSeeder` → ran once (it refuses production and a second run), finished in 550 ms with no error.
- After it, `zb_mill_dev` holds: 7 parties, 2 money accounts, 3 purchases, 8 journal entries (the AT-1 demo, loaded through the real Actions).

## Step 6 – T9.3: screenshots at 375 px for both roles

(to be written when the step runs)

## Step 7 – Final checks and commits

(to be written when the step runs)

## Tasks this session cannot perform, and why

| Task | Why it cannot be done here |
| --- | --- |
| T2.10 Slice review with the client [Human] | Claude's part (the `DemoSeeder`) was built and committed earlier. What remains is showing the real screens to the client and writing every comment with your decision into `docs/feedback/T2.10.md` — only you can hold that conversation. |
| T7.6 Backup status page | Built and its six tests pass. The card's "[Human] after T8.3" part is a real restore on staging (runbook §4) recorded in `docs/release-checks.md` — Claude must never deploy, connect to a server or run `infra/` against one. The `README.md` "Backups and restore" section is your protected-file change (proposed text is in `docs/work-log.md`, T7.6). |
| T8.3 Staging server and first deploy [Human + Claude] | Claude's part (`docs/developer-guide.md`) is committed. Building staging, tagging the first release, deploying, running the health check and the restore test are server and GitHub actions that CLAUDE.md forbids Claude (never deploy, never push, never tag, never run `infra/` against a server). |
| T8.4 Client review on staging [Human] | Showing every page group to the client on staging and recording their comments in `docs/feedback/T8.4.md` is your conversation with the client; staging itself does not exist yet (T8.3). |
| T8.5 Trial month alongside the Urdu register [Human] | Uses real company data, which must never go on staging (spec §18) and must never enter a Claude session (data-handling rule); also needs the production server built first (T8.6). |
| T8.6 Production server and go-live [Human] | Server build, deploy, `zb:create-owner` and the go-live checklist need you on the server; Claude never connects to one. |
| T8.7 Handover to the company [Human] | Needs the company's accounts, two key holders and the Owner's signature — people and secrets outside a session. |
| Pushing / pull requests / merging (rule 8) | You push the branch, open the pull request and merge it; Claude only commits on the task branch. |

Also outside this session, by their own nature: the G-31 `[spec-change]` (new `docs/cards/M9.md`, spec update 7, glossary and traceability rows — still in `docs/proposals/G-31-m9.patch`) and any G-32 Hostinger/MariaDB work (a proposal with future tasks that are not in `docs/tasks.md` yet).

## Open items left for you

1. `README.md` "Backups and restore" section (T7.6): exact proposed text is in `docs/work-log.md`, T7.6 section. `README.md` is protected; only you can add it.
2. G-31 `[spec-change]` pull request: client's written approval (spec §20.3), then apply `docs/proposals/G-31-m9.patch`.
3. G-32 (Hostinger / MariaDB): decide when to start it; it will need its own tasks in `docs/tasks.md` and its own sessions. Nothing in this session changed the database.
4. `docs/work-log.md` questions 15 and the others listed under "Questions for you" there: question 15 (statement PDF over 3 s) is answered by this session's measurements (Step 4); the rest are yours.
5. `docs/README.md` is the map of documents and is protected: if you want this file listed in it, you add the row.
