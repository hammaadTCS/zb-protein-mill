# Start here – ZB Poultry Protein Mill Accounts System (pack v4.1)

This folder is the whole project before any code exists: the client's requirements, the technical specification, 74 small tasks with exact instructions, the rules Claude must follow, the shared-hosting and backup design, and the scripts that keep everything safe.

**How the work runs:**
1. **Claude Code builds** each task in the cloud (claude.ai/code) and opens a pull request on GitHub.
2. **You review and merge** that pull request on GitHub.
3. **You pull and run** the result on your Linux Mint laptop in VS Code.

This page tells you what to do, in order. `docs/README.md` lists every document.

**What you need:**
- a laptop with **Linux Mint 22** (or 23);
- a **Claude Pro or Max** plan (cloud sessions need one of them);
- a free **GitHub** account;
- about an hour for the first setup.

---

## Step 1 – Put the folder in place

1. Unzip `zb-protein-mill-v4.1.zip`. You get a folder `zb-protein-mill`.
2. Move it to `~/Projects/` (create that folder if needed).
3. In the file manager, press **Ctrl+H** to show hidden items. You must see `.claude`, `.github`, `.gitattributes` and `.env.production.example`.

## Step 2 – Install the tools on your laptop (one command)

```bash
cd ~/Projects/zb-protein-mill
bash scripts/setup-local.sh
```

It installs what you need to **pull, run and test** the app: Git, the GitHub CLI, PHP 8.3 with its extensions, Composer, MariaDB 11.8, Node.js 22 and Python 3.

It also creates the **test** login `zb_test` / `local_test_only` and two databases:
- `zb_mill_test`: the automated tests use it, and it is wiped on every test run;
- `zb_mill_dev`: the one you click around in.

It asks for your password once. It is safe to run again. Afterwards, close the terminal and open a new one.

## Step 3 – Put the project on GitHub (private)

```bash
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
gh auth login        # GitHub.com → HTTPS → Yes (authenticate Git) → Login with a web browser
```

On github.com: **New repository** → name `zb-protein-mill` → **Private** → do not add a README → **Create repository**. Then:

```bash
cd ~/Projects/zb-protein-mill
git init -b main
git add .
git commit -m "Project starter pack v4.1"
git remote add origin https://github.com/<your-username>/zb-protein-mill.git
git push -u origin main
```

1. Open the repository's **Actions** tab. Two workflows start by themselves:
   - **CI**: its app steps are skipped until T0.1 creates the app.
   - **Infra**: the server-script tests.

   Wait until both show a green tick (5–10 minutes). If **Infra** did not start, click **Infra → Run workflow**.
2. **Settings → Code security** (on some accounts **Advanced Security**) → **Dependabot alerts → Enable**. This turns on free security e-mails.

Keep the repository **private**: it describes the client's business. GitHub Free cannot lock `main` on a private repository; the project's own checks do that job instead (see "How the project stays safe" below).

## Step 4 – Connect Claude Code on the web (once)

1. Go to **claude.ai/code** and sign in.
2. **Connect GitHub** when asked, and approve.
3. **Install the Claude GitHub App** (github.com/apps/claude). Choose **Only select repositories** → `zb-protein-mill`. Without it, Claude cannot open a private repository.
4. **Environment:** keep the one called **Default**, with network access **Trusted**. It already allows Packagist, GitHub and npm, which is all the project needs. You add **no** environment variables and **no** setup script: the project's own start script prepares every session (MariaDB 11.8, both test databases, packages).
5. Leave **Auto-fix** off on pull requests. You decide every change.

## Step 5 – Send the client document (now; it runs alongside the build)

1. Open `docs/client/Accounts_System_Requirements_v1.2.docx`, put your name on the first page, save a PDF too.
2. Send both with the open questions in `docs/spec.md` section 4 (O-01 to O-37).
3. Ask for:
   - the signature;
   - the answers to the questions;
   - the bank account list;
   - the opening balances on the start date.

Coding can start before the signature. Going live cannot.

## Step 6 – The first task (T0.1), click by click

1. At **claude.ai/code**, click the repository selector, choose `zb-protein-mill`, branch **main**. In the mode dropdown choose **Plan**.
2. Paste **prompt 1** from `docs/prompts.md` (the whole text block) and press Enter.
3. Claude reads the documents and shows a plan. Open `docs/cards/M0.md` → **T0.1** next to it. The plan must follow the card's 9 numbered steps.

   Warning signs:
   - `laravel new`, Laravel Boost, ESLint, a separate Vitest, Pest 5 or SQLite;
   - any change to `docs/`, `infra/`, `.github/`, `scripts/` or `CLAUDE.md`.

   If you see one, reply with the correction, for example *"Card step 4 says pestphp/pest:^4.7. Use that."*
