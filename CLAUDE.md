# ZB Poultry Protein Mill – Accounts System

You are building, in Claude Code cloud sessions (the human pulls and runs the result on a Linux Mint laptop), the accounts system for ZB Poultry Protein Mill, a business in Pakistan that buys chicken waste and wood, renders the waste into protein product and oil/fat, and sells both. The system records every rupee in and out with double-entry accounting. It will run on a real server and be handed to the company, so security and correctness come before speed of delivery.

Your job is to build **exactly** what the documents below describe. Not more, not less, not "better". When something is not written down, you ask; you do not decide.

## Documents, in order of authority

| # | File | What it decides |
| --- | --- | --- |
| 1 | `docs/spec.md` | What the system must do: every rule, table, page, report and test |
| 2 | `docs/cards/M*.md` | Exactly what each task builds, its tests and what is out of scope |
| 3 | `docs/conventions.md` | How code is written: names, folders, money maths, warnings, security in code, tests |
| 4 | `docs/glossary.md` | Every word shown to users |
| — | `docs/acceptance/*.json` | The numbers the system must produce. Tests load these files |
| — | `docs/stack-baseline.md` | Verified versions and starter-kit facts. If reality differs, stop and report |
| — | `docs/architecture.md`, `docs/adr/` | How the system fits together and why |
| — | `docs/security.md`, `docs/data-handling.md`, `docs/environments.md` | Security controls, data rules, settings per environment |
| — | `docs/frontend.md`, `docs/backend.md` | Summaries of how screens and server code are built, and why data is safe (the documents above win on conflict) |
| — | `docs/brand.md` | Colours, logo, typeface |
| — | `docs/traceability.md` | Which test proves which requirement |
| — | `docs/tasks.md` | Build order and checkboxes (you tick your task) |
| — | `docs/prompts.md`, `.github/pull_request_template.md` | How tasks are given to you; the report you end every task with (it becomes the pull request description) |
| — | `docs/README.md` | Map of every document |
| — | `docs/requirements.md`, `docs/requirements-audit.md` | Client-facing scope and the audit. Background only |
| — | `docs/secrets.md`, `docs/operations/`, `infra/` | Server and secrets, for the human. You never run these against a server |

If `docs/spec.md` or `docs/cards/` is missing, STOP and say so. Do not build from memory.
If two documents disagree, STOP and list the conflict. Never pick one silently.

## Status labels in the spec

- **C** confirmed by the client, **A** agreed, **P** proposed technical choice: build all three as written.
- **OPEN**: not decided. Never invent a value. It is a setting with no production default. Tests use the test values in `docs/conventions.md` §11 only.

## Deviation protocol

Stop and ask (do not code around it) when:
- a rule, field, page or test you need is not written anywhere;
- the card, spec and conventions disagree;
- a command in a card fails or the starter kit differs from `docs/stack-baseline.md`;
- a test you did not write fails;
- you think a requirement is wrong or could be better.

Write the question with: document and section, the options, and what each option changes. Then stop.

## Protected files (you must never change them)

`CLAUDE.md`, `START-HERE.md`, `README.md`, `.env.production.example`, `docs/README.md`, `docs/spec.md`, `docs/requirements.md`, `docs/requirements-audit.md`, `docs/conventions.md`, `docs/glossary.md`, `docs/brand.md`, `docs/prompts.md`, `docs/traceability.md`, `docs/architecture.md`, `docs/frontend.md`, `docs/backend.md`, `docs/security.md`, `docs/secrets.md`, `docs/data-handling.md`, `docs/environments.md`, `docs/stack-baseline.md`, everything in `docs/cards/`, `docs/acceptance/`, `docs/templates/`, `docs/adr/`, `docs/operations/`, `docs/client/`, `brand/`, `infra/`, `.claude/`, `.github/`, everything in `scripts/`, and any **existing** file in `tests/Acceptance/` or `tests/Arch/`.

