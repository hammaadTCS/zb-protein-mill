# Work log

A plain record of every task session: what was done, in what order, what was found, and what is left.
Newest task at the bottom. One section per task. This file is not a rule document; the rules are in `CLAUDE.md` and `docs/`.

## Where the project stands

| Item | Status |
| --- | --- |
| Current task | Building all remaining tasks in one session (your instruction, 13 October 2026) |
| State | **T0.1 built; all local checks pass.** Waiting for CI on the pull request, then your review. G-30 (`[spec-change]`) still to be applied and merged by you |
| Branch | `claude/lucid-ptolemy-w3fhsy`, pull request [hammaadTCS/zb-protein-mill#1](https://github.com/hammaadTCS/zb-protein-mill/pull/1) |
| Tasks ticked in `docs/tasks.md` | T0.1 |
| Next task | T0.2, in a new session, after T0.1 is merged into `main` |

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
2. delete `docs/proposals/G-30-cloud-composer.patch` (it will be on `main` by then).

(Update: you asked me to carry on building, so T0.1 was built before G-30 reached `main`. See "T0.1 build" below.)

### T0.1 build (13 October 2026)

You asked me to build the project, checked against the client PDF, as a senior developer. The project rules allow one task per session and pull request, so this session builds **T0.1 only**. T0.2 starts in a new session after you merge T0.1.

**The PDF:** the file you uploaded is byte-for-byte the same as `docs/client/Accounts_System_Requirements_v1.2.pdf` (same SHA-256). I read all 12 pages. T0.1 covers only the base app and its login features: no self-registration, no e-mail verification, two-step login kept (PDF §11 "Two-step login ... available for every user"; spec BR-USR-06). That matches.

**One difference between the PDF and the spec (question for you and the client):** PDF Appendix A, page 1 "Login" says "Username and password". The spec and the starter kit log in with an **e-mail address** (spec BR-USR-09 uses `zb:reset-password {email}`; the test user is `test@example.com`). Nothing in T0.1 changes the login. It must be settled before T5.2 (Users page): either the client accepts e-mail login, or a `[spec-change]` adds usernames.

**Steps, as in the card (cloud blocks from G-30):**

| Step | Result |
| --- | --- |
| 1 Tools | PHP 8.3.6, Composer 2.8.12, Node 22.22.0, npm 10.9.4, PostgreSQL works |
| 2 Create the app | Kit `dev-main 717b8f55aefd82d25d4119eaebdc8e3a72b8d7e5` (same as the baseline); 139 packages from the git-built cache |
| 3 Feature installer | Kept only `2fa` and `password-confirmation`. `chisel.php`, `chisel-paths.php`, `InstallFeaturesCommand.php` gone; `composer.json` no longer mentions `install:features`. Removed: registration, e-mail verification, passkeys (pages, tests, migration) |
| 4 Platform and Pest | `platform.php` 8.3.6; Pest 4.7.8, pest-plugin-laravel 4.1.0; `tests/Pest.php` created |
| 5 Font | `@fontsource/ibm-plex-sans` 5.3.0; `vite.config.ts` uses `fontsource('IBM Plex Sans')`; `app.css` font stack changed; build has 6 font files of its own and no link to an outside font site |
| 6 Settings files | `.env.example` and `phpunit.xml` exactly as the card lists; `database/database.sqlite` deleted |
| 7 Move into the repository | 168 kit files copied, none overwritten, no protected path. Not copied: kit `.github/`, `.gitattributes`, `README.md`. `.gitignore`: removed `/.claude` and `/CLAUDE.md`, added `.claude/settings.local.json` |
| 8 In the repository | `composer install`, `npm ci`, `.env` from `.env.example`, `key:generate`, `migrate` (PostgreSQL) all passed |
| 9 Versions | `docs/installed-versions.md` written, with 6 differences from the baseline |

**Problems found and how each was handled:**

1. **First feature-installer run stopped half-way.** In a new shell I had not set `COMPOSER_ALLOW_SUPERUSER=1`; Composer then refuses to run scripts as root, so the installer's `composer lint` failed and it quit before deleting its own files. I threw that copy away and redid steps 2 and 3 from scratch with the setting, checking the exit code. The second run was clean.
2. **`tests/Pest.php` and Pint disagree.** The card says "exactly" this text, and also that `vendor/bin/pint --test` must pass. The kit's Pint rules reject the full class names in that text. I let Pint rewrite it: same three settings, with `use` lines at the top. **Question for you** (below).
3. **`npm run check` failed on your protected documents.** The kit's formatter checks every file in the repository, including `docs/`, `CLAUDE.md`, `START-HERE.md`, `README.md`, `.claude/` and `infra/README.md` (49 files, all protected). I cannot reformat them. I added exactly those paths to the formatter's ignore list in `vite.config.ts`, with a comment. No app code is excluded.
4. **`npm run types:check` failed: Laravel 13.35.0 and Wayfinder disagree.** Laravel 13.35.0 (the newest) added the HTTP verb `QUERY`. `Route::redirect()` registers every verb, and Wayfinder 0.1.21 (the newest, also its `main` branch) has no `query` in its TypeScript types. I changed the one `Route::redirect('settings', '/settings/profile')` in `routes/settings.php` to `Route::get(...)` with the same Laravel `RedirectController` and the same target, so it answers GET and HEAD only. Checked with a temporary test (deleted afterwards): logged-in user is redirected to `/settings/profile`, a guest goes to login, POST gets 405. The other choice, holding Laravel at 13.34, would block security updates. **Question for you** (below).
5. **`check-all.sh` runs the PHP tests before the front-end build.** On a fresh checkout the kit's page tests fail with "Vite manifest not found" until a build exists. CI builds first, so CI is not affected. **Proposed change** to `scripts/check-all.sh` (protected) below.

**Found for later tasks (not changed now):**

- The guard script (required from T0.3) already flags `$user->delete()` in `app/Http/Controllers/Settings/ProfileController.php`. That is the kit's "delete my account" feature, which T0.2 removes. Its rules for `phpunit.xml` and `.gitignore` already pass.
- `npm audit`: 3 critical advisories, all in development tools (`tinypool` inside `oxfmt` inside `vite-plus` 0.3.0), none in shipped code. The project's audit check passes. A fix needs a newer `vite-plus`: your call, or wait for the monthly Dependabot update.

**Checks:** `bash scripts/check-all.sh`:

```text
==================== Summary ====================
PASS  Secret scan
PASS  Protected files unchanged (or approved)
PASS  PHP tests (Pest, PostgreSQL)
PASS  PHP code style (Pint)
PASS  Static analysis (Larastan level 7)
PASS  Front-end build (also generates Wayfinder routes)
PASS  Front-end lint and format (vite-plus)
PASS  TypeScript
skip  Front-end unit tests (added in T2.1)
skip  Project guard (starts in T0.3)
PASS  Known-vulnerable PHP packages (needs internet)
PASS  Known-vulnerable JS packages (needs internet)

ALL CHECKS PASSED.
```

CI on PHP 8.5 cannot be run here; the pull request shows it.

### Questions and proposed spec changes (T0.1)

1. **Login name (client).** PDF Appendix A says "Username and password"; the spec uses e-mail. Ask the client, or decide a `[spec-change]` before T5.2.
2. **`tests/Pest.php` wording (card T0.1 step 4).** Accept Pint's version, or change the card's text to it:
   ```php
   <?php

   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;

   pest()->extend(TestCase::class)
       ->use(RefreshDatabase::class)
       ->in('Feature');
   ```
3. **`/settings` redirect (Laravel 13.35 + Wayfinder).** Accept the GET-only route, or hold Laravel at 13.34 until Wayfinder supports `QUERY`. I recommend the GET-only route. Suggested addition to `docs/stack-baseline.md`, Laravel table: "Laravel 13.35.0 adds the HTTP verb `QUERY` to `Router::$verbs`; `Route::redirect()` and `Route::any()` register it. Wayfinder 0.1.21 has no `query` in its TypeScript `Method` type, so such routes break `npm run types:check`. Use `Route::get` for redirects."
4. **`scripts/check-all.sh` order (protected).** Move the front-end build before the PHP tests, as CI does. Exact change: cut the line
   `run "Front-end build (also generates Wayfinder routes)" npm run build`
   and paste it directly above
   `run "PHP tests (Pest, PostgreSQL)" php artisan test`.
5. **G-30** is still waiting for your `[spec-change]` pull request (steps above). Until it is merged, a new cloud session cannot install PHP packages.

---

## One-session build (from 13 October 2026)

You asked: "continue, build in one go then we will test it on my local PC". Your process normally has one task per session and pull request; this session builds the tasks one after another on branch `claude/lucid-ptolemy-w3fhsy`, **one commit per task** (message starts with the task ID), one section per task below. Where the documents do not decide something, I take the most cautious choice, note it under the task as a question, and continue. Human tasks (T2.10, T7.6 restore, T8.3 to T8.7) are not done.

Before starting: PR #1 (T0.1) was merged; I fast-forwarded the branch to `main` (`b2a1735`). A `git checkout -B` was refused by the session's safety check; the fast-forward did the same without any risk.

### T0.2 Project files check, brand icons, remove kit features the spec forbids

- All 16 project files and folders the card lists exist.
- Icons: `public/favicon.svg` (ZB monogram), `public/favicon-32.png`, `public/apple-touch-icon.png` from `brand/`; `public/favicon.ico` removed; `app.blade.php` points at `favicon-32.png` and `favicon.svg`.
- Removed (BR-USR-06): password reset (`Features::resetPasswords()`, the two pages, the "Forgot your password?" link, `ResetUserPassword`, its registration and views in `FortifyServiceProvider`).
- Removed (BR-USR-03, BR-USR-06): delete account (`profile.destroy` route, `ProfileController::destroy`, `ProfileDeleteRequest`, `delete-user.tsx`).
- Removed: public welcome page. `GET /` now redirects to `/dashboard` (guests then go to login). Like the T0.1 `/settings` fix, it uses `Route::get` with Laravel's `RedirectController` instead of `Route::redirect`, so it does not register the `QUERY` verb Wayfinder cannot type.
- `.env.example`: `SESSION_LIFETIME=60` (BR-SEC-04).
- Kit tests removed because they only covered removed features: `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/ExampleTest.php` (welcome page), and the two delete-account methods of `tests/Feature/Settings/ProfileUpdateTest.php` (`test_user_can_delete_their_account`, `test_correct_password_must_be_provided_to_delete_account`).
- New tests (`tests/Feature/Auth/RemovedKitFeaturesTest.php`): BR-USR-06 registration route does not exist; BR-USR-06 password reset routes do not exist; BR-USR-03 there is no delete account route; BR-SEC-03 login is throttled after 5 failures; the home page sends guests to login.
- `scripts/guard.sh` now passes (the kit's `$user->delete()` is gone).
- Checks: ALL CHECKS PASSED (26 PHP tests).

### T0.3 Architecture tests and guard script in CI

- Larastan: `phpstan.neon` level 7, no baseline file. Kit code passes; nothing changed.
- `tests/Arch/ArchitectureTest.php` copied from `docs/templates/ArchitectureTest.php`, byte for byte (`cmp` checked). It is now protected.
- `tsconfig.json` already has `"strict": true`.
- `bash scripts/guard.sh` passes (T0.2 removed the only kit line it flagged).
- **Added (not in the card): an `Arch` test suite in `phpunit.xml`.** The kit's `phpunit.xml` runs only `tests/Unit` and `tests/Feature`, so without it `php artisan test` (and CI) would never run the architecture tests.
- **Reported as the card asks: one rule fails only because its namespace does not exist yet.** `conventions §2: actions are classes with a handle method` uses `expect('App\Domain\*\Actions')`; with no Actions folder Pest stops with "The app/Domain/*/Actions directory does not exist". I proved with a temporary class (deleted afterwards) that the rule passes as soon as one `App\Domain\<Module>\Actions` class exists. The first real Actions come in T2.2, so **from T0.3 to T2.1 this single test fails** for that reason only; I check after every task that it is the only failure. Possible `[spec-change]` if you want it green earlier: none needed in the test; it fixes itself in T2.2.
- Checks: all pass except that one architecture rule (35 of 36 PHP tests).