4. **Approve** the plan; if asked, switch the mode to **Accept edits**. Claude works on its own branch, runs `scripts/check-all.sh` until everything passes, and pushes the branch. This takes 15–30 minutes, and you can close the tab.
5. When it is done, click the diff indicator (for example `+420 −12`) → **Create PR**. The pull request description is Claude's report: what it built, the tests, and the check summary.
6. On GitHub, open the pull request.
   - Wait until **every check is green** and none is still running: `checks (PHP 8.3)` and `checks (PHP 8.5)`, plus `Infra` only when server files changed.
   - Go through the checklist in the description.
   - Then click **Merge pull request**.
7. On your laptop, pull and look at it (Step 8).

## Step 7 – Every other task: the same loop

For each unticked task in `docs/tasks.md`, in order:

1. Read the task's card in `docs/cards/` yourself (5 minutes).
2. New session at claude.ai/code (repository `zb-protein-mill`, branch **main**, mode **Plan**). Paste **prompt 2** from `docs/prompts.md` with the task ID, name and card file filled in.
3. Check the plan against the card: same files, same table columns, same tests with the same expected numbers. Approve.
4. **Create PR** → all checks green → go through the checklist → **Merge**.
5. Pull on your laptop and check the screens (Step 8).

Go at one or two tasks a day. If something goes wrong, `docs/prompts.md` section 3 has a ready-made prompt for each case: paste it into the same session.

**Show the client:**
- after T2.10 (required: purchases, khata and trial balance on a phone);
- after M4;
- after M6.

## Step 8 – Pull and run on your laptop (after every merge)

**First time only (after T0.1 is merged):**

```bash
cd ~/Projects/zb-protein-mill
git checkout main && git pull
composer install && npm ci
cp .env.example .env && php artisan key:generate     # .env is never on GitHub; test values only
php artisan migrate --seed
composer run dev
```

**After every later merge:**

```bash
cd ~/Projects/zb-protein-mill
git checkout main && git pull
composer install && npm ci && php artisan migrate
composer run dev
```

Open **http://127.0.0.1:8000**. Log in as `test@example.com` with password `password`. From T1.3 this test user has the Owner role, on your laptop only. Stop the app with Ctrl+C.

- **Run every check yourself** (optional): `bash scripts/check-all.sh`.
- **Demo data** (from T2.10): `php artisan db:seed --class=DemoSeeder`.
- **If the local data gets messy:** `php artisan migrate:fresh --seed`. It rebuilds `zb_mill_dev` only.
- **Phone view:** in Chrome press F12 → phone icon.
- **Real phone on the same Wi-Fi:**
  1. Stop the app and run `npm run build`.
  2. Run `php artisan serve --host=0.0.0.0 --port=8000`.
  3. On the phone, open `http://<laptop address>:8000` (`hostname -I` shows the address).

  Do this only on a network you trust.

Never change code on the laptop's `main` and push it. Changes come from Claude's pull requests, or from your own `[spec-change]` pull requests (Step 9).

## Step 9 – When a rule has to change (`[spec-change]`)

Rules, cards, tests of record, server files and tooling are **protected**: Claude cannot change them, and the checks fail if a pull request changes them without `[spec-change]` in its **title**. You make these changes yourself on the laptop:

```bash
git checkout main && git pull
git checkout -b spec/O-01-approval-limit
# edit in this order: docs/spec.md (and its §4 table) → the cards → conventions, glossary,
# acceptance numbers or infra/ if affected → docs/traceability.md → change log in spec §20.4
git add -A
git commit -m "O-01: approval limit"
git push -u origin spec/O-01-approval-limit
gh pr create --title "[spec-change] O-01 approval limit" --body "Rule change requested by the client."
```

Then wait for green, read your own diff, and merge.

**If you forgot the tag in the title:**
1. Edit the title.
2. Re-run the checks: run `git commit --allow-empty -m "re-run checks"` and `git push`, or close and reopen the pull request. Editing the title alone does not re-run them.

The code for the new rule is then built as a normal task (Step 7).

An answer that is only a value (an approval limit, a start date) is a setting: the Owner enters it on the Settings page after T5.1. Write it into spec §4 the same way.