A hook blocks your edits to these files, and `scripts/check-all.sh` and CI fail when they change without the human's `[spec-change]` approval (a pull request with `[spec-change]` in its title). If one of them must change, write the exact proposed change in your final message; the human makes it.

## Stack (facts in `docs/stack-baseline.md`)

Laravel 13 from the official React starter kit: Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, **Wayfinder** for routes in React (no Ziggy, no `route()` helper), **vite-plus** for build, lint, format and front-end tests (no ESLint, no separate Vitest), Larastan level 7 and Pint (already in the kit), **Pest 4**. **MariaDB 11.8** everywhere — cloud sessions, the laptop (Linux Mint 22), CI and the host (the MySQL family; the old PostgreSQL VPS is gone). PHP 8.3 in cloud sessions and on the laptop, 8.5 on the host; `composer.json` keeps `platform.php` at 8.3.6 so one lock file works everywhere.

Packages are added only by the task whose card names them: `pestphp/pest` + `pestphp/pest-plugin-laravel` and `@fontsource/ibm-plex-sans` (T0.1), `spatie/laravel-permission` (T1.3), `brick/money` (T1.7), `barryvdh/laravel-dompdf` (T6.10). No other package without asking. Never install Laravel Boost (it writes its own `CLAUDE.md`). There is no backup package: backups are the application commands `zb:backup` and `zb:restore` (T10.6).

## Non-negotiable rules

