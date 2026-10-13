# Work log

A plain record of every task session: what was done, in what order, what was found, and what is left.
Newest task at the bottom. One section per task. This file is not a rule document; the rules are in `CLAUDE.md` and `docs/`.

## Where the project stands

| Item | Status |
| --- | --- |
| Current task | T0.1 Laravel app, React starter kit, Pest, PostgreSQL, CI |
| State | **Waiting for you:** apply the G-30 `[spec-change]` (steps in "Your steps" below), merge it, then tell me "merged" |
| Branch | `claude/lucid-ptolemy-w3fhsy`, pull request [hammaadTCS/zb-protein-mill#1](https://github.com/hammaadTCS/zb-protein-mill/pull/1). Holds this log and the proposed patch; no app code yet. **Do not merge it yet** |
| Tasks ticked in `docs/tasks.md` | none |
| Next task | T0.1 continues in this session after G-30 is merged. T0.2 only after T0.1 is merged into `main` |

---

## T0.1 Laravel app, React starter kit, Pest, PostgreSQL, CI

Card: `docs/cards/M0.md` → T0.1. Session date: 13 October 2026.

### Before starting

- `docs/tasks.md`: no task ticked. T0.1 is the first task.
- Branch given by the cloud session: `claude/lucid-ptolemy-w3fhsy` (made from `main` at commit `554240c First commit`).
- `bash scripts/check-all.sh` on the untouched branch: ALL CHECKS PASSED (secret scan, protected files; app checks skipped because there is no app yet).
- Plan approval: the project process (CLAUDE.md, "How to work on a task", step 3) asks for a plan in Plan mode and your approval before building. This session was started in auto mode and you asked me to go ahead, so I did not wait. T0.1's card is a fixed list of commands, so the plan is the card's 9 steps with no choices of my own. From now on, start sessions in **Plan** mode as `START-HERE.md` says.

### Step 1: tools (done, all fine)

| Tool | Result |
| --- | --- |
| `php -v` | PHP 8.3.6 (matches the baseline, so `platform.php` will be `8.3.6`) |
| `composer -V` | Composer 2.8.12 |
| `node -v` | v22.22.0 |
| `npm -v` | 10.9.4 |
| `psql -h 127.0.0.1 -U zb_test -d zb_mill_test -c 'select 1'` | works (returns 1) |
| npm registry | works (`npm view @fontsource/ibm-plex-sans version` → 5.3.0) |

Note: in cloud sessions Composer runs as `root`, and Composer then turns off its plugins (Pest needs its plugin). Your laptop and CI run as a normal user, where plugins are on. I set `COMPOSER_ALLOW_SUPERUSER=1` for my Composer commands so this session behaves like the laptop and CI. This changes no file.

### Step 2: create the app (FAILED, task stopped here)

Command (from the card, run in a temporary folder outside the repository):

```bash
LARAVEL_INSTALLER_DEFER_HOOKS=1 composer create-project laravel/react-starter-kit "$TMP/app" --stability=dev --prefer-dist
```

What happened:

- The starter kit itself came down fine: `laravel/react-starter-kit (dev-main 717b8f55aefd82d25d4119eaebdc8e3a72b8d7e5)`. This is the **same commit** as `docs/stack-baseline.md`.
- Then Composer has to download 139 PHP packages. Every one is a zip file from `https://api.github.com/repos/<owner>/<package>/zipball/<commit>`.
- **All 139 zip downloads were refused** (HTTP 403) by the cloud session's GitHub gateway, with this message:
  `GitHub access to this repository is not enabled for this session.`
- Composer then tries `git clone` for each package instead. That works for 138 of them, but the command still fails with `Could not authenticate against github.com` (exit code 100).

Why (checked, not guessed):

- Claude's cloud documentation (code.claude.com/docs/en/cloud-environments, "GitHub proxy") says: all GitHub traffic goes through a GitHub proxy "independent of the environment's access level", and "an API request for another repository gets a 403 whose message starts with `GitHub access to`". Only repositories attached to the session can use the GitHub API. Composer's zip downloads use the GitHub API.
- So changing the environment's network access from **Trusted** to **Full** would **not** fix it.
- Attaching a package's repository does not fix it either: I tried for `phpstan/phpstan`; the session answered that public repositories are only available for `git clone`, and the API needs write access, which we cannot have on someone else's repository.
- One package, `phpstan/phpstan` (Larastan's engine, needed for the required `vendor/bin/phpstan analyse` check), is published **only** as a zip. It has no git option in `composer.lock`. So a git-only install (`--prefer-source`) also fails, on that package alone.

This is a difference from `docs/stack-baseline.md`, which says the Trusted network allows `api.github.com` and `codeload.github.com`, so Composer downloads would work. The domains are allowed, but the GitHub proxy blocks zip downloads for every repository that is not yours.

**Effect on the whole project, not only T0.1:** `vendor/` is never committed, so every future cloud session must run `composer install` (the start script `scripts/cloud-session-start.sh` does it). Every one of those installs will fail the same way. As things stand, **no task can pass `check-all.sh` in a cloud session.**

### Test I ran to check a possible fix (temporary folder only; nothing kept)

- I downloaded `phpstan/phpstan` with `git` at the exact locked commit (`6155239b`), made a zip of it with `git archive`, and put it in Composer's download cache.
- Then `composer install --prefer-source` installed **all 139 packages** (exit code 0).
- Side effect: git installs include files that the normal zips leave out. Composer printed a warning that a class name appears twice (`App\Providers\AppServiceProvider` in our app and in Pint's own source). Harmless here, but it means cloud installs would not be byte-for-byte the same as CI and your laptop.
- I deleted the planted zip afterwards, so no hidden workaround is active.

### Starter-kit differences from `docs/stack-baseline.md` (for when T0.1 resumes)

| Item | Baseline | What the kit has now |
| --- | --- | --- |
| Commit | `717b8f55` | `717b8f55` (same) |
| `laravel/framework` | 13.35.0 on 10 Oct | 13.35.0 (same) |
| PHP dev packages | pint, pail, sail, mockery, collision, faker, larastan, phpunit | the same **plus `laravel/pao ^1.0.6`** ("Agent-optimized output for PHP testing tools"; locked 1.1.5). Not in the baseline |
| Root files | not listed | `.npmrc` (`ignore-scripts=true`) and `pnpm-workspace.yaml` are in the kit. Not in the baseline |

Locked versions seen in the temporary copy: `inertiajs/inertia-laravel` v3.5.1, `laravel/fortify` v1.41.0, `laravel/wayfinder` v0.1.21, `larastan/larastan` v3.13.0, `phpunit/phpunit` 12.5.39, `vite-plus` 0.3.0. These are not final; T0.1 records the final ones in `docs/installed-versions.md`.

### Decision needed (from you)

Document and section: `docs/cards/M0.md` T0.1 step 2 ("If Composer cannot download packages, stop and say so") and `docs/stack-baseline.md` ("Cloud sessions", network row).

| Option | What you do | What it changes | Cost and risk |
| --- | --- | --- | --- |
| **A. Build on the laptop** (recommended) | Install Claude Code on the laptop (`docs/stack-baseline.md`, "Claude Code on the laptop") and run each task there in Plan mode, with the same prompts | No project file changes. Composer works normally there (your laptop is not behind the session's GitHub proxy). Installs are the same as CI | The laptop must be on and online while a task runs. You lose "close the tab and come back later" |
| **B. Keep cloud sessions, fix the start script** | A `[spec-change]` pull request by you: change `scripts/cloud-session-start.sh` (cloud part only) to fetch `phpstan/phpstan` with git into Composer's cache and install the other packages with git. Also change card T0.1 steps 2 and 4 and the baseline row to match. I write the exact text for you if you choose B | Cloud sessions can install packages again | It relies on how Composer names its cache files (an internal detail that can change), and cloud installs differ slightly from CI (extra files). More code in a protected safety script |
| C. Wait for the platform | Report it to Anthropic support and ask that public-repository zip downloads be allowed through the GitHub proxy | Nothing in the project | Unknown waiting time. No work can happen in the cloud meanwhile |

Second, smaller question (for when T0.1 resumes): the kit now includes `laravel/pao`, which `docs/stack-baseline.md` does not list. Keep it (it comes with the kit; T0.1 says install no package the card does not name, and this one is not installed by me), or remove it? It is a dev-only tool that changes how test results are printed for AI agents.

### Your decision (13 October 2026)

- **Option B:** keep cloud sessions and fix how PHP packages are installed there.
- **`laravel/pao`: keep.** Note: in Claude sessions it prints test results as JSON (made for AI agents); on your laptop the output looks normal.

### Option B: what I prepared and tested

The fix touches protected files, so I cannot make it. I wrote it in a scratch copy of `main` (outside the repository), tested it, and saved it as one patch file: `docs/proposals/G-30-cloud-composer.patch`. The real protected files are unchanged.

How the fix works, in plain words:
- The cloud GitHub proxy blocks GitHub zip downloads but allows `git`.
- A new script, `scripts/cloud-composer-cache.sh`, reads `composer.lock`, fetches each package's exact commit with `git`, and builds the zip with `git archive` (the same way GitHub builds its zips). It puts each zip where Composer looks for downloads it already has.
- Composer then installs normally and never asks GitHub for a zip. `composer.lock` is not changed. Laptop and CI are not affected.

What the patch changes (7 files):

| File | Change |
| --- | --- |
| `scripts/cloud-composer-cache.sh` | New. The script above |
| `scripts/cloud-session-start.sh` | Cloud part only: runs the new script before `composer install`, and sets `COMPOSER_ALLOW_SUPERUSER=1` so Composer plugins (Pest's) stay on |
| `docs/cards/M0.md` | T0.1 steps 2 and 4: a "cloud session" block next to the unchanged laptop commands |
| `docs/stack-baseline.md` | New row "GitHub proxy and Composer" (the fact and the fix); `laravel/pao`, `.npmrc`, `pnpm-workspace.yaml` recorded as kit facts |
| `docs/README.md` | The new script listed under Tooling |
| `docs/requirements-audit.md` | New gap row G-30 |
| `docs/spec.md` | §20.4 change log row "1.0 (update 7)" |

Tests I ran (all in scratch folders, each starting from an **empty** Composer cache, like a fresh cloud session):

| Test | Result |
| --- | --- |
| New script on the kit's `composer.lock` | 139 zips built in 33 seconds |
| `composer install` using only those zips | exit 0; 139 of 139 "from cache", 0 downloads, 0 errors; no duplicate-class warning (unlike the plain git install) |
| Card step 2, cloud block, run word for word | exit 0; kit commit `717b8f55`; 139 packages from cache; kit scripts ran; kit's `.git` removed |
| Card step 3 (unchanged) | `chisel.php`, `chisel-paths.php`, `InstallFeaturesCommand.php` gone; `install:features` no longer in `composer.json` |
| Card step 4, cloud block, run word for word | exit 0; Pest 4.7.8 and pest-plugin-laravel 4.1.0 (same as baseline); Pest's plugin active |
| Patched `cloud-session-start.sh`, fresh-session simulation (app present, no `vendor/`) | 151 packages installed in 39 seconds; `vendor/bin/phpstan` and `vendor/bin/pest` present |
| `shellcheck -S warning -x scripts/*.sh` (what the Infra check runs) | passed (shellcheck 0.11.0) |
| `bash infra/tests/test-guardrails.sh` | 97 passed, 0 failed |
| `bash scripts/secret-scan.sh` | passed |
| `scripts/check-protected.sh` | fails without approval and passes with `[spec-change]`, as it should |
| Patch applied to a clean copy of `main` | applies cleanly; result identical to the tested copy |

Two things the protection hook stopped (I did not try another way for either):
- Marking the new script as executable in the scratch copy: the hook read the path as the protected file in the real repository. It does not matter: the script is always run as `bash scripts/cloud-composer-cache.sh`.
- Writing your steps into this log with a shell command, because the text contained the human-only approval option of `check-all.sh`. I left that command out of your steps; CI checks the `[spec-change]` approval anyway.

Known limit (also written in the baseline row): the fix relies on how Composer names its cached files (checked in Composer 2.8.12's own code). If a future Composer changes that, cloud installs fail again with the same 403 and the start script prints a warning. The laptop and CI are never affected.

### Your steps (on the laptop, about 10 minutes plus CI)

```bash
cd ~/Projects/zb-protein-mill
git checkout main && git pull
git checkout -b spec/G-30-cloud-composer
git fetch origin claude/lucid-ptolemy-w3fhsy
git show origin/claude/lucid-ptolemy-w3fhsy:docs/proposals/G-30-cloud-composer.patch | git apply --index
git diff --cached --stat        # must list the 7 files above
git commit -m "G-30: cloud sessions install PHP packages through git-built zips"
git push -u origin spec/G-30-cloud-composer
gh pr create --title "[spec-change] G-30 cloud sessions: PHP packages through git" --body "Fixes the T0.1 blocker found in hammaadTCS/zb-protein-mill#1: the cloud GitHub proxy refuses Composer's GitHub zip downloads. Prepared and tested as described in docs/work-log.md on branch claude/lucid-ptolemy-w3fhsy."
```

Then on GitHub: read the diff, wait until **CI and Infra** are both green (Infra runs because `scripts/` changed), and merge. Then tell me "merged" in this session. I will then:
1. merge `main` into this branch;
2. delete `docs/proposals/G-30-cloud-composer.patch` (it will be on `main` by then);
3. continue T0.1 from step 2 with the new cloud block.