## Step 10 – Later: hosting, trial month, go-live

None of this is needed until task T8.3. Start opening the company's accounts (hosting, domain, backup storage, monitoring) during M5–M6, because that can take days.

| When | What | Guide |
| --- | --- | --- |
| T8.3 | Set up the **staging** subdomain, make the first release, prove a backup restores | `docs/operations/server-setup.md` |
| Each release | CI on `main` green → `git tag v1.0.0 && git push origin v1.0.0` → GitHub builds the release → `bash infra/hostinger/ship-release.sh v1.0.0 <domain>` | `docs/operations/runbook.md` §2 |
| T8.4 | Client reviews staging (test data only) | card T8.4 |
| T8.5 | Set up **production**, run one real month beside the Urdu register | cards T8.5, T8.6 |
| T8.6 | Go-live checklist | spec §19.2 |
| T8.7 | Handover to the company | `docs/operations/handover.md` |

Claude never touches a server. Only you deploy.

---

## How the project stays safe

| Layer | Stops |
| --- | --- |
| **Task cards**: exact files, columns, tests, "Not in this task" | Guessing and building ahead |
| **CLAUDE.md**, conventions, glossary, verified versions | Inconsistent code; outdated knowledge |
| **Plan first**: nothing changes until you approve | Surprises |
| **Protection hook**: blocks Claude from protected files, from changing or pushing `main`, from tags, merges and releases | Claude changing the rules, or code reaching `main` without you |
| **Acceptance numbers** that tests load from `docs/acceptance/` | "Adjusting" expected results |
| **Checks on every pull request**: PHP 8.3 and 8.5, MariaDB 11.8, guards, secret scan, protected files | Broken code |
| **Main guard** on every push to `main` (GitHub e-mails you if it fails) | Anything that reached `main` without a pull request, or a rule change without `[spec-change]` |
| **Release gate**: releases are built only from a commit whose CI on `main` is green | Broken code reaching a server |
| **You**: reading every plan and diff, merging only when all checks are green, testing screens | What no tool can catch |

**The one rule only you can keep:** never click **Merge** while a check is red or still running. GitHub Free will not stop you; the release gate stops it reaching a server.

**Actions minutes:** GitHub Free gives 2,000 a month for private repositories. When they run out, the checks stop until the next month; there is no charge. Do not merge untested work. The pack already saves minutes in four ways:
- both PHP versions run only on pull requests;
- server tests run only when server files change;
- package updates come monthly, grouped;
- after a merge, `main` is tested once more on PHP 8.5 only.

Create the pull request only when Claude has finished; every push to an open pull request runs the checks again. In busy months, look at github.com/settings/billing.

## When something goes wrong

| Situation | What to do |
| --- | --- |
| `setup-local.sh` says MISSING | Read its message, fix that item, run it again |
| Claude's session says MariaDB is not ready | Ask Claude: "Run bash scripts/cloud-session-start.sh again and report the result" |
| Claude asks a question the documents do not answer | Answer it; if it changes a rule, do a `[spec-change]` (Step 9) first |
| An old test fails | `docs/prompts.md` → "An old test fails". Claude must not edit the old test |
| Claude built more than the card | `docs/prompts.md` → "Claude built more than the card" |
| A check on the pull request is red | Copy the failing step's error into the same session: "This check fails: ... Fix it." |
| Pull request says "This branch is out-of-date" or has conflicts | `docs/prompts.md` → "The pull request has conflicts" |
| GitHub e-mails "main guard failed" or CI failed on `main` | Do not release. Open the run; it names the commit and the problem. Undo a wrong change with **Revert** on the merged pull request |
| A number on screen is wrong | `docs/prompts.md` → "A money number is wrong" |
| You hit your Claude usage limit | Stop; the branch keeps the work. Later: "Continue an unfinished task" |
| `git pull` on the laptop complains about local changes | You changed files on the laptop. `git stash` (keep them aside) or `git checkout -- .` (throw them away), then pull |
| Anything on a server | `docs/operations/runbook.md` |

## What only you do

- Get the client's answers and signature.
- Read every plan and diff; merge only when every check is green; test screens on a real phone.
- Make every `[spec-change]`.
- Open the company's accounts, set up the hosting, keep the keys, deploy, prove that a backup restores.
- Run the trial month beside the Urdu register, and the handover.

**Never** put real client data, real passwords or server details into this repository, a test, a Claude prompt or the cloud environment's settings. Test values only.
