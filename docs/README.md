# Document map

Every document in this project, what it is for, and who uses it. "Protected" means Claude cannot change it; only you change it, through a `[spec-change]` (START-HERE, Step 9).

If two documents disagree, the one higher in this order wins, and Claude must stop and report the conflict: **spec → cards → conventions → glossary → everything else**.

## 1. What the system must do

| Document | What it is | Who reads it |
| --- | --- | --- |
| [client/Accounts_System_Requirements_v1.2.docx](client/) (+ .pdf) | The scope document the client signs | You and the client |
| [requirements.md](requirements.md) | The same document as text, for searching | You, Claude (background) |
| [spec.md](spec.md) | **The master specification.** Every rule (BR-…), table, page, report, posting rule, test and open question (§4) | Claude for every task; you when a task names a section |
| [glossary.md](glossary.md) | Every word shown on screen, and how balances are worded | Claude for screens |
| [acceptance/](acceptance/) | The exact numbers the system must produce (AT-1 to AT-6); tests load these files | Claude; you when a number looks wrong |
| [requirements-audit.md](requirements-audit.md) | Every requirement you stated, where it is covered; every gap found and fixed (G-01 to G-29) | You |
| [traceability.md](traceability.md) | Requirement → rule → task → test that proves it | You before go-live |

## 2. How it is built

| Document | What it is | Who reads it |
| --- | --- | --- |
| [tasks.md](tasks.md) | The 74 tasks in build order, with checkboxes (the only file here Claude may edit: it ticks its task) | You and Claude |
| [cards/M0.md … M8.md](cards/) | Exact instructions per task: files, columns, rules, tests, "Not in this task" | Claude; you before each task |
| [prompts.md](prompts.md) | Prompts to paste into Claude, fix prompts, your review checklist | You |
| [../.github/pull_request_template.md](../.github/pull_request_template.md) | The report Claude writes at the end of each task; it becomes the pull request description, with your review checklist | Claude, you |
| [frontend.md](frontend.md) | How the front end is built: structure, screens, forms, money display, speed, safety | Claude for screens; you |
| [backend.md](backend.md) | How the back end is built: modules, saving data safely, money, permissions, tests, and why data is not lost | Claude; you |
| [conventions.md](conventions.md) | How code is written: names, folders, money maths, save order, security in code, test values | Claude for every task |
| [brand.md](brand.md) | Colours, logo use (login page and PDFs only), typeface | Claude for screens |
| [stack-baseline.md](stack-baseline.md) | Verified versions and facts, with sources and dates | Claude; you when versions change |
| [templates/ArchitectureTest.php](templates/) | Structural rules installed by task T0.3 | Claude |

## 3. Design decisions and safety

| Document | What it is | Who reads it |
| --- | --- | --- |
| [architecture.md](architecture.md) | How the whole system fits together: cloud session → pull request → laptop → servers, users, database roles | You (once), Claude |
| [adr/](adr/) | 12 decision records: what was decided, why, what was rejected | You when someone asks "why" |
| [security.md](security.md) | Threats and the control for each | You |
| [data-handling.md](data-handling.md) | What data is kept, for how long, and the rules for developers | You, Claude |
| [environments.md](environments.md) | Settings per environment: laptop, cloud session, CI, staging, production | You, Claude |
| [secrets.md](secrets.md) | Every secret: where it is made, where it is kept, who holds it, when it changes | You |

## 4. Running the live system (needed from task T8.3)

| Document | What it is |
| --- | --- |
| [operations/server-setup.md](operations/server-setup.md) | Building staging and production, step by step |
| [operations/runbook.md](operations/runbook.md) | Releases, rollback, monthly restore test, alerts, incidents, upgrades |
| [operations/handover.md](operations/handover.md) | What the company receives and signs |
| [../infra/README.md](../infra/README.md) | The server scripts and their automated tests |

## 5. Tooling (protected)

| File | What it does |
| --- | --- |
| `../scripts/setup-local.sh` | One-time laptop setup (Linux Mint 22/23): everything to pull, run and test |
| `../scripts/cloud-session-start.sh` | Prepares each cloud session (PostgreSQL, test databases, packages, `.env`); on the laptop it only reports |
| `../scripts/check-all.sh` | Every check, same order as CI, with a summary |
| `../scripts/check-protected.sh`, `check-main-push.sh` | Protected files change only through a `[spec-change]` pull request; the main guard reports anything that reaches `main` without a merged pull request |
| `../scripts/guard.sh`, `secret-scan.sh` | Project rules in code; no keys or passwords in the repository |
| `../.claude/settings.json`, `../.claude/hooks/protect_files.py` | Plan mode first, allowed and blocked commands, the protection hook |
| `../.github/` | CI (every pull request and every push to `main`), Infra (server-script tests), Release (builds the deploy file), Dependabot (monthly package updates), pull request template |