1. **No float anywhere for money, kg or rate** (conventions §3). Database `DECIMAL` (`numeric` is its synonym), PHP `BigDecimal` through `App\Support\Decimal`, strings in JSON, no `parseFloat` in React.
2. **Only `PostingService` writes journal rows.** Debits equal credits. Posting rules PR-01 to PR-28 are the only postings. At the end of the saving transaction it writes the entry's seal row (BR-LED-11); the database refuses the entry when the seal does not match.
3. **Nothing is deleted.** Edit = reversal + new journal; cancel = reversal + reason. Never disable or bypass database triggers or the seal-row trigger.
4. **One database transaction per save**, in the order of conventions §2, with the idempotency key on creates (§2a).
5. **The server is the truth** for amounts and balances. Browser totals are ignored.
6. **Permissions are checked on the server** for every route and action.
7. **Every query is scoped by `company_id`.**
8. **Closed months are locked.**
9. **Rule IDs in code and test names** (for example `// BR-PUR-03`, `it('BR-PUR-03 ...')`).
10. **Party balances in words**, never a minus sign (glossary §2).
11. **Interface words only from `docs/glossary.md`.**
12. **Logo only on the login page and in PDFs.**
13. **Security in code** (conventions §12): `env()` only in `config/`; no personal data, amounts or secrets in logs; no scripts, styles, fonts or calls to other sites; inline Blade scripts carry the CSP nonce; migrations must work with the previous release.
14. **Portable SQL only — the database is MariaDB** (conventions §14). Never write PostgreSQL-only SQL: no `::cast`, `ilike`, `FILTER (WHERE …)`, `pg_*` functions, `to_char`, `ON CONFLICT` or `SET CONSTRAINTS`. Use `CAST(… AS DECIMAL(…))`, `LIKE` (the column's collation decides case), `CASE WHEN`, `DATE_FORMAT(…, '%Y-%m')` and `DATEDIFF()` — never subtract dates (`20261101 − 20261031` is 70, not 1). Compare a key only with a value of its own type; state the NULL order in every sort on a column that can be empty. `scripts/guard.sh` fails on the PostgreSQL-only forms.

## Tests and checks

- **MariaDB only, never SQLite and never PostgreSQL.** One version of record everywhere: **MariaDB 11.8**, with the connection settings in `docs/conventions.md` §14 (`READ COMMITTED`, strict `sql_mode`, `+00:00`, InnoDB, `utf8mb4`). The application refuses to start on an older MariaDB or wrong settings (T10.1). In cloud sessions `scripts/cloud-session-start.sh` (SessionStart hook) installs and starts MariaDB, creates both databases and its user, installs packages when the app exists, and creates `.env` from `.env.example` when it is missing; on the laptop `scripts/setup-local.sh` does the same once. Tests use `zb_mill_test` (set in `phpunit.xml`); the app's `.env` uses `zb_mill_dev`; the local database user and password are in `docs/environments.md` and `.env.example` (test values only). If MariaDB is not ready, run `bash scripts/cloud-session-start.sh` again; if it still fails, stop and report.
- Write the test first for anything that posts money; assert exact journal lines and exact strings.
- Acceptance tests load `docs/acceptance/*.json`; never copy numbers into test code.
- Wayfinder's TypeScript route files are generated, not committed: run `npm run build` (or `php artisan wayfinder:generate --with-form`) before `npm run types:check`.
- Before finishing ANY task run `bash scripts/check-all.sh` (it runs all of the following in CI order) until it prints ALL CHECKS PASSED, and paste its summary in your report:
  - `php artisan test`
  - `vendor/bin/pint --test`
  - `vendor/bin/phpstan analyse`
  - `npm run check` (vite-plus lint + format) and `npm run types:check`
  - `npm test` (from T2.1)
  - `bash scripts/guard.sh` (from T0.3) and `bash scripts/secret-scan.sh`
- **Never change or delete an existing test to make it pass.** If an old test fails, your new code is wrong or the spec changed; stop and report.
- From T4.8 onward, AT-1 and AT-2 pass in every task. From T7.7, AT-3 to AT-6 too.

## How to work on a task

0. You start on `main` in Plan mode. The first step after the plan is approved is to work on a task branch: the branch the cloud session gives you, or `task/T2.7-...` if you create one. Never change anything on `main`.
1. Read the task's card in `docs/cards/` completely, then every spec section and rule it names, then `docs/conventions.md` (and `docs/frontend.md` or `docs/backend.md` for that side).
2. Run `bash scripts/check-all.sh`. If anything fails before you start, stop and report.
3. In Plan mode, list: files to create or change, migrations with columns, tests with their exact expected values, and every question. Wait for approval.
4. Build only what the card's "Build exactly" lists. Nothing from "Not in this task".
5. Run everything (see Tests and checks). Fix until green.
6. Tick the task in `docs/tasks.md`, commit everything on the task branch, and push the task branch.
7. Final message = `.github/pull_request_template.md` filled in (it becomes the pull request description), including the `check-all.sh` summary.

## Git

- Branch: the one the cloud session gives you, or `task/T2.7-purchases-fixed-rate`. Commits start with the task ID: `T2.7: ...`. Pull request title: task ID and name, exactly as in `docs/tasks.md`.
- **You work only on your task branch, and you may push only that branch.** Never commit, merge, rebase or reset on `main`; never move or delete `main`; never push `main`; never push, create or delete tags; never delete remote branches; never merge a pull request; never create a release or run a workflow; never write through the GitHub API (`gh api` is for reading only); never run `check-all.sh --spec-change`. The human reviews the pull request and merges it on GitHub. The hook and `.claude/settings.json` block these commands; CI checks every pull request, and the main guard checks every push to `main`.
- If `main` moved while you worked (the pull request shows conflicts or is out of date), the human will ask you to `git merge origin/main` into your branch, fix conflicts without touching protected files or existing tests, run the checks again and push.

## Never

- Never put real client data, passwords, keys, `APP_KEY` values, monitor URLs or server details in the repository, tests, fixtures, screenshots or environment variables. Test values only.
- Never deploy, never connect to a server, never run anything from `infra/` except the tests in `infra/tests/` on this machine if a card says so.
- Never weaken, skip or work around `scripts/guard.sh`, `scripts/secret-scan.sh`, the protection hook, CI checks, database privileges or architecture tests. A `guard:allow` comment needs a reason and is reviewed by the human.
