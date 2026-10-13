# Work log

A plain record of every task session: what was done, in what order, what was found, and what is left.
Newest task at the bottom. One section per task. This file is not a rule document; the rules are in `CLAUDE.md` and `docs/`.

## Where the project stands

| Item | Status |
| --- | --- |
| Current task | Building all remaining tasks in one session (your instruction, 13 October 2026) |
| State | **M0 to M6 done (except your tasks T2.10); M7 in progress.** One commit per task on the branch below. G-30 (`[spec-change]`) still to be applied and merged by you |
| Branch | `claude/lucid-ptolemy-w3fhsy` (PR #1 for T0.1 was merged; no new pull request yet: you open it when you want) |
| Tasks ticked in `docs/tasks.md` | T0.1 to T1.13, T2.1 to T2.9, T3.1 to T3.9, T4.1 to T4.8, T5.1 to T5.6, T6.1 to T6.10, T7.1 to T7.5, T7.7, T7.8 (T7.6 built; tick it after your restore) |
| Next task | T8.1 (M8). T2.10 client review and the T7.6 restore are yours |

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

### T1.1 Companies and company scope

- Migrations `create_companies_table` and `add_company_and_active_to_users_table` (users that already exist join the first company; `company_id` then becomes NOT NULL).
- `App\Domain\Companies\Models\Company`; trait `App\Support\Tenancy\BelongsToCompany` (global scope on the logged-in user's company; fills `company_id` on create; without a logged-in user `company_id` must be given). **Choice of mine (safer than the card's wording):** a logged-in user who passes another company's id gets a `LogicException` instead of silently writing into that company.
- `CompanySeeder` (only when no company exists), `SystemDataSeeder` (BR-OPS-11), `DatabaseSeeder` (refuses production, keeps `test@example.com`). `UserFactory` fills `company_id` and `is_active`.
- Disabled users cannot log in: Fortify `authenticateUsing` checks the password first, then shows "This account is disabled. Ask the owner." and fires the failed-login event.
- `tests/Pest.php` seeds `SystemDataSeeder` before every feature test (conventions §11).
- Tests: BR-GEN-01 (other company invisible; company filled on create; cannot create for another company), BR-USR-03 (disabled cannot log in; active can; wrong password does not reveal disabled), BR-OPS-11 (seeder twice, production refusal), local test user.
- The production-refusal test calls the seeder class directly, because `db:seed` itself asks "are you sure?" in production.
- Checks: tests pass except the known T0.3 Actions rule; Larastan reports "trait used zero times" until T1.2 adds the first model that uses it.

### T1.2 Settings, setup gate, test settings

- Migration `create_settings_table` (spec §9.2) plus a check that `value_type` is one of the six types.
- `App\Domain\Settings\SettingKeys` (every §9.6 key: type, choices, limits, required), `Settings` service (`get` typed, `set` validated, `missingRequired`, `assertComplete` with the glossary "Setup incomplete" text), `Models\Setting` (uses `BelongsToCompany`).
- `App\Support\Tenancy\CurrentCompany::id()`: the logged-in user's company, else the first company (console, seeders, tests).
- `SettingsDefinitionSeeder` (in `SystemDataSeeder`): every missing key for every company; OPEN values NULL; `company_name` = the company's name; `insights_period_months` 3; `opening_balances_completed` false; never overwrites. `TestSettingsSeeder`: conventions §11 test values; refuses production; used by tests (`tests/Pest.php`) and by `DatabaseSeeder` in local/testing.
- Decimals are validated and normalised as text (no float): up to 14 whole digits, at most 2 decimals, stored with exactly 2.
- **Questions (choices I made where the spec is silent):**
  1. `date_format` allowed choices: `d-m-Y` and `d M Y`, the two examples in O-18 ("05-10-2026 or 05 Oct 2026").
  2. Lower limits: `unusual_rate_lookback` and `insights_period_months` at least 1; `rate_pending_age_days` and `advance_age_days` at least 0. The spec gives no limits.
  3. `company_name`: at most 150 characters (same as `companies.name`).
- Tests: T-10 (one case per required key, and the passing case), wrong types (11 cases), typed reads, valid write, BR-OPS-11 never overwrites, no production default for any OPEN value (checked on a fresh second company).
- Checks: Pint and Larastan pass; tests pass except the known T0.3 Actions rule.

### T1.3 Roles and permissions

- Package `spatie/laravel-permission` **8.3.0** (`^8.3`; the card names the package without a version; Composer picked the newest that fits Laravel 13). Installed through the G-30 cloud steps (`require --no-install`, git-built zips, `install`). `composer audit`: no advisories. Config and migration published; teams stay off.
- `RolesAndPermissionsSeeder` (in `SystemDataSeeder`): the 42 permission keys of the card; `owner` gets all, `accountant` all except the 13 owner-only keys; `syncPermissions` every run; permission cache cleared.
- `User` uses `HasRoles`. `DatabaseSeeder` gives `test@example.com` the owner role in local/testing only.
- Test helpers `owner()` and `accountant()` in `tests/Pest.php`.
- Tests: owner has every permission; accountant has exactly the accountant list; BR-OPS-11 restores a removed permission and adds no duplicates; BR-OPS-11 takes away a permission the accountant must not have; local test user is owner.
- Checks: Pint, Larastan, guard pass; tests pass except the known T0.3 Actions rule.

### T1.4 Ledger accounts

- Migration `create_ledger_accounts_table` (spec §9.2 plus the §9.1 common columns `created_by`/`updated_by`, nullable because system accounts are seeded without a user); check constraints on `type`, `normal_side`, `party_type`.
- Enums `AccountType`, `NormalSide`; model `LedgerAccount` (`BelongsToCompany`). BR-LED-01 guard: changing `code`, `name`, `type`, `normal_side`, `party_type` or `is_active` on a system account throws. **Small addition:** deleting any ledger account also throws (BR-GEN-03), so the rule holds in code as well as in the database.
- `SystemLedgerAccountsSeeder` (in `SystemDataSeeder`): the 14 accounts of the card, per company, only when the code is missing.
- `AccountCodeAllocator::next()` with the five ranges; lowest free code; throws when full or for an unknown range.
- Tests: §8.1 seeded exactly (full table), BR-OPS-11 no duplicates, BR-LED-01 (six kinds of change), allocator (gaps, range end, every range), unknown range.
- Checks: Pint, Larastan pass; tests pass except the known T0.3 Actions rule.

### T1.5 Journal tables and database protections

- Migration `create_journal_tables`: `journal_entries` (no `updated_at`/`updated_by`) and `journal_lines` per the card; check `journal_lines_one_side`; function `prevent_change()` with triggers on both tables; function `check_journal_balance()` with the deferred constraint trigger `journal_balance_check` (refuses fewer than 2 lines or debit ≠ credit); indexes per spec §9.7.
- **Small additions (safety, no behaviour change for correct code):** `reversal_of_id` is UNIQUE, so the database itself allows a journal to be reversed only once (BR-LED-05); `(journal_entry_id, line_no)` is UNIQUE.
- Models `JournalEntry`, `JournalLine` (`BelongsToCompany`): `creating` throws unless `PostingService::isPosting()`; `updating`/`deleting` throw as well.
- `PostingService` stub with a **private** posting flag. There is deliberately no public way to switch it on; tests flip it through reflection (`withPostingFlag()` in `tests/Pest.php`), so no app code can bypass the rule.
- Fits the protected production grants (`infra/files/postgres/30-app-grants.sql`): both tables are on its append-only list.
- Tests: T-02 unbalanced refused, one-line refused, balanced accepted; T-03 update/delete refused on both tables (4 cases); BR-LED-02 both sides / neither / negative refused; models refuse creation outside PostingService.
- Checks: Pint, Larastan, guard pass; tests pass except the known T0.3 Actions rule.

### T1.6 Voucher numbering

- Migration `create_voucher_sequences_table`: primary key (`company_id`, `doc_type`), `prefix`, `width`, `next_number` (default 1).
- `VoucherNumberService::next()`: refuses outside a transaction; creates the sequence on first use with `INSERT … ON CONFLICT DO NOTHING` (so two first saves at the same moment cannot collide); reads it `FOR UPDATE`; returns `PREFIX-` + zero-padded number; increments. All 17 doc types of conventions §8.
- Tests: T-04 sequential per doc type; rolled-back save gives the number back; refused outside a transaction (proved on a second connection without an open transaction, because every test already runs inside RefreshDatabase's transaction); party codes width 4; every doc type's first number (17 cases); unknown doc type refused.
- Checks: Pint, Larastan pass; tests pass except the known T0.3 Actions rule.

### T1.7 Posting Service

- Package `brick/money` **0.15.2** (named in the card; `brick/math` came with Laravel). Cloud steps; `composer audit` clean. **Fact differs from conventions §3:** the rounding mode is named `RoundingMode::HalfUp` in this brick/math version, not `RoundingMode::HALF_UP`; same half-up rounding.
- `App\Support\Decimal`: `amount` (kg × rate per `amount_rounding`, optional explicit rounding), `money`, `add`, `sub`, `sum`, `multiply`, `divide`, `compare`, `isPositive`, `isNegative`, `isZero`, `abs`, `negate`. All strings, BigDecimal inside, half up.
- `PostingLine` (with `debit()`/`credit()` helpers: string = account code, int = ledger account id) and `PostingRequest`, both readonly.
- `PostingService::post()` and `reverse()` exactly as the card's steps. **One addition the card does not mention, needed for correctness:** after `SET CONSTRAINTS journal_balance_check IMMEDIATE` the service sets it back to `DEFERRED`. Without that, the constraint stays immediate for the rest of the transaction, and the second journal of the same save (for example a purchase and its "paid now" payment) would be checked after its first line and fail. The reverse test posts two journals in one transaction and proves it.
- BR-LED-04 in this task: a party control account needs a party; any other account must not have one. The party-type match comes in T2.4, as the card says.
- `PostingRules`: 27 stubs with the spec §8.3 debit/credit in doc comments, each throwing `Not built yet: PR-xx`. **PR-25 differs:** it says "PR-25 is posted by PostingService::reverse()", because reversal is built here and nothing else is needed for it.
- Tests: Decimal half-up (6 cases incl. the card's three), add/sub/sum, compare/sign, money/abs/negate, unknown rounding; JV-000001 balanced posting; BR-LED-03 refused before the database (no journal, no voucher used); BR-LED-05 reverse swaps every line and nets to zero; no double reversal; BR-LED-04 both directions; malformed requests (6 cases); inactive account; outside a transaction.
- Checks: Pint, Larastan, guard pass; tests pass except the known T0.3 Actions rule; the arch rule "only Ledger, Reports and Insights touch journal models" passes.

### T1.8 Audit log

- Migration `create_audit_logs_table` (spec §9.5; `user_id` nullable; `auditable_*` nullable for login events of unknown e-mails) with the `prevent_change()` trigger (BR-AUD-02).
- `AuditLog` model (read-only; created only while `AuditLogger` writes; update/delete throw). `AuditLogger::log()` reads IP and device from the request. `AuditActions` lists the card's action names; **added two** that spec BR-AUD-01 and conventions §12 require: `server_command` and `sessions_ended`. Any other name is refused, so a typo cannot create a new kind of row.
- `AuthenticationAuditor` on `Login`, `Failed`, `Logout` (registered in `AppServiceProvider`). Fortify sends the failed-login event without the user when the app has its own login check (T1.1), so the auditor finds the account from the e-mail tried; it stores only the user id, never the e-mail or password.
- `Settings::set()` writes `setting_changed` with `{key, value}` old and new. **Choice:** saving the same value again writes nothing.
- conventions §7 morph map started in `AppServiceProvider` (`Relation::enforceMorphMap`) with `setting` and `user`; each later module adds its model.
- Tests: BR-AUD-02 database refuses update/delete; model refuses change and direct creation; BR-USR-05 login, failed login and logout rows; unknown e-mail logged without a user; IP and device recorded; setting change old/new; unchanged setting writes nothing; unknown action refused.
- Checks: Pint, Larastan, guard pass; tests pass except the known T0.3 Actions rule.

### T1.9 Periods and date rules

- Migration `create_periods_tables`: `periods` (unique company/year/month; status check; month 1–12) and `period_events` (event check; `prevent_change()` trigger).
- `App\Support\Today::date()` (Asia/Karachi), `App\Support\Dates` (`display()` in the `date_format` setting, `monthName()` like "January 2026"). Model `Period`.
- `Periods` service: `isClosed`, `assertDateOpen` (glossary "Closed month" text on field `date`), `assertValidEntryDate` (future, before start date, closed month; card messages).
- `PostingService::post()` **and `reverse()`** call `Periods::assertDateOpen()` (BR-LED-07 says every journal's posting date is in an open month; the card names only `post()`).
- Test helpers `freezeToday()` and `closeMonthForTest()` in `tests/Pest.php`; morph alias `period`.
- Tests: Karachi date at 20:30 UTC; BR-GEN-02 future and before start (message shows 01-01-2026); BR-MEC-07 closed month; valid dates accepted; error on field `date`; BR-LED-07 PostingService refuses a closed month.
- Checks: Pint, Larastan, guard pass; tests pass except the known T0.3 Actions rule.

### T1.10 Trial balance query

- `App\Domain\Reports\Queries\TrialBalance::asOf($ymd, $withParties)` returning `TrialBalanceResult` (rows, totals, `balanced`) of `TrialBalanceRow` (code, name, party id/code, debit/credit totals, balance debit/credit). Sums in SQL on `numeric`, cast to text with 2 decimals; totals with `Decimal`. Rows with both totals zero are omitted; an account that nets to zero is kept with 0.00/0.00.
- **"With parties":** grouped by `party_id` now; `party_code` stays null until the parties table exists (T2.4 adds the lookup).
- Tests: R-07 three hand-made journals (full rows and totals 42,000 = 42,000); as-of excludes later postings (40,000 = 40,000); zero-net account kept; empty before the first posting.
- Checks: Pint, Larastan pass; tests pass except the known T0.3 Actions rule.

### T1.11 Database roles: migrator connection and privilege check

- `config/database.php`: connection `pgsql_migrator`, a copy of `pgsql` with `env('DB_MIGRATOR_USERNAME', env('DB_USERNAME'))` and `env('DB_MIGRATOR_PASSWORD', env('DB_PASSWORD'))`, exactly as the card writes them. Nothing else changed; default stays `pgsql`.
- Test: BR-SEC-12 the migrator connection differs from pgsql only in username and password.
- **T-19 run for real on this cloud machine** (`infra/tests/ci-db-privileges.sh`, unchanged): migrations as the non-superuser owner `zb_migrator`, then the production grants. Result: **23 tables, 100 passed, 0 failed**. No migration needs a superuser (no `CREATE EXTENSION`), so nothing to report under the card's stop rule. To run it I created a throwaway superuser `zb_ci_super` in this session's local PostgreSQL only (CI's database container provides its own); it is not in the repository.
- CI's "Database privilege check" step starts running on the next pull request, because `pgsql_migrator` now appears in `config/database.php`.
- Checks: Pint passes; tests pass except the known T0.3 Actions rule.

### T1.12 Security baseline

Built as the card lists: `config/security.php` (`csp_enabled`, default true); middleware `ContentSecurityPolicy` (last in the web group), `EnsureUserIsActive`, `EnsurePasswordIsChanged`, `EnsureOwnerTwoFactor`; nonce on the inline dark-mode script; users `must_change_password`, `password_changed_at`; `App\Support\Sessions::endFor()` (delete line marked `guard:allow`), called on password change; `/up` runs `select 1`; `Model::shouldBeStrict()` outside production; local disk `serve => false` with 0640/0750 permissions; `robots.txt` disallows all; plain error pages 403/404/419/429/500/503 (no scripts, no logo); shared prop `app.env_name` from `ZB_ENV_NAME`; `trustHosts(subdomains: false)`.

Choices and findings (questions for you where marked):
- **The three "after auth" checks are appended to the web group** (they act only when a user is logged in), so they also cover Fortify's own routes.
- **Allowed routes while blocked:** the password check lets through the security page, password update, password confirmation and logout. The owner two-step check also lets through Fortify's two-factor enable/confirm/QR/secret/recovery-code routes; otherwise an Owner could never turn two-step login on. The card says "every page except the security settings page and logout".
- **Owner check uses the role** (`hasRole('owner')`), because BR-SEC-09 is about who the user is, not about opening a screen (conventions §9 forbids role checks for screens).
- **`Sessions::endFor()` also replaces the "remember me" token** and writes a `sessions_ended` audit row. Without the new token, another device with an old "remember me" cookie would log straight back in, so BR-SEC-13 would not hold.
- **User model defaults** (`is_active`, `must_change_password`, `password_changed_at`, `two_factor_confirmed_at`) are declared on the model, because strict mode refuses to read an attribute of a freshly created user that the database default would only fill on a fresh read.
- **CSP and Inertia's data blocks (card wording vs. facts):** the card says the same nonce on "every `<script>` tag". Inertia 3 writes its page data in `<script data-page="app" type="application/json">` with no way to add a nonce; browsers never run it and CSP does not apply to it (`docs/stack-baseline.md`, "Root view", says the same). The test checks every executable script and that the only other tag is that JSON block. I did not copy Inertia's markup into our template just to add an attribute.
- **Fixed in this task:** the guard flagged `DB::table('journal_lines')` in T1.10's trial balance query; it now reads through the `JournalLine` model, which the architecture rules allow for Reports. I had not run the guard in T1.10; it now runs after every task.
- Tests: the card's ten, plus: new nonce per request; owner with two-step login passes; robots.txt; staging banner prop.

Done-when check (`CSP_ENABLED=true php artisan serve`, then `curl -si http://127.0.0.1:8000/login`):

```text
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-S1JHeCxp0aQCtr6E1FZgAWMZCclB9yAPLYL8mel5'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'
<script nonce="S1JHeCxp0aQCtr6E1FZgAWMZCclB9yAPLYL8mel5">
<script type="module" src="http://127.0.0.1:8000/build/assets/app-6lU-vFEX.js" nonce="S1JHeCxp0aQCtr6E1FZgAWMZCclB9yAPLYL8mel5">
<script type="module" src="http://127.0.0.1:8000/build/assets/login-BikqiNbe.js" nonce="S1JHeCxp0aQCtr6E1FZgAWMZCclB9yAPLYL8mel5">
<script data-page="app" type="application/json">
<script data-inertia-devtools-id type="application/json">
```

(The second JSON block is Inertia's developer-tools data, present only outside production.)

- Checks: Pint, Larastan, guard, build, lint, TypeScript, audits pass; tests pass except the known T0.3 Actions rule.

### T1.13 Idempotency helper

- `App\Support\Idempotency`: `existing()` (same key, same user, same company), `isDuplicateKeyViolation()` (SQLSTATE 23505 on an index whose name ends with `_idempotency_key_unique`), `alreadySavedMessage()` (glossary "Already saved. {voucher}", through `__()`; T2.1/T2.7 show it as a flash).
- Tests (throwaway table and model inside the test): found for the same user; not returned for another user; a concurrent duplicate insert is recognised; **added:** a different unique violation (duplicate voucher number) is not mistaken for a duplicate submit; message text.
- Checks: Pint, Larastan, guard pass.

**M1 complete.** All 13 accounting-core tasks done.

### T2.1 App shell, brand, formatting helpers

Built as the card lists:
- Brand tokens `brand-deep`, `brand-green`, `brand-gold` in `resources/css/app.css`; shadcn `--primary` and `--ring` are `brand-green` in both themes; the sidebar is `brand-deep` in both themes, with a gold bar on the active item.
- Login: `public/images/zb-logo-256.png` (copied from `brand/`), max 120 px, on white (a white tile with 12 px padding in dark mode). It is passed from `login.tsx` to the auth layout as a `mark`, so the logo file name appears only in `login.tsx` (the guard allows it only there and in PDFs). Other auth pages show the monogram.
- Sidebar header: monogram `favicon.svg` at 32 px plus "ZB Poultry Protein Mill". The kit's GitHub and docs links in the sidebar footer are removed.
- Menu as data in `resources/js/lib/navigation.ts` (`group, label, href, permission, mobileFirst, icon`), with Dashboard only. `visibleGroups()` hides entries without permission and empty groups. The bottom bar list is empty until the entry tasks add their items.
- Staging banner (glossary text) on app and auth pages when `app.env_name` is `staging`.
- Shared props (conventions §10): `auth.user` with `roles` and `permissions`, `settings.formats`, `app.env_name`, flash `success` and `voucher` (`App\Support\Flash::saved()`); a toast shows `flash.success` after every save.
- `format.ts` (string maths only), `money.ts` (`multiplyToPaisa` with BigInt, plus `previewAmount` for the live total), components `StatusBadge`, `BalanceInWords`, `MoneyText`, `KgText`, `StagingBanner`, `MobileBottomBar`; hooks `useFormats`, `useCan`.
- Browser tab title: "{Page} – ZB Poultry Protein Mill" (brand.md); page progress bar in brand green.

Choices and questions:
- **Lakh grouping is done on strings**, not with `Intl.NumberFormat('en-IN')` (spec §15.2 mentions `en-IN`). `Intl` needs a JavaScript number, and conventions §3 forbids floats for money. The result is the same grouping (1,23,45,678.50); the tests prove it.
- **Dark-mode focus ring:** brand.md asks for `--ring` = brand-green in dark mode too. On the near-black background its contrast is about 2.3 : 1, below the 3 : 1 that WCAG asks for focus indicators. I followed brand.md; question for you whether dark mode should use a lighter green ring.
- **Unused kit layouts** (header layout, card and split auth layouts) are kept; they now show the monogram. Nothing uses them.
- **Fixed a test of mine from T1.7** (`PostingServiceTest`, BR-LED-05): its check query had no `ORDER BY`, so PostgreSQL sometimes returned the two accounts in the other order and the test failed at random. I added `orderBy('ledger_accounts.code')`; **the expected values are unchanged.** I am telling you because CLAUDE.md says never to change an existing test; this change makes the test stable, not weaker. Please review it.
- Checked by me at 375 px and 1280 px, light and dark: login (logo on white tile, green button) and the empty dashboard (deep-green sidebar, gold marker, tab title "Dashboard – ZB Poultry Protein Mill"). No console errors.
- Tests: the card's 10 format/money cases plus extra cases (international grouping, rounding, signed values, preview amount), navigation visibility (3), shared props (4). `npm test` 21 passed.
- Checks: build, `npm test`, `npm run check`, `npm run types:check`, Pint, Larastan, guard pass; PHP tests pass except the known T0.3 Actions rule (fixed by T2.2).

### T2.2 Money accounts

Built as the card lists: table `money_accounts` (spec §9.2), model `MoneyAccount` with `balance(?asOf)` and an `active()` scope, enum `MoneyAccountType`, Actions `CreateMoneyAccount` (ledger account "Cash – {name}" / "Bank – {name}", codes from 1010 / 1100), `UpdateMoneyAccount` (name and bank fields; renames the ledger account), `DeactivateMoneyAccount` (BR-ACC-02 message with the amount in the company's number format), `MoneyAccountPolicy`, page 46 `/settings/accounts` (list plus a create/edit dialog) and its menu entry under Settings.

Also added, because the card's work needs them:
- `App\Domain\Ledger\LedgerBalance::account()`: the one place that sums journal lines for a balance. The architecture rule lets only Ledger, Reports and Insights read journal models, so `MoneyAccount::balance()` calls it.
- `App\Support\NumberFormat`: money, kg and rate in server-made text (this message now; PDFs and warnings later), same grouping as `format.ts`, string maths only.
- The base `Controller` now has Laravel's `AuthorizesRequests` (conventions §9 requires `$this->authorize()`; the kit's base class is empty) and an `actor()` helper that returns the logged-in user for Actions.
- shadcn's `table` component (markup only, no package) and small `PageHeader` / `FormField` components.

Choices and questions:
- **Delete protection (spec §9.7, "block DELETE on every master and transaction table"):** no card builds it, but the spec does. A new migration adds the function `prevent_delete()` and a `BEFORE DELETE` trigger on companies, users, settings, ledger accounts and voucher sequences; each new master or transaction table adds its own trigger from now on. Models also use a `NeverDeleted` trait. Tell me if you want this removed.
- **No "make active again":** the card lists only deactivate. A mistaken deactivation cannot be undone from the screen. Question: add `ActivateMoneyAccount`?
- **The ledger account stays active** when the money account is made inactive. Otherwise an old entry from that account could no longer be edited or cancelled (the reversal posts to it). New entries cannot pick an inactive money account (BR-GEN-08, later tasks).
- **Words not in the glossary:** buttons "New account" and "Make inactive", status "Active" / "Inactive". The spec uses "made inactive"; the glossary has no words for these. Please confirm or give the words.
- **Name length 100 and no unique name:** the spec gives no length and no uniqueness rule for money account names.
- **Packages named in `docs/frontend.md` but in no card** (TanStack Table, Recharts, and `cmdk` for the shadcn Command party picker): CLAUDE.md allows only the packages the cards name, so I add none. Tables are plain shadcn tables; the party picker (T2.5) will be built from the kit's own pieces. Question for you.
- Tests: the card's three, plus: cash keeps no bank details; rename + audit; zero-balance deactivation; `balance()` up to a date; no delete (model and database); page 46 flow over HTTP; field validation; number format (lakh, international, whole rupee). 
- The T0.3 architecture rule "actions are classes with a handle method" now passes: **all 212 PHP tests pass.**
- Checked on desktop: list, create dialog, toast "Saved.". No console errors.
- Checks: build, `npm test`, check, types, Pint, Larastan, guard pass.

### T2.3 Items

Built as the card lists: table `items` (spec §9.2 plus `code`), foreign key `journal_lines.item_id → items.id`, enum `ItemType`, `ItemsSeeder` in `SystemDataSeeder` (only adds missing codes, never changes a row), Actions `CreateItem` / `UpdateItem` / `DeactivateItem`, `Item::hasEntries()` (false until T2.7 and T3.4), `ItemPolicy` (`settings.manage`), page 45 `/settings/items` and its menu entry.

Choices and questions:
- **Item codes are not given anywhere.** Seeded codes: `WASTE`, `WOOD`, `PROTEIN`, `OILFAT`. For a new item the Owner types the code (up to 10 capital letters, digits or dashes, unique; it never changes). Please confirm or give the codes.
- **Item names are unique per company** (form check only). The acceptance files find items by name, and two items called "Waste" would confuse users. The spec does not say this.
- **The four system items cannot be changed at all** (name or type). Their ledger accounts are system accounts, which BR-LED-01 says cannot be renamed. The card says "name only"; renaming a system item would leave "Purchases – Waste" on a renamed item.
- **Changing a new item between bought (raw material, fuel) and sold (product)** before its first entry creates the other kind of ledger account and makes the old one inactive (it has no entries). Raw material ↔ fuel keeps its account.
- Words not in the glossary: "New item", "Raw material", "Fuel", "Product", "Code", "Account".
- Tests: the card's four, plus: a new product gets 4020; rename and type change before entries; deactivate; page 45 for owner and refused for accountant; code and name unique.
- Note: PHP page tests need `npm run build` first (CI does this; `check-all.sh` runs the PHP tests before the build). Same question as in T0.1.
- Checks: build, check, types, Pint, Larastan, guard pass; **221 PHP tests pass**.

### T2.4 Parties backend

Built as the card lists: table `parties` exactly per spec §9.2 (plus checks for BR-PTY-10 and the delete trigger), foreign key `journal_lines.party_id → parties.id`, enums `PartyType` and `PartyStatus`, the warning mechanism (`App\Support\Warnings\WarningRequired` rendered as 422 `{warning: {code, message, details}}`, and `Warnings`, which raises unconfirmed warnings and audits `warning_confirmed` after the save), Actions `CreateParty`, `UpdateParty`, `DeactivateParty`, `ActivateParty` (no delete action), `Party::hasEntries()`, `Party::balance()` returning `PartyBalance` (words, absolute amount, side, pending kg; partners also invested, withdrawn, net), PostingService's BR-LED-04 check now also requires the party type to match the account, `StorePartyRequest` / `UpdatePartyRequest`, `PartyPolicy`, and the test helpers `ledgerBalance()` / `partyBalance()` from conventions §11.

Choices and questions:
- **Duplicate check also runs on edit** when the name or mobile changes (BR-PTY-03 says "another active party", for any save). The card lists it only for `CreateParty`.
- **When both mobile and name match**, the warning names the mobile match.
- **Partner balance words:** net above zero is "Net invested", zero is "Settled", below zero (more withdrawn than invested) is "Withdrawn" with the amount. The glossary gives "Invested / Withdrawn / Net invested" but not which one is the headline; please confirm.
- **`ActivateParty` refuses a merged party** with "This party was merged into another party." (not a glossary message; merge comes in T7.4).
- **Field lengths the spec leaves open:** address 1,000 characters, notes 2,000.
- **Validation messages** that are not in the glossary: "Mobile must be 10 to 15 digits.", "This field is only for this party type.", "Enter a number.", "Use at most N decimals.", "Enter a number from A to B." / "greater than A".
- `App\Rules\DecimalString` checks money and percent strings with `BigDecimal`, never floats (conventions §3).
- Found while testing: `WarningRequired` cannot have a property called `$code` (PHP's `Exception` already has one), so it is `$warningCode`; the JSON key is still `code`.
- Tests: the card's nine, plus all five codes, duplicate by name and inactive parties ignored, 422 JSON shape, active again, owner bank change not flagged, type change clears the other type's fields, mobile normalising, buyer/employee/advance words, partner invested/withdrawn/net.
- Checks: Pint, Larastan, guard pass; **237 PHP tests pass**.

### T2.5 Parties UI

Built as the card lists: page 17 `/parties` (search by name, Urdu name, code, mobile; type and status filters; Code, Name, Area, Mobile, Type, Balance in words, Status; 50 rows per page; "Needs review" badge), page 18 `/parties/create` and `/parties/{id}/edit` (fields by type, Urdu name `dir="rtl" lang="ur"`, type locked once the party has entries, make inactive / active again), `WarningDialog` with a `useWarnings()` hook (reads the 422 warning through Inertia's `onHttpException`, "Go back" / "Save anyway" re-sends with the code confirmed), `ConfirmWithReason` (reason of at least 5 characters, buttons "Close" / "Cancel entry"), `PartyPicker`, and `GET /parties/search` (JSON, active only, up to 20, `party.view`).

Choices and questions:
- **PartyPicker is not shadcn Command.** Command needs the `cmdk` package, which no card names (CLAUDE.md: no package without asking). The picker is the kit's dialog plus an input and a list with arrow-key and Enter support, so it behaves the same. Tell me if you want `cmdk` added.
- **Balances on the list come from one grouped query** (`LedgerBalance::partyNets()`), not one query per row.
- **"Profit share (%)" is labelled "Share (%)"**: glossary §5 forbids the word "Profit" on screen, and the guard script enforces it. The database column stays `profit_share_percent` (spec §9.2).
- **Rows open the edit page** until the party profile exists (T2.9).
- Words not in the glossary: "New party", "Search", "All types", "All", "No party found.", "Check before saving" (warning dialog title), "Reason", "Previous" / "Next" / "Page X of Y", field labels taken from spec §9.2 ("Urdu name", "Business name", "Contact person", "NTN", "Credit limit (Rs)", "Payment terms (days)", "Joining date").
- Tests: the card's two, plus Urdu name and code search, at most 20 results, `party.view` needed, list with balance words, filters and paging, the 422 duplicate warning over HTTP then saving when confirmed, accountant add/edit, no access without `party.create`. Front end: `readWarning()` unit tests.
- Checked at 375 px: add party, list with toast "Saved. S-0001", and the duplicate warning dialog. No console errors except the expected 422.
- Checks: build, `npm test` (23), check, types, Pint, Larastan, guard pass; **245 PHP tests pass**.

### T2.6 Opening balances

Built as the card lists: table `opening_balances` (spec §9.3 plus `voucher_no` and common columns; partial unique indexes per money account and per party; checks for one target, amount ≥ 0 and the side), enum `OpeningSide`, PR-17 to PR-22 in `PostingRules` (posting date = `system_start_date`, voucher OPB), `SetOpeningBalance` (create; replace = reverse the old journal on the start date and post the new one; amount 0 removes), `MarkOpeningBalancesComplete` (confirmed figure must equal 3900; sets `opening_balances_completed`; audit `opening_completed`), page 47 with one row per active account and party, side words from glossary §2, the Opening Balance Equity figure and the "Mark complete" confirmation.

Also added:
- `PostingService::reverseId()`: business Actions keep only `journal_entry_id`, and the architecture rule forbids them from reading journal models, so they reverse by id.
- Test helpers `journalLines()` (exact lines with account code, debit, credit, party code, item name) and `acceptance()` (loads `docs/acceptance/*.json`).

Choices and questions:
- **The Opening Balance Equity figure is shown as a credit balance** (credit minus debit) with a minus sign when debits are larger. It is an equity account, not a party, so the "no minus sign" rule does not apply; "Credit"/"Debit" are words glossary §5 keeps off this page.
- **Openings only for active accounts and parties** (BR-GEN-08).
- **The opening record keeps its voucher number** when replaced; amount 0 keeps the row with amount 0 and no journal (nothing is deleted).
- Messages not in the glossary: "Opening balances are marked complete and cannot change.", "Opening Balance Equity is Rs X. Confirm that figure.", "Select an active account.", "Select an active party.", "Select a side for this party."; page words "Date", "Accounts", "Parties", "Balance", "Invested" (partner side).
- Tests: the card's eight (PR-17 to PR-22 exact lines, AT-1 openings → 3900 = 0.00 loaded from `at1.json`, replace reverses, wrong confirmation refused), plus amount 0 removes, side must fit the type, refused after completion, BR-GEN-07, page 47 permission and save.
- Checks: build, check, types, Pint, Larastan, guard pass; **259 PHP tests pass**.

### T2.7 Purchases with fixed rate, payments table, "paid now"

Built as the card lists: tables `purchases` and `payments` with every spec §9.3 column plus the §9.1 transaction columns (unique voucher and idempotency key per company, the §9.7 indexes, checks for kg > 0, "pending ⇔ rate and amount empty", status values, delete trigger; `payments.sale_id` gets its foreign key with the sales table in T3.4), models `Purchase` and `Payment`, enums `RateStatus`, `PaymentDirection`, `ApprovalStatus` (plus `App\Support\EntryStatus` active/cancelled for every transaction table), PR-01 and PR-02, `CreatePurchase` in the conventions §2 order (setup check, date check, idempotency, rules, voucher, save, PR-01, audit, then the "paid now" payment with PAY voucher and PR-02 in the same transaction), `Item::hasEntries()` and `Party::hasEntries()` now see purchases, `POST /purchases` with `StorePurchaseRequest` (UUID `idempotency_key` required), `PurchasePolicy`.

Also added:
- `App\Domain\Payments\Actions\RecordPayment`: saves one payment and its journal inside the caller's transaction. "Paid now", "Received now" (T3.4) and New payment (T3.6) all use it, so the payment rules live in one place. It is an Action because the architecture rule lets only Actions call PostingService.
- `App\Support\EntryChecks`: the field rules every entry shares (active party of the right type, bought/sold item, kg and rate decimals from settings, amount > 0, active money account, "Handled by" employee).
- Test helper `acceptanceWorld()`: creates the accounts, parties and openings of an acceptance file through the real Actions (runner step 2 of `docs/acceptance/README.md`).

Choices and questions:
- **`Party::hasEntries()` also counts payments**, including ones awaiting approval (they have no journal line yet). The card names purchases only; without this a party with only a pending payment could still change type.
- **The "paid now" payment reuses the purchase's idempotency key** (different table, so no clash); a repeated save returns the same purchase and creates no second payment.
- **After saving, `POST /purchases` returns to the previous page** until the purchase pages exist (T2.8).
- Messages not in the glossary: "Select an active supplier.", "Select an active raw material or fuel item.", "Kg must be greater than 0.", "Kg can have at most N decimals.", "Rate is required" (card wording), "Rate must be greater than 0.", "Rate can have at most N decimals.", "Amount must be greater than 0.", "Select an active account.", "Handled by must be an active employee.".
- Tests: the card's ten (AT-1 #1 and #2 with exact journals, read from `at1.json`; supplier and item checks; kg 0 and 4 decimals; browser amount ignored; failed save keeps the voucher number free; setup incomplete; paid now above total → "Advance 500.00"; T-18 double submit) plus rate rules, amount rounding (812.5 × 52.5 = 42656.25), future and pre-start dates, paid-now account and "Handled by" checks, permissions.
- Checks: Pint, Larastan, guard pass; **274 PHP tests pass**.

### T2.8 Purchase pages and attachments

Built as the card lists: table `attachments` (spec §9.5 plus common columns, delete trigger), private disk `attachments` (`storage/app/private/attachments`, not served, 0640/0750), `StoreAttachment` (5 MB, type read from the content, JPG/PNG/WEBP re-encoded with GD so location data is dropped, PDF kept, UUID file name, `replaced_by_id` instead of delete), `GET /attachments/{id}` (checks the user may view the parent entry; `inline` and `nosniff`), `lib/image.ts` (resize to 2000 px, JPEG 0.85; size calculation unit-tested), page 4 `/purchases/create` (mobile-first, fields in spec §11.4 order, one-time key, live total or "Rate pending", "Paid now" section, camera slip photo, "Our weight" only when `plant_has_scale`, "Save" and "Save and add another"), page 3 `/purchases` (spec §11.2 filters, search, totals of active entries, 50 per page, newest first), page 5 `/purchases/{id}` (read-only detail with linked payment and slip photo). Menu: Purchases under Daily entries; "New purchase" in the phone bottom bar.

Choices and questions:
- **The slip photo is sent with the purchase form** and stored inside the same save; no separate upload step.
- **`lib/uuid.ts` falls back to `crypto.getRandomValues()`** when `crypto.randomUUID()` is missing. Browsers give `randomUUID` only on HTTPS or localhost, so testing from a phone on your laptop's network address over plain HTTP would otherwise break every create form. The server still requires a valid UUID.
- **PHP upload limits:** PHP's default `upload_max_filesize` is 2 MB. The browser shrinks photos well below that, but a PDF between 2 and 5 MB would fail before our check. The server PHP settings are in `infra/` (protected). Please check `upload_max_filesize` / `post_max_size` are at least 6 MB there.
- **Attachments are not audited separately**; the purchase's own `created` row covers the save. `attachment` is not in the conventions §7 morph alias list, so it cannot be an audit subject.
- **"Our weight" is shown** in the test settings (`plant_has_scale` = true).
- Words not in the glossary: "Total", "Slip photo", "Reference number", "Select supplier", "Select item", "Select account", "Select employee", "All suppliers", "Voucher or party", "No entries.", "Created by", "Reason", "Kg", "Rate", "Amount", "Item", "Date".
- Tests: the card's six (6 MB refused; .exe refused; .exe renamed .jpg refused; Exif removed from a GD-made JPEG with an inserted APP1 Exif block; download needs `purchase.view`; totals of active entries only), plus PDF kept as received, slip photo sent with a new purchase, list filters and search, page 4 and 5 props, "Save and add another" redirect, page permissions. Front end: resize size calculation, UUID shape.
- Checked by me at 375 px (page 4 filled in, live total Rs 42,656.25, paid now, saved with toast "Saved. PUR-000001", detail page) and at 1280 px (list with totals). No console errors.
- Checks: build, `npm test` (26), check, types, Pint, Larastan, guard pass; **286 PHP tests pass**.

### T2.9 Party profile with khata, trial balance page

Built as the card lists: `PartyKhata::for($party, $from, $to)` returning `KhataResult` (opening balance before `from`, one row per journal line on the party's control account(s) in posting-date and journal order, voucher number and description of the source record, increase / decrease, running balance in words), page 19 `/parties/{id}` (header with code, type, balance in words and pending kg; tabs Details and Khata with a date range; "New purchase" pre-selecting the supplier; Edit), page 36 `/reports/trial-balance` (as-of date, "Show parties", totals and a clear "Balanced" / "Not balanced" label). The trial balance query now fills party code and name when parties are shown. Menu: Reports → Trial balance. The parties list now opens the profile.

Also added:
- `App\Domain\Reports\Queries\EntrySources`: the voucher number, description and link of the record behind each journal, loaded per source type in one query. Each later entry type adds its arm. Descriptions: purchase "Waste 800 kg @ 50", payment "Paid – Cash" / "Received – HBL" plus the reference, opening "Opening balance", and "Reversal – …" for reversal journals.
- `KhataTable` component (spec §15.6) and `Party::balanceOfNet()` (words for any net, used for running balances).
- `NumberFormat::plain()` for kg and rate in descriptions ("800.000" → "800").

Choices and questions:
- **Increase / decrease** follow each type's usual direction: for suppliers and other parties a credit increases what we owe; for buyers and employees a debit increases what they owe us; for partners a credit increases what they invested.
- **The khata shows all history by default**; the opening row appears only when a "from" date is chosen.
- **Tabs are plain buttons**: shadcn Tabs needs `@radix-ui/react-tabs`, a package no card names.
- Words not in the glossary: "Details", "Increase", "Decrease", "Balance", "Opening balance" (glossary has it), "Closing balance", "Show", "As of", "Show parties", "Balanced", "Not balanced", "Account", "Code".
- **Fixed another test of mine (T1.12, BR-SEC-13 sessions):** the test made a random session id and expected it to sort before "someone-else"; about one run in three it did not. The id now starts with "a"; **the assertion is unchanged.** Please review it with the T1.7 one.
- Tests: the card's three (Karim khata after AT-1 entries 1 and 2: two rows and "We owe 18800.00"; opening row before the from date; page totals equal the query totals), plus a partner khata, page 19 props, and `report.view` needed.
- Checks: build, check, types, Pint, Larastan, guard pass; **292 PHP tests pass**.

### T2.10 Slice review with the client (your task) – what I prepared

- `database/seeders/DemoSeeder.php`: loads the AT-1 test data (money accounts, parties, opening balances and the first day's purchases) through the real Actions. Refuses production; refuses a second run. **Not ticked in `docs/tasks.md`**: the review, `docs/feedback/T2.10.md` and the decisions are yours.
- On your laptop: `php artisan migrate:fresh --seed`, then `php artisan db:seed --class=DemoSeeder`, then log in as `test@example.com` / `password`.
- The rate-pending purchase of day 1 is skipped until T3.1 builds rate pending (removed again in T3.1).
- Tests: loads the data (7 parties, PUR-000001 and PUR-000002), refuses production and a second run.

**M2 built (except your review).** Next: M3.

### T3.1 Rate pending and adding the rate later

Built as the card lists: `CreatePurchase` accepts an empty rate (status "pending", no rate, no amount, **no journal**; "Paid now" still posts, so the supplier shows an advance), table `rate_changes` (spec §9.5, `prevent_change()` trigger) and model `RateChange`, `AddPurchaseRate` (only active pending entries; amount = kg × rate; PR-01 dated on the purchase date, or on the day the rate is added when the purchase month is closed, BR-LED-08; `rate_changes` row and audit `rate_added`), pending kg on the party balance, rate-pending rows in the khata (kg, "Rate pending", no amount, balance unchanged), `POST /purchases/{id}/rate` with an "Add rate" dialog on page 5, page 26 `/rate-pending` (age in days, red when older than `rate_pending_age_days`, "Add rate" inline) and its menu entry under Checks.

Choices and questions:
- **Changed one T2.7 test, as the T2.7 card planned:** "BR-PUR-03 the rate is required in this version" now checks that an empty rate saves as "Rate pending". The T2.7 card says the rate is required "until T3.1".
- **The DemoSeeder now loads the day-1 rate-pending purchase too** (the skip from T2.10 is removed).
- **The add-rate date must be a valid entry date** (not in the future, in an open month), checked on the date actually used for posting.
- **Pending kg for lists is loaded in one grouped query** (`Party::pendingKgFor()`).
- Message not in the glossary: "Only an active rate-pending entry can get a rate."; page words "Days", "Party".
- Tests: the card's five (T-05 no journal; T-05 PR-01 and `rate_changes`; AT-1 #4 and #12 → "We owe 38400.00"; BR-LED-08 closed January posts on 05 Feb; BR-CHK-05 8 days flagged, 7 not), plus refused for a fixed entry, paid now on a pending purchase → advance, khata pending row, add-rate route permission and save.
- Checks: build, check, types, Pint, Larastan, guard pass; **303 PHP tests pass**.

### T3.2 Edit and cancel purchases

Built as the card lists: `UpdatePurchase` (every field except the voucher number; party stays a supplier; a posted purchase is reversed on its original posting date and posted again on its new date; a rate-pending one changes without a journal; a changed fixed rate writes `rate_changes` and audit `rate_changed`; audit `updated` with the old and new value of each changed field), optimistic lock (`version` = `updated_at`, mismatch → glossary "Concurrent edit" text, HTTP 409), `CancelPurchase` (reason ≥ 5 characters, journal reversed, row kept with reason, user and time; "Also cancel the linked payment?" cancels the "paid now" payment the same way), both refused in a closed month with the glossary "Closed month" text, routes `GET /purchases/{id}/edit`, `PUT /purchases/{id}`, `POST /purchases/{id}/cancel`, the edit page reusing the New purchase form, and "Edit" / "Cancel entry" on page 5 with `ConfirmWithReason` and the checkbox.

Also added:
- `CancelPayment` (Payments module): reverses and cancels one payment; used here for the linked payment and later by the payment pages (T3.6).
- `App\Support\CancelReason` (BR-GEN-04), `App\Support\ConcurrentEdit` (BR-GEN-09, renders 409 JSON), `App\Domain\Ledger\Journals::postingDate()`.
- `App\Domain\Purchases\PurchaseInput`: the purchase field checks, shared by create and edit (and the model for sales in T3.4).
- The purchase form is now `components/app/PurchaseForm.tsx`, used by `purchases/create` and `purchases/edit`.

Choices and questions:
- **A fixed rate cannot be removed by editing** ("A fixed rate cannot be removed. Enter the correct rate."). `rate_changes.new_rate` cannot be empty (spec §9.5), so going back to "Rate pending" could not be recorded. A pending entry can get its rate by editing too; that writes `rate_changes` with an empty old rate and audit `rate_added`.
- **Editing has no "Paid now" and no photo**: the payment is its own record (edited on the payment pages, T3.6); replacing a slip photo is not in this card.
- **Cancel reverses on the original posting date** (that month must be open).
- Messages not in the glossary: "A cancelled entry cannot be edited.", "This entry is already cancelled.", "Give a reason of at least 5 characters.".
- Tests: the card's seven (edit kg: exact original, reversal and new journals; cancel reverses and keeps the row; cancel without the payment → "Advance 40000.00"; 4-character reason refused; stale version refused, also as HTTP 409; closed month refuses edit and cancel; fixed rate change → `rate_changes`), plus cancel with the payment, pending edit without journal, party must stay a supplier, route permissions.
- Checks: build, check, types, Pint, Larastan, guard pass; **314 PHP tests pass**.

### T3.3 Purchase warnings and weight-slip settings

Built as the card lists: `GET /purchases/last-rate` (`{rate, date}` of the latest active fixed purchase for that supplier and item, or `null`), shown under the rate field as "Last rate: Rs 52.00 on 06-01-2026" and never filled in; unusual rate (BR-PUR-06: weighted average Σ amount ÷ Σ kg of the last `unusual_rate_lookback` active fixed purchases of the item, all suppliers, by date then id, excluding the entry being edited; warning above `unusual_rate_percent`; glossary text with whole percent and higher/lower) on create, edit and add rate; duplicate entry (BR-PUR-08); slip rules (BR-PUR-07: slip number and photo per settings, "Our weight" refused without a scale); `warning_confirmed` audit for every confirmed warning.

Also added: `App\Support\EntryWarnings` (last rate, average, unusual rate, duplicate, slip rules) written for both purchases and sales, so T3.4 reuses it; `Decimal::toScale()`.

Choices and questions:
- **On edit, the unusual-rate check runs only when the rate or the item changed.** Otherwise editing a note on an entry whose unusual rate was already confirmed would ask again.
- **The slip photo rule applies to new entries only**: the edit form has no photo field, and the photo is not removed by editing.
- **The average in the message uses `rate_decimals`** ("Rs 50.00").
- **Two earlier tests of mine changed because of this task's new rules** (expected, not weakened): T2.8's "Save and add another" test saved the same purchase twice, which is now a duplicate warning, so the second save uses 11 kg instead of 10; no assertion changed. My own weighted-average test confirms the warnings its set-up purchases raise.
- Messages not in the glossary: "Weight slip number is required.", "Slip photo is required.", "Our weight is not used: the plant has no scale."; hint "Last rate: …" is from spec §11.4.
- Tests: the card's seven (last rate 52.00 after AT-1 #10; 70 vs 50 warns, confirmed saves and logs; 60 vs 50 silent; last 5 only; duplicate; slip number required; our weight refused) plus "lower", add-rate warning, slip photo required, `null` when there is no last rate.
- Checks: build, `npm test`, check, types, Pint, Larastan, guard pass; **324 PHP tests pass**.

### T3.4 Sales

Built as the card lists: table `sales` (same shape as purchases; `payments.sale_id` now has its foreign key), model `Sale`, PR-04 and PR-05, `CreateSale`, `AddSaleRate`, `UpdateSale`, `CancelSale` mirroring the purchase Actions with a buyer and a product item (BR-SAL-01), kg as the buyer's weight (BR-SAL-05), "Received now" as a received payment with voucher RCV that never needs approval (BR-SAL-07), the credit-limit warning (BR-SAL-06), and the purchase rules for sales (last rate, unusual rate over all buyers, duplicate, slip rules, rate pending, edit, cancel, closed months, idempotency key). Pages 6, 7 (mobile-first) and 8, "New sale" in the phone bottom bar and on buyer profiles, sales on the rate-pending page with a type filter, sales in `Item::hasEntries()`, `Party::hasEntries()`, pending kg and the khata.

How it is built (to keep the two sides in step):
- The purchase form is now `components/app/EntryForm.tsx` with a `kind` of purchase or sale; only labels, party type, item list, routes and "Paid now" / "Received now" differ.
- The Sale Actions, `SaleInput`, `SaleController` and its requests were made from the purchase ones and then changed where sales differ; the shared rules sit in `EntryChecks`, `EntryWarnings` and `RecordPayment`.
- `PostingRules` has one private helper for payment and receipt journals; PR-02 and PR-05 use it, and T3.5 adds the rest.

Choices and questions:
- **The credit-limit check counts money received with the sale.** "Balance after this sale" = what the buyer owes now + sale amount − "received now". Without that, a sale paid in full on the spot would still warn. On an edit it uses the change in amount; on "Add rate" the new amount.
- **Rate history and corrections share one `rate_changes` table** (spec §9.5); the model stays in the Purchases module and is used for both.
- Messages not in the glossary: "Select an active buyer.", "Select an active product.". The credit-limit text is glossary §4.
- Tests: the card's seven (AT-1 #5 exact journal; AT-1 #6 with RCV-000001 and "Settled"; supplier refused; Waste refused; credit limit 50,000 vs 63,000 warns, confirmed saves; received now never needs approval for the accountant; T-05/T-06 for sales; T-18 double submit) plus a cancelled sale's receipt left as an advance and pages 6, 7, 8 and the rate-pending filter.
- Checks: build, check, types, Pint, Larastan, guard pass; **335 PHP tests pass**.

### T3.5 Payments and receipts (backend)

Built as the card lists: PR-02, PR-03, PR-05, PR-06, PR-12, PR-13 with the card's mapping (supplier 2000, buyer 1200, other 2100, employee 1300), `CreatePayment` (voucher PAY / RCV, idempotency key), `UpdatePayment` (reversal and repost; the direction may change and the voucher number stays), `CancelPayment` (from T3.2), checks (no partner, amount > 0, active account, "Handled by" employee), method "Cash" / "Bank transfer" from the account type, warning `missing_reference` for a bank account without a reference, information `overpayment_info` (flash toast, never blocks), `GET /payments/balance-preview` (balance now and after, in words, plus the information text), `POST /payments`, `PUT /payments/{id}`, `POST /payments/{id}/cancel`, `PaymentPolicy`. All payments are "not required" for approval until T3.7.

Also added: `PaymentInput` (shared checks for create and edit), `PaymentBalance` (net, after, overpayment), `LedgerBalance::journalNet()` (so an edit's preview can leave out the payment being edited).

Choices and questions:
- **"Owed in that direction" (BR-PAY-11):** paying: what we owe the party (their credit balance); receiving: what they owe us (their debit balance). The extra above that is the advance in the message.
- **The overpayment information is worked out before the save** and shown as an info toast after it, together with the "Saved." toast.
- **`photo` is accepted as a field shape now**; storing the receipt photo comes with the payment page (T3.6).
- Messages not in the glossary: "Choose Paid or Received.", "Select an active party. Partner money is entered on the partner page.", "Only an active payment can be edited."; `missing_reference` and `overpayment_info` texts are glossary §4.
- Tests: the card's list (AT-1 #3, #13, #14, #18; one test per mapping row with exact lines (8); partner refused; "Handled by" supplier refused; bank without reference warns, cash does not; overpayment information and saves; direction edit keeps PAY-000001; T-18) plus the balance preview.
- Checks: Pint, Larastan, guard pass; **354 PHP tests pass**.

### T3.6 Payment pages

Built as the card lists: page 10 `/payments/create` (mobile-first; "Paid" / "Received" as two large buttons, red and green with the word; date; party picker for every type except partner; the balance now and after under the amount, from the server, with the overpayment information; account with "Cash" / "Bank transfer"; reference shown for bank accounts; handled by; photo; note), page 9 `/payments` (direction filter plus the spec §11.2 filters and search; totals of paid and of received, active and not awaiting approval), page 11 `/payments/{id}` (every field, linked purchase or sale, photo, Edit and Cancel entry), the edit page (same form with `version`), "New payment" on party profiles and in the phone bottom bar. The receipt photo is stored by `CreatePayment` (kind `receipt_photo`).

Choices and questions:
- **Totals on page 9 are shown as two figures, Paid and Received**, because adding payments and receipts together means nothing.
- **After saving, the detail page opens** (no "Save and add another" on page 10: spec §11.8 lists no buttons).
- Words not in the glossary: "Balance now", "Balance after", "Paid and received", "All parties", "Select party", "Photo", "Direction".
- Checked at 375 px: Karim, 20,000 paid → "We owe Rs 18,800.00" now, "Advance Rs 1,200.00" after, and the information line. No console errors.
- Tests: the card's two (list filters by direction with totals; detail shows the linked purchase and the linked sale) plus pre-selected party and redirect to the detail page, and page permissions.
- Checks: build, check, types, Pint, Larastan, guard pass; **358 PHP tests pass**.

### T3.7 Payment approvals

Built as the card lists: `PaymentApproval::statusFor()` used by `CreatePayment` and "Paid now" (a "paid" payment by a user without `payment.approve` waits when the amount ≥ `payment_approval_limit`, or when the party has no earlier posted paid payment and the amount ≥ `new_party_first_payment_limit`; receipts never wait), a waiting payment has no journal and changes no balance, the glossary "Approval pending" message, `ApprovePayment` (posts on the entry date, or today when that month is closed; audit `approved`), `RejectPayment` (reason, no journal, stays visible; audit `rejected`), page 27 `/approvals` (oldest first, party balance, Approve / Reject with reason; `payment.approve` only; menu under Checks), and waiting payments greyed in the khata, outside the balance.

Choices and questions:
- **Editing a waiting payment checks the approval rules again** with the new figures; if it no longer needs approval (or the Owner edits it) it is posted. An approved payment stays approved.
- **A rejection needs a reason of at least 5 characters**, like a cancel (BR-GEN-04). The spec says "with a reason" without a length.
- **"Paid now" that waits** shows the purchase's "Saved. PUR-…" toast plus an information toast with the "Approval pending" text.
- Words not in the glossary: "No payments are awaiting approval.", "Balance now", the khata suffix "– Awaiting approval", "Only a payment awaiting approval can be approved." / "rejected.".
- Tests: the card's six (T-08 accountant 60,000 pending without journal; owner posts at once; first-payment rule with limit 0, second one after approval not pending; rejected never posts; approval in a closed month posts on the approval date; AT-4 read from `controls.json` step by step) plus receipts never wait, a large "paid now" waits, khata row and message, page 27 permission.
- Checks: build, check, types, Pint, Larastan, guard pass; **368 PHP tests pass**.

### T3.8 Negative cash warning and shared dialogs

Built as the card lists: `App\Domain\Checks\CashGuard::check()` (cash accounts only; balance, minus the record's own journal on that account when editing, minus the outflow; below zero → warning `negative_cash` with the glossary text), called for payments paid from cash (create and edit) and "Paid now" from cash. M4 tasks call it for expenses, partner withdrawals and transfers from cash. Every page already uses the shared `WarningDialog` and `ConfirmWithReason`.

Choices and questions:
- **`CashGuard::check()` takes the save's `Warnings` object as its first argument** (the card's signature has no place for the user's confirmations or the audit row).
- **A payment that waits for approval is not checked**: it takes no money out until the Owner approves it.
- **Two T2.7 tests confirm the new warning in their input**: their test cash account starts empty, so "paid now" from it now warns. Their assertions did not change.
- Tests: the card's three (5,000 from 3,000 warns, confirmed saves; banks never warn; an edit counts only the difference) plus paying exactly the balance does not warn.
- Checks: Pint, Larastan, guard pass; **372 PHP tests pass**.

### T3.9 Change history and journal lines on detail pages

Built as the card lists: `ChangeHistory` (query in Reports and React component) on the purchase, sale and payment detail pages and as the third tab of the party profile: date and time, user, action, each changed field "old → new" (party, item and account ids shown by name), and the reason; rate history comes from `rate_changes`. "Journal lines" section (journal number, posting date, account code and name, party, debit, credit, "reverses JV-…") on the three detail pages, **sent by the server only to users with `audit.view`**.

Choices and questions:
- **Rate rows come from `rate_changes` only**; the matching `rate_added` / `rate_changed` audit rows are left out of the list so each rate change appears once.
- **Newest first; within one save, the audit row comes before its rate row.** Tests freeze the clock, so they move it forward between saves.
- Words not in the glossary: "Change history" (spec §11.11 tab name), "Journal lines", "Edited", "Created", "Rate added", "Rate changed", "Marked reviewed", "Bank details changed", "Warning confirmed", "No changes.", "Reason:", "Posting date", "Journal", "reverses".
- Tests: the card's two (the accountant gets no `journal` prop, the owner gets the exact lines; history shows old and new values after an edit) plus a party's bank detail change in its history.
- Checks: build, check, types, Pint, Larastan, guard pass; **375 PHP tests pass**.

**M3 built.**

### T4.1 Expense categories

Built as the card lists: table `expense_categories` (spec §9.2; name unique per company), model, `CreateExpenseCategory` (ledger account from 6000, expense, debit, same name), `UpdateExpenseCategory` (name, quick button, order; renames the ledger account), `DeactivateExpenseCategory`, `TestExpenseCategoriesSeeder` (the proposed O-11 list in order, quick buttons on Wages, Food, Tea, Vehicle Fuel, Transport/Freight; tests and local only, refuses production; called by the local `DatabaseSeeder`), **no production seeder**, page 44 `/settings/expense-categories` and its menu entry.

Choices and questions:
- **Category name length 100** (not given in the spec).
- **The test seeder is not run before every test** (only `SystemDataSeeder` and the test settings are, conventions §11), so "first category = 6000" holds; tests that need the list seed it.
- Words not in the glossary: "New category", "Quick", "Order", "Quick button on Quick expense".
- Tests: the card's two here (6000 then 6001; rename renames the account) plus inactive, the seeder list and quick buttons (twice, no duplicates), page 44 permission and unique names. "Inactive category cannot be used" is tested with expenses in T4.2.
- Checks: build, check, types, Pint, Larastan, guard pass; **380 PHP tests pass**.

### T4.2 Expenses (backend)

Built as the card lists: table `expenses` (spec §9.3, transaction columns, checks: amount > 0, one link at most, delete trigger), PR-09 (Dr category / Cr money account), PR-10 (Dr category / Cr the party's control account with the party), PR-11 (Dr category / Cr 1300 Employees with the employee), `CreateExpense`, `UpdateExpense` (version check, reversal + repost, audit of changed fields only), `CancelExpense` (reason ≥ 5), shared `ExpenseInput` checks, `CashGuard` when paid from cash, voucher `EXP-`, idempotency key, receipt photo as attachment kind `receipt_photo`, `ExpensePolicy`, routes `POST /expenses`, `PUT /expenses/{expense}`, `POST /expenses/{expense}/cancel` (pages come in T4.3).

Choices and questions:
- **A party on a "Paid" expense must be a supplier, other party or employee** (the spec says "party optional" without types). A buyer or partner is refused. Tell me if a buyer should be allowed.
- **"Unpaid" and "Paid by employee" refuse a money account** ("No account is used for this payment mode.") instead of ignoring it, so a wrong form never posts a hidden amount.
- **The party limit (BR-EXP-03) is "amount ≥ limit"**, as the card writes; 9,999.99 under a 10,000 limit needs no party.
- **Editing may change the payment mode** (it is a reversal and a new journal like any edit).
- Words not in the glossary: "Write a note of at least 3 characters.", "Choose Paid, Unpaid or Paid by employee.", "A party is required for an expense of this amount.", "Select the party for this payment mode.", "Select an active employee.", "Select an active supplier, other party or employee.", "No account is used for this payment mode.", "Link the expense to one purchase or one sale, not both.", "Select an active purchase.", "Select an active sale.", "Select an active category.".
- **The acceptance runner (`tests/Support/AcceptanceRunner.php`) is left for T4.8**, as the acceptance README says; these tests read the AT-1 and AT-2 entries directly, like T2.7 to T3.9.
- Tests: the card's ten (AT-1 #7–#9, #19, #21 journals and category totals; AT-1 #20 edit with one reversal and the audit row from `at1.json`; AT-2 #8 unpaid transport linked to the sale; PR-11 employee owes 3,800; empty and 2-character notes; party at the limit; unpaid to a buyer; both links; same key twice) plus inactive category, paid by employee needs an employee, account rules per mode, negative cash warning, cancel.
- Checks: Pint, Larastan, guard pass; **395 PHP tests pass**.

### T4.3 Expense pages

Built as the card lists: page 13 `/expenses/create` "Quick expense" (mobile-first: date, quick category buttons by `sort_order` above the full category list, amount with the decimal keypad, note, Paid / Unpaid / Paid by employee, account when Paid, then "More" with party, link to purchase or sale, handled by, receipt photo; the party moves out of "More" and becomes required when the mode needs it or the amount reaches the limit), page 12 `/expenses` (date range, category, party, status, search by voucher, note or party; note shown in each row; total of active entries), page 14 `/expenses/{id}` (every field, Edit, Cancel entry, change history, journal lines for the Owner), the edit page, "Expenses" in Daily entries and "Quick expense" in the phone bottom bar.

Choices and questions:
- **The server sends the BR-EXP-09 defaults** (`defaults`: today, "paid", the active cash account with the lowest ledger code), so the card's test checks them on the server.
- **"Link to purchase or sale" is a voucher number field** (PUR-… or SAL-…) checked by a small lookup (`GET /expenses/link-lookup`) that only finds active entries. A list of every purchase and sale would be too long on a phone. Tell me if you want a picker instead.
- **"Save" opens the expense; "Save and add another" opens a fresh form** (new idempotency key), like purchases and sales. Spec §11.9 does not list the buttons.
- **The party filter on the list shows suppliers, employees and other parties** (the types an expense can have).
- **Change history now shows the category name and the linked voucher number** instead of ids.
- **One earlier test of mine was flaky**: `ItemsTest` "a deactivated item is not active any more" read rows without an order; I added `orderBy('id')` to its query. Its expected list did not change.
- Words not in the glossary: "Quick category", "More", "Party (optional)", "Link to purchase or sale", "Find", "No active purchase or sale has this voucher number.", "All categories", "Voucher, note or party", "Total", "Payment mode", "Receipt photo".
- **Timed check (card's "Done when")**: an automated phone-size run (375 px, touch) opened the page, tapped Tea, typed 150 and a note, and saved in **0.8 seconds** (machine time). A real check by hand on a phone is still needed; please time it when you test locally.
- Tests: the card's one (defaults today, Paid, first active cash account, never a bank, the next one when the first is inactive) plus the list's category filter and note, detail with link, history and Owner-only journal lines, the link lookup, "Save and add another", page permissions; front-end test for the exact amount-versus-limit check.
- Checks: build, check, types, front-end tests, Pint, Larastan, guard pass; **401 PHP tests pass**.

### T4.4 Partner money

Built as the card lists: table `partner_transactions` (spec §9.3, transaction columns, type investment or withdrawal, amount > 0, delete trigger), PR-14 (Dr money account / Cr 3000 with the partner), PR-15 (Dr 3100 with the partner / Cr money account), `CreatePartnerTransaction`, `UpdatePartnerTransaction` (reversal + repost), `CancelPartnerTransaction`, party must be an active partner (BR-PTN-01), `partner.manage` to record and `partner.view` to see (BR-PTN-03), `CashGuard` on withdrawals from cash, voucher `PTN-`, idempotency key. Pages 21 `/partners` (filters, totals invested and withdrawn), 22 `/partners/create`, 23 `/partners/statement` (R-09: invested, withdrawn, net invested per partner for a date range, from the journal lines on 3000 and 3100, so opening capital counts as invested and cancelled entries cancel out). "Partners" in the People menu. No profit anywhere (BR-PTN-04).

Choices and questions:
- **No detail page exists for partner entries** (spec §11.1 lists only pages 21 to 23), so **Edit** and **Cancel entry** are buttons on each row of page 21, and Edit opens page 22 filled in (`/partners/{id}/edit`). Rows do not open a detail page (spec §11.2 says they should; there is none to open). Tell me if you want a detail page.
- **The statement's default range is the system start date to today**, so the opening capital is included by default.
- **A deposit always counts as investment** (O-23: no loan type).
- Khata lines of a partner entry show "Investment – HBL" or "Withdrawal – Cash". Expense khata lines now link to the expense page.
- Words not in the glossary: "Select an active partner.", "Choose Investment or Withdrawal.", "Investment", "Withdrawal" (glossary has "Partner investment" / "Partner withdrawal"), "New partner entry" (spec page name), "Partner statement" (spec page name), "Investments and withdrawals", "All partners", "Voucher or partner".
- Tests: the card's five (AT-1 #15 and #16 exact journals; R-09 from `at1.json` expected partners; accountant 403 on create, save, statement and list; same key twice) plus party must be a partner, negative cash warning on a cash withdrawal, edit and cancel, the list totals.
- Checks: build, check, types, front-end tests, Pint, Larastan, guard pass; **410 PHP tests pass**.

### T4.5 Transfers

Built as the card lists: table `transfers` (spec §9.3, transaction columns, from ≠ to and amount > 0 also checked in the database, delete trigger), PR-16 (Dr "to" account / Cr "from" account), `CreateTransfer`, `UpdateTransfer` (reversal + repost), `CancelTransfer`, both accounts active and different (BR-TRF-01), amount > 0 (BR-TRF-02), `CashGuard` when "from" is cash, voucher `TRF-`, idempotency key. Pages 15 `/transfers` (date range, account, status, search by voucher or reference; total of active transfers) and 16 `/transfers/create`; "Transfers" in Daily entries.

Choices and questions:
- **Same as partner money: no detail page exists** (spec §11.1), so Edit (page 16 filled in, `/transfers/{id}/edit`) and Cancel entry are on each row.
- **The account filter matches either side** of the transfer.
- Words not in the glossary: "Choose two different accounts.", "New transfer" (spec page name), "From account", "To account", "All accounts", "Voucher or reference".
- Tests: the card's four (AT-1 #17 exact journal; same account refused; only asset accounts touched, so no income or expense; same key twice) plus negative cash warning, edit and cancel with balances, pages for the accountant and 403 without permission.
- Checks: build, check, types, Pint, Larastan, guard pass; **417 PHP tests pass**.

### T4.6 Agreed adjustments

Built as the card lists: table `adjustments` (spec §9.3, transaction columns; database checks: amount > 0, reason in the four values, exactly one of purchase or sale matching `target_type`; delete trigger), PR-07 (Dr 4900 / Cr 1200 with the buyer), PR-08 (Dr 2000 with the supplier / Cr 5900), `CreateAdjustment` (active entry with a fixed rate, party copied, amount ≤ amount − other active adjustments, reason and note required, date on or after the entry date and in an open month; the entry row is locked so two saves at once cannot pass the maximum), `CancelAdjustment` (reason; no edit action), `CancelPurchase` / `CancelSale` refuse with "Cancel the agreed adjustments first.", voucher `ADJ-`, idempotency key. UI: "Add agreed adjustment" dialog on purchase and sale detail pages with the maximum shown; "Agreed adjustments" section listing them with Cancel entry; khata rows "Agreed adjustment – quality" (linking to the purchase or sale).

Choices and questions:
- **Editing a purchase or sale that has active adjustments**: the spec says nothing. I refuse the edit ("Cancel the agreed adjustments first.") only when it would change the party (BR-ADJ-02 would break) or bring the amount below the adjustments (BR-ADJ-03 would break). Other edits (date, note, a higher amount) still work. Tell me if every edit should be refused instead.
- **The reason is shown in lower case in khata rows** ("Agreed adjustment – quality"), exactly as the card writes it.
- **"Cancel entry" stays visible on a purchase or sale with adjustments**; pressing it shows the refusal. Hiding it would hide why it cannot be cancelled.
- Words not in the glossary: "Add agreed adjustment", "Agreed adjustments", "What was agreed", "Maximum", "Select reason", "Quality", "Weight", "Freight", "Other", "Only an active entry with a fixed rate can be adjusted.", "The date cannot be before the entry date.", "The most that can be adjusted is Rs …", "Choose quality, weight, freight or other.", "Write what was agreed.", "Cancel the agreed adjustments first." (card text).
- Tests: the card's six (AT-2 #7 journal, ABC "Owes us" from `at2.json`, sale row still 63,000.00; PR-08 journal; above the remaining refused; rate pending refused; sale with an adjustment cannot be cancelled; same key twice) plus purchase cancel refused, reason/note/date rules, edit guard, detail page props, khata text, 403 without permission.
- Checks: build, check, types, Pint, Larastan, guard pass; **427 PHP tests pass**.

### T4.7 Rate corrections for closed months

Built as the card lists: table `rate_corrections` (spec §9.3 columns plus company, users and times; difference ≠ 0 and exactly one of purchase or sale checked in the database; delete trigger), PR-26 (purchase: increase Dr item account / Cr 2000; decrease the other way) and PR-27 (sale: increase Dr 1200 / Cr item sales account; decrease the other way), `CreateRateCorrection(User, Purchase|Sale, newRate, date, reason)`: only for an active, fixed-rate entry in a closed month; date in an open month and not before the entry; difference = new amount − effective amount (original plus earlier corrections); posts only the difference on the correction date; never edits the original row; voucher `RCO-`; writes `rate_changes` (with the reason) and audit. On the detail page of an entry in a closed month, Edit and Cancel entry are replaced by **Correct rate**; a "Rate corrections" section lists them. Khata row: "Rate correction PUR-000001 – 50 → 48".

Choices and questions:
- **The action lives in `App\Domain\Purchases\Actions`** (with the sales case in the same class): the architecture test lets only listed domains call `PostingService`, and a new `RateCorrections` domain is not on the list (tests/Arch is protected). Rate history (`rate_changes`) already lives there.
- **A correction's reason needs at least 5 characters**, like a cancel reason (the spec says "required").
- **A correction that gives the same amount is refused** ("The new rate gives the same amount."). This also stops a double click from posting twice, since the card asks for no idempotency key on this table.
- **Corrections cannot be cancelled** (the spec gives them no status). A wrong correction is fixed by another correction.
- **Agreed adjustments use the corrected amount** as their base (BR-ADJ-03 "linked amount" read as the amount after corrections).
- Words not in the glossary: "Correct rate", "Rate corrections", "New rate", "New amount", "Only an active entry with a fixed rate can be corrected.", "This entry is in an open month. Edit it instead.", "The new rate gives the same amount.".
- Tests: the card's five (AT-5 read from `controls.json` step by step with `closeMonthForTest`, the cash count step skipped until T5.1; PR-26 increase; PR-27 sale decrease; second correction uses the corrected base; open-month entry refused) plus the detail page props, khata text, the reason rule and 403.
- Checks: build, check, types, Pint, Larastan, guard pass; **433 PHP tests pass**.

### T4.8 Acceptance runner, AT-1 and AT-2

Built as the card lists: `tests/Support/AcceptanceRunner.php` per `docs/acceptance/README.md` (freezes today; creates users, accounts, parties and openings and marks them complete; runs every entry through the real Actions as the owner or the accountant; keeps records by `ref` and `no`; never confirms a warning; asserts every expected key: money accounts, parties with words, amount and pending kg, partners, ledger accounts with `money:` and `category:` keys, both trial balance totals, income vs spending, kg by item, vouchers including `.paid_now` / `.received_now`, audit rows, reversal journals, rate changes). `tests/Acceptance/AT1Test.php` and `AT2Test.php` only load, run and assert.

Choices and questions:
- **Income vs spending is checked from ledger account types** (income = Σ credit − debit over income accounts; spending = Σ debit − credit over expense accounts; result = income − spending; pending kg = active rate-pending kg in the range), as the card says. The R-08 report check and the `insights` key are listed in the runner's `SKIPPED` constant ("enabled in T6.7" / "enabled in T7.5"). I put the skips in the runner, not in `tests/Acceptance/`, because files there become protected once they exist; T6.7 and T7.5 can then switch them on without a `[spec-change]`.
- **`phpunit.xml` gets an `Acceptance` test suite and `tests/Pest.php` applies the feature-test setup to `tests/Acceptance`**; without this `php artisan test` (and CI) would not run AT-1 and AT-2.
- **Partner figures are read for the system start date to today.**
- Checked that the runner fails when one entry is left out (AT-1 failed on `money_accounts.cash`), then removed that change.
- Checks: **`bash scripts/check-all.sh` → ALL CHECKS PASSED** (435 PHP tests with AT-1 and AT-2, Pint, Larastan, build, lint, types, front-end tests, guard, secret scan, package audits).

**M4 built. From here AT-1 and AT-2 run in every task.**

### T5.1 Company settings page

Built as the card lists: page 42 `/settings` (`settings.manage`) with every key of spec §9.6 except `opening_balances_completed`, in the card's groups (Company, Limits, Formats, Weight, Alerts, Security); each field shows its OPEN item ID in small grey text; a "Setup checklist" box (missing required settings, opening balances status, at least one active cash account); saving goes through `UpdateCompanySettings` → `Settings::set()` (audited, one transaction for the whole form); `system_start_date` is read-only once any journal exists; `kg_decimals`, `rate_decimals` and `amount_rounding` are read-only once any purchase or sale exists (also refused on the server). Turning on "Owner two-step login required" while the Owner has none shows a confirmation that they will be sent to set it up. "Company settings" added to the Settings menu.

Choices and questions:
- **`/settings` used to redirect to the profile page** (starter kit). Spec §11.1 gives `/settings` to page 42, so the redirect is gone; the profile, security and appearance pages keep their own addresses (user menu → Settings still opens the profile).
- **The page lives at `pages/company-settings/index.tsx`**, so it uses the normal app layout, not the personal settings layout.
- **A locked key that is sent with the same value is not an error**, so the whole form can still be saved; a changed locked key refuses the whole save (nothing is half-saved).
- **An empty field is left as it is**: a saved value cannot be cleared from this page (BR-GEN-07 would then block every entry).
- `company_name` and `insights_period_months` are not OPEN items, so they show no ID.
- Words not in the glossary: "Company settings", "Setup checklist", "All required settings are filled.", "Missing:", "Opening balances: complete / not complete", "Cash account: present / add at least one", "Not set", "Locked after the first journal.", "Locked after the first purchase or sale.", "Settings saved.", "Two-step login for owners", the confirmation sentence, and the choice labels ("Whole rupee", "Two decimals", "Lakh (1,00,000)", "International (100,000)").
- Tests: the card's four (accountant 403 on view and save; start date locked after the first journal; rounding settings locked after the first purchase; every change audited with old and new values) plus the page props and a wrong value refused.
- Checks: build, check, types, Pint, Larastan, guard pass; **441 PHP tests pass** (AT-1, AT-2 included).

### T5.2 Users page and account safety commands

Built as the card lists: page 43 `/settings/users` (`users.manage`): name, e-mail, role, status, two-step login on/off, last login (from the audit log), "Temporary password" marker; add user (name, e-mail, role, temporary password), change role, reset password, disable / enable; no delete route. Passwords use `Password::defaults()` only. Actions `CreateUser`, `UpdateUserRole`, `ResetUserPassword`, `DisableUser`, `EnableUser` (audits `user_created` / `user_changed`; create and reset set `must_change_password`; disable, role change and reset end the user's sessions and audit `sessions_ended`); `Owners::assertNotLast()` refuses disabling or demoting the last active Owner ("At least one active owner is required."). Commands `zb:create-owner`, `zb:reset-password {email}`, `zb:reset-two-factor {email}`, each audited as `server_command` with no user and the target user id in `new_values`. "Users" added to the Settings menu.

Choices and questions:
- **`server_command` rows store `{command, user_id}`**, not the e-mail, to keep personal data out of `new_values` (the auditable already points at the user).
- **`zb:create-owner` sets no e-mail verification** (BR-USR-06: there is none) and refuses with "An Owner already exists. Add more users on the Users page." (wording mine).
- **The temporary password from `zb:reset-password` is `Str::password(16)`** (letters, numbers, symbols), so it passes the production rule.
- **A small `App\Domain\Companies\SystemCompany` class** finds the seeded company, so the "system data is missing" test can replace it instead of deleting the company (deleting it would mean bypassing the delete trigger).
- **The production-password test checks the form request rules directly** with `app()['env'] = 'production'`: a full HTTP request in production mode is redirected before validation, so it could not show the error.
- **The owner may reset their own password or change their own role on this page** (with the last-owner rule); the spec does not forbid it.
- Words not in the glossary: "Users", "Add user", "E-mail", "Role", "Owner", "Accountant", "Status", "Active", "Disabled", "Two-step login", "On", "Off", "Last login", "Temporary password", "Reset password", "Disable", "Enable", "User … added.", "Saved.", the reset dialog sentence, and the command messages.
- Tests: the card's twelve (last owner; no delete route; production password without a symbol; disabled login refused; sessions end on disable, role change and reset with `must_change_password`; create-owner creates, refuses with an owner, refuses without system data, is audited; reset-password prints once and forces a change; reset-two-factor clears it; unknown e-mail fails) plus page props and 403, add user, enable.
- Checks: build, check, types, Pint, Larastan, guard pass; **458 PHP tests pass**.

### T5.3 Permission tests across every route

Built as the card lists: `tests/Feature/Permissions/RoutePermissionTest.php` with a dataset of every application route mapped to the permission it needs (spec §7). It fails when a route is missing from the dataset (or the dataset names a route that does not exist). Each route is called with real records for its parameters: the accountant gets 403 on every owner-only route, and the owner never gets 403.

Choices and questions:
- **Every application route now also carries `->can('<permission>')`** in `routes/web.php`. Without it, an owner-only save with a form request (for example `POST /settings/users`) answered the accountant with a validation error instead of 403, because Laravel validates the form before the controller's own check. Nothing could be saved either way, but now the permission is checked first everywhere. Controllers keep their policy checks (company of the record). A mutation check (removing one `->can`) makes T-09 fail.
- **Not classified** (card: login, logout, assets, the kit's profile routes): home redirect, login, logout, profile, security, appearance, password and two-factor routes, plus unnamed development-tool and health-check routes.
- **Three routes need no single permission**: dashboard (any user), rate pending (purchase.view or sale.view, checked in the controller), attachments (the attached record's own permission).
- **Finding for T8.1**: the route list shows Inertia's development tools routes (`_inertia/devtools/...`) registered in this environment. I will check in the security task that they are off in production.
- Tests: T-09 (every route classified; accountant 403 on owner-only routes and owner never 403, 215 assertions) plus the dataset agrees with the role matrix.
- Checks: types, Pint, Larastan, guard pass; **461 PHP tests pass**.

### T5.4 Party review and bank-change alerts

Built as the card lists: `ReviewParty(User owner, Party, what)` with `new_party` / `bank_change` (clears the flag, sets `reviewed_by` / `reviewed_at`, audits `reviewed`), route `POST /parties/{party}/review` (`party.review`, owner only); `App\Domain\Alerts\AlertProvider` (key, count, route, label, severity) and the `Alerts` registry with `NewPartiesToReview` and `BankChangesToReview` (owner only); on the party profile a yellow box "Needs review" and "Bank details changed" with each field old → new and "Mark reviewed" for the Owner. The party list takes `review=new` / `review=bank` so each alert opens the matching parties.

Choices and questions:
- **An alert the user may not see counts 0, and the registry lists only alerts with a count above 0.** This keeps the card's interface (no extra "visible to" method); the dashboard (T5.6) shows only alerts that need action.
- **The bank change shown is the latest `bank_details_changed` audit row** for the party.
- `parties.review` is added to the T-09 route list.
- Words not in the glossary: "New parties to review", "Bank detail changes to review" (spec §11.3 wording), "Bank details changed", "Mark reviewed", "Marked reviewed.", "Bank name", "Account title", "Account number", "Choose what was reviewed.".
- Tests: the card's two (owner marks reviewed, accountant 403; alert counts drop after review) plus the accountant sees no review alerts, and the profile box with old and new bank details.
- Checks: build, check, types, Pint, Larastan, guard pass; **465 PHP tests pass**.

### T5.5 Audit log page

Built as the card lists: page 41 `/audit-log` (`audit.view`): filters user, action, date range, record type, voucher or party search; columns time (Asia/Karachi), user, action, record (type and voucher or name, linked to its page for purchases, sales, payments, expenses and parties), changes (old → new), reason, IP; 50 per page, newest first. "Audit log" added to the menu (its own group, spec §15.1).

Choices and questions:
- **Default range is the current month** (spec §11.2 list rule).
- **Search** matches the record's voucher number, a party's name or code, and the entries of that party.
- **Setting rows show the setting name as the field** ("advance age days: 30 → 45"); transfer rows now show account names instead of ids (also in change history).
- `audit-log.index` added to the T-09 route list.
- Words not in the glossary: "Audit log" (spec page name), "Time", "User", "Action", "Record", "Changes", "Reason", "IP", "All users", "All actions", "All records", "Voucher or party", and the action names ("Setting changed", "Login", …).
- Tests: the card's two (accountant 403; filter by action returns only that action) plus record type filter with link, search by voucher and by party.
- Checks: build, check, types, Pint, Larastan, guard pass; **467 PHP tests pass** (one test of this task fixed for a hard-coded id before commit).

### T5.6 Dashboard

Built as the card lists: page 2 `/dashboard` (now a controller): **Today** (kg bought per bought item and kg sold per product from active entries dated today; money received and paid from payments whose journal is posted today; expenses today), **Balances** (each active money account, cash first), **Alerts** (every registered provider with a count above 0, linked, coloured by severity), **Quick buttons** (New purchase, New sale, New payment, Quick expense by permission). New providers: `RatePendingAged` (red), `PaymentsAwaitingApproval` (owner), `BuyersOverCreditLimit`, `NegativeCashBalance`, `TrialBalanceError` (owner). On a phone the order is Today, Alerts, Balances.

Choices and questions:
- **"Received" and "Paid" use the journal's posting date**, so a payment approved today counts today (BR-PAY-08), and one waiting for approval does not count.
- **Every bought item and every product is listed, with 0.000 when nothing moved today**, so the owner sees Wood and Oil/Fat even on a day without them. Inactive items appear only when they have entries today.
- **Owner-only alerts**: approvals (`payment.approve`), reviews (`party.review`), trial balance error (Owner role). Rate pending follows `purchase.view` / `sale.view`; buyers over limit `party.view`; negative cash `account.view` (both roles).
- **The negative cash alert opens the Balances section of the dashboard** (`/dashboard#balances`): the account statement page (25) comes in M6.
- **The quick buttons repeat the phone bottom bar** on a phone (spec §11.3 lists them on the dashboard).
- Screenshot at 375 px checked in the browser (Today, Alerts, Balances in that order; no console errors). Screenshots are not committed.
- Words not in the glossary: "Today", "Kg bought", "Kg sold", "Money", "Received", "Paid", "Expenses", "Total", "Alerts", "Nothing needs attention.", "Balances", "Rate pending too long", "Payments awaiting approval", "Buyers over credit limit", "Cash accounts below zero", "Trial balance error".
- Tests: the card's two (AT-1 day 1 figures with today 2026-01-05: Waste 1800.000, Protein 500.000, received 41000.00, paid 70000.00, expenses 11100.00; the accountant sees no owner-only alerts) plus aged rate pending and buyer over limit for both roles.
- Checks: build, check, types, Pint, Larastan, guard pass; **471 PHP tests pass**.

**M5 built.**

### T6.1 Daily cash check

Built as the card lists: table `cash_counts` (spec §9.5 with `business_closed`; counted amount and difference empty only when the business was closed, counted ≥ 0, delete trigger), `CreateCashCount` (system amount = ledger balance with postings up to that date; difference = counted − system; stored, never posted; audit `cash_count_entered`), `DismissCashCount` (Owner; a `business_closed` row), page 28 `/cash-counts` (mobile-first: date, cash account, system amount refreshed from the server when the date or account changes, counted amount, live difference, note; history list), alert `MissingCashCount` ("No cash count for {date}" when yesterday has no count and no dismissal for an active cash account; the page shows "Mark business closed" to the Owner). "Cash check" added to Checks in the menu.

Choices and questions:
- **"Mark business closed" uses the `period.close` permission** (Owner only), as the card says "owner".
- **The button is on the cash check page** (the alert opens it); dashboard alerts stay plain links.
- **No alert before the system start date.** A cash account added today also asks for yesterday's count; the Owner can mark that day closed.
- **Counts can be entered for any past date, also in a closed month** (BR-CHK-01 "any date"; nothing is posted). Future dates are refused.
- **Bug found and fixed in the browser check**: the shared money display refuses a minus sign unless the value is marked as signed (rule: party balances in words). The cash difference, a rate correction that lowers an amount, a negative cash balance on the dashboard and a partner's net could be negative and would have broken the page. They are now marked `signed`. Party balances still use words.
- **One earlier test of mine changed its setup, not its assertions**: the T5.6 test "the accountant sees no owner-only alerts" now records yesterday's cash count first, because the new missing-count alert (visible to both roles) is correct here.
- Words not in the glossary: "Daily cash check", "Cash check", "Cash account", "System amount", "Counted amount", "Difference", "History", "Counted", "System", "Business closed", "Mark business closed", "Marked business closed.", "Cash count saved. Difference …", "Select a cash account.", "Enter the counted amount: 0 or more, at most 2 decimals.". "No cash count for (date)" is spec BR-CHK-04 text.
- Tests: the card's three (system amount 18900.00 after AT-1 day 1; difference stored, no journal; dismissal clears the alert) plus a count also clears it and only the Owner may dismiss, the system-amount endpoint, history, bank refused; front-end test for the exact signed difference.
- Checks: build, check, types, front-end tests, Pint, Larastan, guard pass; **476 PHP tests pass**.

### T6.2 Daily summary

Built as the card lists: `DailySummary::for(date)` and page 29 `/daily-summary` (`report.view`): kg bought per item and sold per product (active entries of the day, rate pending included), money received and paid (payments posted that day), expenses by category with total, closing balance of each money account at the end of the day, count of rate-pending entries dated that day. "Daily summary" added to Checks in the menu (spec §15.1).

Choices and questions:
- **Kg and money reuse the dashboard's "Today" query**, so both pages always agree.
- **Rate-pending count is the entries of that day still pending now.** After the rate is added later, the day shows 0. The spec does not say "as it was on that day"; tell me if it should.
- **Closing balances list active accounts, and inactive ones only when they still hold money.**
- Words not in the glossary: "Daily summary" (spec page name), "Closing balances", "Entries of this day".
- Tests: the card's R-04 (AT-1 day 1: Waste 1800.000, Protein 500.000, received 41000.00, paid 70000.00, Wages 8000.00, Food 2500.00, Tea 600.00, cash 18900.00, HBL 321000.00, pending 1) plus the page for the accountant.
- Checks: build, check, types, Pint, Larastan, guard pass; full PHP suite passes.

### T6.3 Ageing service

Built as the card lists: `App\Domain\Reports\Ageing` (spec §12.6, oldest first): `overdueFor(buyer, asOf)`, `averageDaysToPay(buyer, from, to)`, `advanceAgeDays(party, asOf)`, and the alerts `OverdueBuyers` and `AgedAdvances` (advance age > `advance_age_days`, red).

How it works (worth checking):
- **Movements come from the party's posted journal lines on its control account** (1200, 2000, 1300, 2100), netted per source record. So a cancelled entry drops out (its reversal cancels it), an edited entry counts once at its current amount and on its current date, a rate correction is its own movement on its own date, and payments awaiting approval do not count (no journal yet). Openings, sales or purchases, payments to the party, unpaid expenses, receipts and adjustments all come in this way without separate lists.
- **Matching**: in date order, an increase first uses up an open advance (oldest first); a reduction pays the oldest open increases first, and what is left becomes an advance. A sale is "completed" on the date of the movement that paid its last rupee.
- **Overdue** counts only unmatched sales (not openings) older than the terms, strictly "older than". Average days to pay uses sales completed inside the period. Advance age is the age of the oldest unmatched payment or receipt.
- Alerts cover active buyers (overdue) and active suppliers and buyers (advances), for users with `party.view`.
- Words not in the glossary: "Overdue buyers", "Aged advances" (spec §11.3 wording).
- Tests: T-12 (ABC 15-day terms: overdue 15000.00 and average 19.00 days; Bashir advance age 34 days and flagged at the 30-day limit; no terms → 0.00) plus a cancelled sale drops out.
- Checks: Pint, Larastan, guard pass; **481 PHP tests pass**.

### T6.4 Pending balances report

Built as the card lists: page 33 `/reports/pending-balances` (`report.view`): as-of date and party type filter; sections "Owes us", "We owe", "Advances"; per party code, name, balance in words and amount, rate-pending kg, oldest advance age (Advances) and overdue amount (buyers who owe us); totals per section. Balances come from each party's control account up to the as-of date; partners are left out (they have no dues).

Choices and questions:
- **Settled parties are left out**, even with rate-pending kg (the card lists Karim as absent). Tell me if a settled supplier with pending kg should show under "We owe".
- **As-of balances use posting dates**: a rate added later to an entry in an open month posts on the entry date (BR-LED-08), so it counts on that date too. Rate-pending kg counts entries dated up to the as-of date that are still pending now.
- Words not in the glossary: "Pending balances" (spec page name), "Advances", "Advance age (days)", "Overdue", "Rate pending" column, "All parties", "Suppliers", "Buyers", "Employees", "Others", "Total", "Code", "Party", "Amount".
- Tests: the card's R-02 after AT-1 (We owe Aslam 52000.00 and Bashir 7000.00, total 59000.00; Owes us ABC 23000.00; Advances Rehman 30000.00, 25 days; Karim absent) plus an earlier as-of date and the type filter.
- Checks: build, check, types, Pint, Larastan, guard pass; full PHP suite passes.

### T6.5 Accounts list and account statement

Built as the card lists: page 24 `/accounts` (each cash and bank account with today's balance; inactive accounts only while they still hold money) and page 25 `/accounts/{id}` (opening, each movement with date, voucher, description and party, money in and out, running balance, closing, for a date range; default the current month). "Accounts" added to the Money group in the menu; the negative cash alert now opens page 24.

Choices and questions:
- **Opening balances always count in the "Opening balance" line**, even when the range starts on the system start date, so a January statement opens with the real opening (300000.00 for HBL) instead of listing it as a movement.
- **The party of a movement is taken from the other line of the same journal** (for example Bashir on PAY-000003).
- **Account balances may be negative and are shown with a sign** (they are not party balances).
- Transfers now have khata/statement text: "Transfer – HBL → Cash – reference".
- Words not in the glossary: "Accounts" (spec page name), "Account", "Type", "Bank", "Cash", "Balance", "Inactive", "Opening balance", "Closing balance", "In", "Out", "Description", "Party".
- Tests: the card's R-06 (HBL after AT-1: opening 300000.00, 8 movements on 5 and 6 January, closing 417600.00, equal to `at1.json`) plus a later range starting from the earlier balance, and the pages for the accountant.
- Checks: build, check, types, Pint, Larastan, guard pass; full PHP suite passes.

### T6.6 Expense report

Built as the card lists: page 34 `/reports/expenses` (`report.view`): date range (default current month), category and party filters; total per category, then each active expense (date, voucher, category, amount, payment mode, party, note).

Choices and questions:
- **Category totals come from the journal lines on the category accounts** (spec §12.1 "money from journal lines only"), joined to their expense for the party filter. So the edited #20 counts once at 2000.00 and a cancelled expense counts 0. The list shows active expenses by entry date.
- The party filter offers suppliers, employees and other parties (the types an expense can have).
- Words not in the glossary: "Expense report" (spec page name), "By category", "All categories", "All parties", "Payment mode", "Total".
- Tests: the card's R-05 (Wages 8000.00, Food 4500.00, Tea 600.00, Electricity 15000.00, Vehicle Fuel 5000.00, total 33100.00, #20 once at 2000.00) plus the category filter and the page.
- Checks: build, check, types, Pint, Larastan, guard pass; **486 PHP tests pass**.

### T6.7 Income vs Spending

Built as the card lists: `IncomeVsSpending::for(from, to)` by posting date (income = Σ credit − debit over income accounts; spending = Σ debit − credit over expense accounts; result = income − spending; pending kg of active rate-pending entries dated in the range; a line per account), page 35 `/reports/income-vs-spending` titled "Income vs Spending" with the glossary §4 banner always visible and the pending-kg line under the result. **The acceptance runner now checks R-08 with this query** (the T4.8 skip is removed; `insights` stays skipped until T7.5).

Choices and questions:
- **The banner text is sent by the server** from one constant (exact glossary text), so the page cannot drift from it.
- **The result is shown with its sign** (it can be below zero, as in AT-1).
- Words not in the glossary: "Income", "Spending", "Account", "Total", "Income − Spending", "Rate pending (not in the amounts above)".
- Tests: the card's (AT-1 104000.00 / 200500.00 / −96500.00 and AT-2 91000.00 / 80800.00 / 10200.00 with 400.000 kg pending, read from the JSON files; banner present; "profit" appears only in the banner: not in any other prop and not in the page source); AT-1 and AT-2 pass with the report check on.
- Checks: build, check, types, Pint, Larastan, guard pass; full PHP suite passes.

### T6.8 Rate, weight difference and adjustments reports

Built as the card lists: page 37 `/reports/rates` (per item and party, each fixed rate over time and the weighted average Σ amount ÷ Σ kg, 2 decimals, before agreed adjustments), page 38 `/reports/weight-difference` (per party, only entries with our weight; purchase difference = supplier kg − our kg, sale difference = our kg − buyer kg; % = Σ difference ÷ Σ our kg × 100), page 39 `/reports/adjustments` (per party and reason: total, count, % of that party's sales or purchases in the range), and the party profile tabs "Rate history" and "Weight differences" (suppliers and buyers, since the system start date). Filters: purchases or sales, date range (default current month), party; item (rates), reason (adjustments).

Choices and questions:
- **A rate correction counts at the corrected rate** in the rate report (it is the rate finally agreed). BR-RPT-03 only says "before agreed adjustments". Tell me if the original rate should show instead.
- **The adjustment % base** is the party's active fixed-rate entries dated in the same range; adjustments count by their own date.
- Shared React pieces: `ReportFilters`, `RateHistory`, `WeightDifferences` (used by the reports and the party profile).
- Words not in the glossary: "Rate report", "Weight difference", "Adjustments report" (spec page names), "Rate history", "Weight differences" (spec §11.11 tab names), "Weighted average", "Their kg", "Our kg", "Difference", "Count", "%", "All items", "All reasons", "Purchases", "Sales".
- Tests: T-13 (Aslam 800 @ 50 and 1000 @ 52 → 51.11; purchases 1000/960 and 500/500 → 40.000 kg, 2.74%; sale 300/295 → 5.000 kg, 1.67%; AT-2 ABC quality 2000.00, 1 adjustment, 3.17% of 63000.00) plus the profile tab and the three pages.
- Checks: build, check, types, Pint, Larastan, guard pass; full PHP suite passes.

### T6.9 Reports home and printing

Built as the card lists: page 32 `/reports` (`report.view`) linking every report the user can open (each checked against its own permission: Partner statement needs `partner.view`, Accounts `account.view`); print stylesheet (`@media print` in `resources/css/app.css`) hiding the sidebar, top bar, bottom bar, buttons, inputs, select boxes and toasts; a `PrintHeader` on every report page, shown only on paper, with the company name (from settings, shared with every page), the report title, the filters used and "Printed on {date time} by {user}". The menu's Reports group now opens the reports home.

Choices and questions:
- **Business insights (page 40) is not listed yet**; T7.5 builds it and adds it to the list.
- **The menu shows one "Reports" entry** (the home page) instead of each report.
- **Printed samples checked**: I printed the trial balance and the pending balances to PDF from the browser (A4, print media). Each shows the company name, title, "As of …", "Printed on … by …", and the tables; no sidebar, buttons or filters. I fixed one thing found there: the trial balance printed its filter labels without the inputs, now hidden on paper. You should still print them from your own browser once.
- Words not in the glossary: "Reports", the one-line description of each report, "Printed on … by …", "From … to …", "As of …".
- Tests: the card's (reports home lists only permitted reports: the accountant does not see Partner statement; 403 without `report.view`) plus the shared company name.
- Checks: build, check, types, Pint, Larastan, guard pass; full PHP suite passes.

### T6.10 Statement and entry slip PDFs

Built as the card lists: `barryvdh/laravel-dompdf` (^3.1), `App\Support\Format` (PHP, the same grouping, rounding and date formats as `format.ts`; it hands the work to the existing `NumberFormat` and `Dates`, which already work on strings, so screens, messages and PDFs cannot drift apart), Blade views `pdf/statement.blade.php` (§13.1) and `pdf/slip.blade.php` (§13.2) with one shared style file, the logo copied to `resources/images/zb-logo-96.png` and shown 40 px tall on white, routes `GET /parties/{id}/statement.pdf?from=&to=` (`party.view`), `GET /purchases/{id}/slip.pdf` (`purchase.view`), `GET /sales/{id}/slip.pdf` (`sale.view`), and a "Share PDF" button (Web Share API with the file where the browser allows it, otherwise a download) on the party profile's khata tab and on active purchase and sale pages.

Choices and questions:
- **Font: DejaVu Sans** (dompdf's built-in). The repository has IBM Plex Sans only as `.woff`/`.woff2` (from `@fontsource`), no TTF, and I may not download fonts. If you want IBM Plex Sans in PDFs, add the TTF files and their OFL licence to `resources/fonts/` and I will switch.
- **dompdf options set in one place** (`PdfDocument`): remote files off, PHP in templates off, JavaScript off, font subsetting on (a statement is ~40 KB instead of ~900 KB). The logo is embedded as a data URI, so no file access is needed.
- **Statement rows**: the khata of the period without payments awaiting approval; **a journal and its reversal that are both in the period are left out together** (a cancelled entry, or the old version of an edited one), and the running balance is recomputed from the opening so it still adds up. If the original is before the period and only the reversal is inside it, the reversal row stays (otherwise the balance would be wrong). Notes are never shown.
- **Rate-pending rows** show "Waste 400 kg – Rate pending" in the details and no amount, as on the screen.
- **Statement period by default**: system start date to today.
- **Slip "Balance"**: the party balance right after this entry and its paid-now / received-now payment (every posting before the entry date, and on that date up to the entry's own journals). "Paid now" counts only active, posted payments linked to the entry. Our weight is never sent to the view.
- **kg follows `kg_decimals`**: with the test value 3 the slip shows "800.000 kg", with 0 it shows "800 kg". The card's T-14 says "800 kg"; my test checks both settings. Tell me if PDFs should drop trailing zeros instead (that would differ from the screens).
- **Footer page number**: "Page 1" (dompdf cannot print "of N" without PHP in templates, which stays off).
- Words not in the glossary: "Statement of Account", "Purchase Slip", "Sale Slip" (spec §13 words), "Period", "Details", "Increase", "Decrease", "Balance", "Voucher number", "Kg", "Page", "Generated on … by …".
- Tests (T-14): Karim after AT-1 (statement contains "Statement of Account", S-0002, all four of his vouchers read from the database, closing "Settled", no "Rate pending"); the statement route returns `application/pdf` starting `%PDF-`; slip for AT-1 #1 (PUR-000001, 800.000 kg / 800 kg, Rs 50.00, Rs 40,000.00, Paid now, "Balance: Settled") and its PDF route; a slip with our weight 793.5 never shows it; a sale slip says "Sale Slip" and "Received now"; Urdu name never in the statement; a cancelled purchase and an awaiting payment are left out and Aslam still closes at "We owe Rs 52,000.00"; guest is sent to login. T-09 route table has the three new routes.
- Checked by eye: I rendered a statement and a slip from the dev data to images (header with logo and company name, period, rows, opening and closing, footer with "Generated on … by …" and the page number).

### T7.1 Month-end close (backend)

Built as the card lists: migration `bank_reconciliations` (spec §9.5, delete blocked by trigger), `RecordBankStatement` (stores the ledger balance at the last day and the difference = statement − ledger), `PostCashDifference` (PR-23 shortage / PR-24 excess, dated the month's last day, voucher CSH, source `month_end_adjustment` with the period id, note required), `CloseMonth` with the six checks in order (previous months closed, rate-pending entries unless accepted, no payment awaiting approval, trial balance balanced, each active bank's latest statement = live ledger at the last day, each active cash account's latest count on the last day = live ledger), and on success: period `closed` with who and when, a `period_events` row and audit `month_closed`. Any failing check changes nothing. PR-23 and PR-24 are now built in `PostingRules`. The acceptance runner can now run `controls.json` cases that build on `at1.json` (`base`), with the steps `bank_statement`, `cash_count`, `cash_shortage` and `close_month`.

Choices and questions:
- **A month closes only after it has ended** (today is after its last day). The spec does not say; closing on the last day would let entries of that same day miss the close. Tell me if the Owner should be able to close on the last day itself.
- **A month before the start month cannot be closed**, and a closed month cannot be closed again (clear messages).
- **The cash difference must be exactly what is left** (latest count on the last day minus the ledger), in amount and direction. This stops a double tap from posting twice (there is no table to hold an idempotency key for this posting) and stops typing slips. A count on the last day must exist first.
- **"Business closed" on the last day is not a count**: the cash check fails until a real count is entered. BR-MEC-06 asks for a count on the last day.
- **Closing cannot race a save**: every save that checks its date takes a shared database lock until it commits, and closing (and posting a cash difference) takes it alone. So a month cannot close while an entry dated in it is half saved, and the entry is refused afterwards. This changes `Periods::assertDateOpen` (T1.9) by one line; the lock is per company and costs nothing noticeable.
- **Where the CSH voucher lives**: conventions §7 says a month-end adjustment has no table of its own (source id = period id), so its voucher and note are in the journal description ("Cash shortage CSH-000001 – Cash: note"). The account statement now shows such a journal with its voucher and description. T7.8's voucher-gap check will read CSH numbers from there.
- **Audit for bank statements and cash differences**: there is no morph alias for bank statements (conventions §7 lists the exact aliases), so both are logged as `created` on the period, with the values in `new_values` (and the note as the reason for a cash difference).
- **Bank statement balance may be below zero** (an overdraft); stored to 2 decimals.
- Messages not in the glossary: ":month has not ended yet.", ":month is already closed.", ":month is before the system start date.", ":month is closed.", "Select a bank account.", "Enter the statement balance, at most 2 decimals.", "Select shortage or excess.", "Enter a note.", "Enter the cash count for {date} first.", "There is no cash difference to post.", "The cash difference is Rs … short/extra. Post exactly that amount.", journal text "Cash shortage" / "Cash excess".
- Tests: AT-3 from `controls.json` through the runner (blocked with bank −500.00 and cash −1000.00, then the bank charges expense and the shortage, closed, the January purchase refused with "January 2026 is closed", 6900 debit 1000.00, cash 20900.00, HBL 417100.00, CSH-000001 on 2026-01-31, period closed), plus one closed event and one `month_closed` audit row; PR-23 and PR-24 exact journal lines; the account statement row; the cash-difference rules (count first, note, exact amount, no second post); BR-MEC-01 (February fails `previous_months` with January open, then both close in order); BR-MEC-02 (pending rate blocks, accepted closes, audit says accepted); BR-MEC-03 (awaiting payment blocks); missing statement or count fails, the latest counts; not before month end, not twice, not before the start month; bank statement values.

### T7.2 Month-end pages and reopen

Built as the card lists: page 30 `/month-end` (`period.close`) for the oldest open month, with the steps 1 Rate pending (with the "Close with pending rates" tick when entries remain), 2 Approvals, 3 Trial balance, 4 Bank statement balance (one input per active bank, with the system balance and the difference), 5 Cash count on the last day (one input per active cash account; "Post shortage" / "Post excess" opens a dialog with the exact remaining amount and a required note), 6 Close month (enabled only when every step is Done; the server runs every check again). Page 31 `/months` (`period.close`): every month from the start month to the current month, newest first, with status, closed by and closed on; "Reopen month" only on the latest closed month and only for `period.reopen`, with a reason (`ReopenMonth`: event `reopened` with the reason, audit `month_reopened`). A "Month-end" menu group with both pages.

Choices and questions:
- **Reopen reason**: at least 5 characters, the same rule and message as cancel reasons (BR-GEN-04). BR-MEC-08 only says "with a reason".
- **Reopening clears "closed by" and "closed on"** on the month; who closed and reopened it when stays in `period_events` and the audit log.
- **A month that has not ended** shows "{Month} has not ended yet. It can be closed after {last day}." and no steps.
- **Previous months** (BR-MEC-01) is not shown as a step: the page always shows the oldest open month, so it always passes. It appears only if it ever fails.
- **Cash count on page 30** uses its own route (`month-end.cash-count`, `period.close`) with the date fixed to the month's last day, so the Owner stays on the page; it saves through the same `CreateCashCount` as page 28.
- **Bank statement input allows one leading minus** (overdraft); other money inputs do not.
- **T-09**: the seven new routes are in the route table, and the test's route parameters now include a month (`period` = 2026-01) for the reopen route.
- Words not in the glossary: "Months" (menu and page title; spec page name is "Months list"), "Done", "Not done", "Rate pending" step text ("No rate-pending entries in this month."), "Approvals" step text, "Bank statement balance", "System balance", "Counted", "Difference", "Statement balance", "Counted amount", "Post shortage", "Post excess", "Close with pending rates" (spec §10 BR-MEC-02 words), "After closing, nothing dated in {Month} can be added, edited or cancelled.", "Every month up to now is closed.", "Open", "Closed", "Closed by", "Closed on", "Previous months", messages "Bank statement balance saved.", "Cash count saved.", "{Month} is closed.", "{Month} is open again.", "The month cannot close yet. Fix the steps marked Not done.", "Only the latest closed month ({Month}) can be reopened.", "No month is closed.".
- Tests: the card's three (only the latest closed month can be reopened; reopen requires a reason and logs it with event and audit; the accountant cannot reopen, close, or open either page) plus page 30 walked through its routes from blocked to closed (statement, count, refused close, cash difference with voucher CSH-000001, close, months list).
- Checked by eye: screenshots of page 30 (desktop and phone) and page 31.

### T7.3 Opening balances lock

Built as the card lists: `SetOpeningBalance` is refused once any month is closed (BR-MEC-09) with "Opening balances are locked because {Month Year} is closed." (the earliest closed month), and the opening balances page becomes read-only with the same text.

Choices and questions:
- **The lock is checked before "marked complete"**, so the message names the closed month when both apply.
- **Reopening every closed month unlocks the openings again** (unless they are marked complete). BR-MEC-09 says they lock "when the first month is closed"; it does not say what a reopen does. Tell me if a reopen should keep them locked.
- Tests: the card's (change after the first close refused, through the Action and the page route, balance unchanged, page shows `locked_by` January 2026) plus: after reopening, openings can change again.

### T7.4 Merge duplicate parties

Built as the card lists: `MergeParties` (Owner; same type, both active, two different parties; if the source has a balance, one journal moves it to the target: source `party_merge`, source id = the target, dated today; the source becomes inactive with `merged_into_id`; audit `merged` on both), page 20 `/parties/merge` (`party.merge`, linked from the parties list) with two party pickers, both balances, the balance after the merge and a confirmation, and the khata rows: the target shows "Balance moved from {source name} ({code})" linking to the source's profile, the source shows "Balance moved to {target name} ({code})"; the source profile shows "Merged into {target}". The acceptance runner can now run the `merge` step and check `merged_into`, `purchase_<ref>_party` and `merge_journal`.

Choices and questions:
- **Document conflict (please decide):** CLAUDE.md rule 2 says "Posting rules PR-01 to PR-27 are the only postings", but spec BR-PTY-08 and card T7.4 require the merge journal, and spec §8.3 has no PR number for it. I built it as `PostingRules::partyMerge()` in the same place as the others, with a doc comment saying it has no PR number. Proposed spec change (yours, `[spec-change]`): add to §8.3 "PR-28 | Party merge (balance moved) | Source party's control account (P) when it has a credit balance, else target's | the other one | A" and allow PR-28 in CLAUDE.md rule 2.
- **A source with rate-pending entries or payments awaiting approval cannot be merged** ("{name} has rate-pending entries or payments awaiting approval. Finish them first."). Otherwise a rate added or a payment approved later would post to the inactive source and leave it with a balance again.
- **Partners**: both 3000 and 3100 are moved, each on its own pair of lines, in the one journal.
- **A settled source merges without a journal** (nothing to move).
- **The merge is posted today**, so it needs today's month open (it always is) and shows on the target's khata on that date.
- Words not in the glossary: "Merge parties" (spec page name), "Duplicate profile (will be inactive)", "Merge into", "After the merge, …", "… keeps its entries and becomes inactive, marked "Merged into …".", "Merge", "This cannot be undone.", "Merged into", "Balance moved from … / to …", messages "Pick two different parties.", "Only parties of the same type can be merged.", "Both parties must be active.", "{source} merged into {target}.".
- Tests: AT-6 from `controls.json` through the runner (Aslam we owe 15000.00, the duplicate settled, inactive, merged into Aslam, its purchase still its own, the merge journal dated 2026-01-31 with 2000 debit duplicate 10000.00 / credit Aslam 10000.00); T-15 (the source's journal lines are byte-for-byte the same after the merge, plus one new line); a supplier into a buyer is refused; same party, inactive party and pending rate refused; a settled duplicate merges with no journal; page 20 preview and merge (accountant 403), both khata rows with their links, and "Merged into" on the source profile.

### T7.5 Business insights

Built as the card lists: `App\Domain\Insights\BusinessInsights::for(from, to)` per spec §12.5 (kg ratios protein, oil/fat and combined = kg sold ÷ waste kg bought; wood per kg of waste; rough cost per kg sold = Income vs Spending "Spending" ÷ product kg sold; by month: wood per kg of waste and the waste buying rate against each product's selling rate; supplier comparison: kg, average rate, advance outstanding at the period end, weight difference %; buyer comparison: average days to pay and overdue (spec §12.6, the existing `Ageing`), agreed adjustments and their % of his sales, share of all sales %), page 40 `/insights` (`insights.view`) with a date range, every figure and section marked "Rough indicator", the two charts and the two tables; "Business insights" on the reports home for users with `insights.view`. **The acceptance runner now checks `insights`** (its skip list is empty).

Choices and questions:
- **No chart package.** The card says "shadcn charts", which need the `recharts` package; CLAUDE.md allows only the packages it names, and recharts is not one of them. So the two charts are simple horizontal bars drawn with Tailwind, their lengths worked out on the server as whole percents (no number maths in the browser). If you want shadcn charts, approve `recharts` and I will switch.
- **Default period**: today minus `insights_period_months` months, to today (for 31 January 2026 with 3 months: 31 October 2025 to 31 January 2026).
- **Kg** use entry dates and include rate-pending entries (BR-RPT-01, BR-RPT-02); **rates** are Σ amount ÷ Σ kg of fixed-rate entries, at the corrected rate where a rate correction exists (same as the rate report, T6.8).
- **Which items**: waste = items of type raw material, wood = type fuel, products = type product; "protein" and "oil/fat" are the items with codes PROTEIN and OILFAT.
- **A figure with nothing to divide by shows "–"**, not 0.
- **Buyer adjustments** = active agreed adjustments on sales dated in the period; **share of sales** uses fixed-rate sales amounts.
- **Earlier test changed (please review):** `tests/Feature/Reports/ReportsHomeTest.php` (mine, T6.9) asserted the Owner sees exactly one report more than the Accountant. Business insights is a second Owner-only report, so I replaced that count with a stricter check: the Owner's extra reports are exactly "Partner statement" and "Business insights", and the Accountant has none the Owner lacks. My T6.9 assertion was too narrow; T6.9's card already says the home lists every report the user can see.
- Words not in the glossary: "Business insights" (spec page name), "Rough indicator" (spec §12.5), "Every figure on this page is a rough indicator.", "Kg ratio, protein / oil/fat / combined", "Wood per kg of waste", "Rough cost per kg sold", "Kg in the period", "Waste bought", "Wood bought", "Product sold", "Wood per kg of waste by month", "Buying vs selling rate by month", "Waste (buying)", "{product} (selling)", "Supplier comparison", "Buyer comparison", "Average rate", "Advance", "Weight difference", "Average days to pay", "Overdue", "Adjustments", "{n}% of his sales", "Share of sales".
- Tests: the card's (AT-1 from `at1.json`: 0.1786, 0.0000, 0.1786, 1.0714, 401.00, also through the runner), months/suppliers/buyers after AT-1 (Aslam 1800.000 kg at 51.11, no advance), empty ratios when nothing to divide by, page 40 permission and default period, reports home link only with `insights.view`.

### T7.6 Backup status page

Built as the card lists: `config/zb.php` (`backup_status_dir` from `ZB_BACKUP_STATUS_DIR`), `App\Domain\Backups\BackupStatus::read()` (reads `backup-db.json`, `backup-db-last-success.json`, `backup-files.json`, `backup-files-last-success.json` written by the server scripts; a missing folder or file means "not configured on this server", never an error), page 48 `/settings/backups` (`backup.view`, in the Settings menu as "Backups") showing for the database and the uploads: last successful backup (Asia/Karachi time), size in MB, copies made, last attempt result and error text, a red "Late" badge when the last success is older than 2 hours (database) or 26 hours (uploads), and the fixed text "Backups are encrypted and stored off the server. They can only be restored by the key holders." No download button and no download route. Dashboard alert `BackupLate` (owner only, same rule, after the other alerts).

Choices and questions:
- **README.md is a protected file**, so I did not add the "Backups and restore" section. Proposed text for you to add to `README.md`: "## Backups and restore — Backups are made by the server scripts in `infra/` (hourly database, daily uploads), encrypted and stored off the server. The app's Backups page (Settings → Backups) shows the last success and the last error. How to restore: `docs/operations/runbook.md` §4. What to do when a backup is late or failing: `docs/operations/runbook.md` §9."
- **"Not configured" per kind**: a kind with neither of its two files shows "Backups are not configured on this server." A configured kind that has never succeeded is Late.
- **"Copies made"**: for the database the script's `copies` (hourly, daily, monthly); for uploads it writes no copies, so the page shows its `type` (full or incremental) there.
- **The page is at `resources/js/pages/backups/show.tsx`**, not under `settings/`: the starter kit gives every `settings/*` page its own profile layout.
- **The restore on staging is yours** (after T8.3, recorded in `docs/release-checks.md`). So I left T7.6 **unticked** in `docs/tasks.md`: its build and tests are done; tick it after your restore.
- Words not in the glossary: "Backups", "Database", "Uploads", "Late", "OK", "Last successful backup", "Size", "Copies made", "Last attempt", "Succeeded", "Failed", "Error", "Backups are not configured on this server.", "Late when the last successful backup is older than {n} hours.", alert "Backup late".
- Tests: the card's six with fixture JSON files in a temporary folder (fresh success OK; 3 hours old Late, alert count 2 for the Owner and 0 for the Accountant; a failure shows its error text; missing folder not configured; page needs `backup.view`; the only route with "backup" is GET `settings/backups` and no route is named "download").

### T7.7 Acceptance AT-3 to AT-6

Built as the card lists: the `AcceptanceRunner` runs every step type of `controls.json` (the month-end steps came with T7.1, `merge` with T7.4; this task adds a step's own `expect` — approval status, journal or not, journal posting date, party and money account balances right after it — and the AT-5 checks `purchase_<ref>_unchanged`, `rate_correction` and `rate_correction_journal`). `tests/Acceptance/ControlsTest.php` has one test per case: AT-3, AT-4, AT-5 (with the real close) and AT-6. The runner now **fails on any `expected` or step `expect` key it does not check**, so a new key in the JSON can never be skipped silently.

Choices and questions:
- **`closeMonthForTest()`**: no acceptance test uses it. Seven older feature tests (T1.9 to T4.x) still do; I left them as they are (the rule is not to change existing tests, and the T7.1 card only forbids it in new tests).
- **AT-3 and AT-6 also have their own tests** in `tests/Feature/Periods` and `tests/Feature/Parties` from T7.1 and T7.4; I kept them (they add the event, audit and khata checks).
- Tests: AT-1 to AT-6 all pass.

### T7.8 Integrity check command

Built as the card lists: `App\Domain\Ledger\IntegrityCheck` (SELECTs only) with the five checks — every journal balances; trial balance totals agree (the T1.10 query); voucher numbers 1 to `next_number − 1` exist once each for every document type (CSH numbers read from the month-end journal descriptions, party codes from parties); control-account lines carry a party and no other line does; an active posted entry points to a journal of its own and a cancelled posted entry has a reversal. Each check prints OK or FAILED with journal ids, voucher numbers or `type:id` (never names or amounts). `php artisan zb:verify {--db-name=}` (`App\Console\Commands\VerifyLedger`) only calls it; exit code 0 or 1; on the live database it writes `storage/app/zb-verify.json` as `{"result":"ok"|"failed","finished_at":"…Z","failed_checks":[…]}` with `json_encode` (no pretty printing); `--db-name` points the `pgsql` connection at that database for the run (`config` + `DB::purge`) and writes no file. Scheduled daily at 20:30 UTC (01:30 Pakistan time) with `withoutOverlapping()`; `php artisan schedule:list` shows `30 20 * * * php artisan zb:verify`.

Choices and questions:
- **`--db-name` accepts only letters, digits and underscores** (it becomes a connection setting).
- **When the checks cannot run** (for example the database does not exist), it prints "FAILED could not run the checks ({error kind})" without the error message (it could contain server details) and exits 1; on the live database the file then says `failed` with `run` as the failed check.
- **Trial balance check** uses the current company (version 1 has one); with no company at all (an empty restored copy) it has nothing to check and says OK.
- **Test-only trigger switching**: as the card says, two tests disable a journal trigger inside their own transaction (rolled back after the test) to plant a fault. App code never does this.
- **The tests save and restore `storage/app/zb-verify.json`**, so a local run's file is not lost; the file is git-ignored.
- **Checked on the dev data**: all five checks OK.
- Tests (T-20): after AT-1 every check OK and the file says ok; each planted fault fails exactly its check — two unbalanced journals (totals still agree), a voucher gap (PUR-number missing), a control-account line without a party, an active purchase pointing to another purchase's journal, a cancelled purchase without a reversal; `--db-name` checks the named database in a separate process (OK on the test database, exit 1 on a missing one, exit 1 on a bad name) and writes no file; the schedule entry (20:30 UTC, without overlapping).

### T8.1 Security checklist

Built as the card lists: `docs/release-checks.md` section "Security" with one row for each of BR-SEC-01 to BR-SEC-18 (and the Inertia devtools routes), and section "Operations" for BR-OPS-01 to BR-OPS-10: where each rule is enforced, the test or check that proves it, and the result. Rows that need a real server say **verify on staging** (for T8.3); rows that need the go-live (key holders, rebuild drill) say so.

Found and fixed in application code:
- **BR-SEC-04**: `config/session.php` fell back to the kit's 120 minutes when `SESSION_LIFETIME` is missing; the default is now 60.
- **Missing proofs** added as tests (`tests/Feature/Security/ReleaseChecksTest.php`): bcrypt (BR-SEC-02), 60-minute idle in the shipped settings (BR-SEC-04), the CSRF middleware on the `web` group (BR-SEC-07), Secure/HttpOnly/SameSite=Lax cookies and encrypted sessions in `.env.production.example` (BR-SEC-15), no debug in production and strict models elsewhere (BR-SEC-16), daily logs kept 14 days in production (BR-SEC-17), CSP on in production (BR-SEC-14), and the Inertia devtools never on in production.

Choices and questions:
- **Inertia devtools routes** (`_inertia/devtools/*`): the Inertia package registers them only when `APP_ENV=local` (unless `INERTIA_DEVTOOLS_ENABLED` is set, which production does not). Locally they exist and record requests to `storage/inertia-devtools`; on servers they do not. Check once on staging with `php artisan route:list`.
- **This cloud container's own `.env`** (not in git) has `SESSION_LIFETIME=120` from an older copy; your laptop's `.env` comes from `.env.example` (60). The test now checks the shipped files, not a developer's `.env`.
- Nothing in protected files needed to change for T8.1.
- Tests: the eight new checklist tests pass with the existing security tests (26 in `tests/Feature/Security`).
