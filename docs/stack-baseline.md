# Stack baseline (verified facts)

Checked on **11 October 2026**; the cloud-session, laptop, Claude Code and GitHub rows were checked on **13 October 2026**. Every row says how it was checked. If a fact here is older than 6 months when you start a task that depends on it, check it again.

**How to use this file:** task T0.1 records the exact versions it installed in `docs/installed-versions.md`. If the starter kit Claude installs differs from the "Starter kit" section, Claude stops and reports; it does not adapt silently.

## Server operating system and runtimes

| Item | Fact | How checked |
| --- | --- | --- |
| Ubuntu 26.04 LTS ("resolute") | Released 23 April 2026. Standard security maintenance until **May 2031** | [Ubuntu 26.04 release notes](https://documentation.ubuntu.com/release-notes/26.04/), [Ubuntu release cycle](https://ubuntu.com/about/release-cycle) |
| Ubuntu 24.04 LTS | Standard security maintenance until May 2029. Ships PHP 8.3 | Ubuntu release cycle page; PHP 8.3.6 in this workspace (Ubuntu 24.04.5) |
| PHP in Ubuntu 26.04 | **8.5.4** (`php8.5-fpm` 8.5.4-0ubuntu1.3 in `resolute-security`). `php8.5-fpm`, `-intl`, `-bcmath`, `-zip` are in **universe**; `-cli`, `-pgsql`, `-gmp`, `-mbstring`, `-xml`, `-curl`, `-gd` are in main. There is no `php8.5-opcache` package (OPcache is built in) | Read `archive.ubuntu.com/ubuntu/dists/resolute*/…/Packages.xz` directly |
| PostgreSQL in Ubuntu 26.04 | **18** (`postgresql-18` 18.6-0ubuntu0.26.04.1) | Same package index |
| nginx in Ubuntu 26.04 | 1.28.3 | Same package index |
| Other 26.04 packages used | `age` 1.2.1, `rclone` 1.60.1, `certbot` 4.0.0, `shellcheck` 0.11.0, `ufw` 0.36.2, `unattended-upgrades` 2.12 | Same package index |
| PHP support dates | 8.3 security until 31 Dec 2027; 8.4 until 31 Dec 2028; **8.5 until 31 Dec 2029** | [php.net branches](https://www.php.net/releases/branches.php) |
| PostgreSQL support dates | 16 until 9 Nov 2028; 17 until 8 Nov 2029; **18 until 14 Nov 2030** | [PostgreSQL versioning policy](https://www.postgresql.org/support/versioning/) |

**Decision (ADR-0003):** production and staging run **Ubuntu 26.04 LTS with the Ubuntu-packaged PHP 8.5 and PostgreSQL 18**. Ubuntu 24.04 would mean PHP 8.3, whose upstream security support ends in December 2027, before this system has been in use for two years.

## Laravel

| Item | Fact | How checked |
| --- | --- | --- |
| Laravel 13 | Released 17 March 2026. Supports PHP 8.3 to 8.5. Bug fixes until Q3 2027, **security fixes until 17 March 2028** | [Laravel 13 release notes](https://laravel.com/docs/13.x/releases) |
| Framework version on 10 Oct 2026 | 13.35.0 | `laravel/framework` branch 13.x, `Application::VERSION` |
| `migrate --database=X` | Switches the **default** connection to X for the whole run, so `Schema::` and `DB::statement` inside migrations use X | `Migrator::usingConnection()` and `setConnection()` source |
| `/up` health route | Fires the `DiagnosingHealth` event; if a listener throws, `/up` returns 500 (JSON `{"status":"down"}` when JSON is requested). With `APP_DEBUG=false` the error is reported, not shown | `ApplicationBuilder::buildRoutingCallback()` source |
| Key rotation | `APP_PREVIOUS_KEYS` (comma separated) is read by the encrypter | `config/app.php` in the kit and framework |
| Password rule `uncompromised()` | Sends only the first 5 characters of the password's SHA-1 hash to `api.pwnedpasswords.com`. If the service cannot be reached, the password is **accepted** (fails open) | `NotPwnedVerifier` source |
| Local disk permissions | A local disk accepts `'permissions' => ['file' => [...], 'dir' => [...]]` | `FilesystemManager::createLocalDriver()` source |
| Fortify two-factor secrets | Encrypted with the application encrypter, so they depend on `APP_KEY` | `EnableTwoFactorAuthentication` source (laravel/fortify) |

## Starter kit (laravel/react-starter-kit)

Checked at commit `717b8f55` (21 September 2026). Read-only clone; nothing from it was run here.

| Item | Fact |
| --- | --- |
| PHP packages | `php ^8.3`, `laravel/framework ^13.17`, `inertiajs/inertia-laravel ^3.0`, `laravel/fortify ^1.37.2`, `laravel/wayfinder ^0.1.14`, `laravel/chisel ^0.1.0`, `laravel/tinker ^3.0` |
| PHP dev packages | `phpunit/phpunit ^12.5.23`, `larastan/larastan ^3.9` (already installed, `phpstan.neon` at **level 7**), `laravel/pint`, `laravel/pail`, `laravel/sail`, `mockery`, `collision`, `faker`, `laravel/pao ^1.0.6` (prints test results in a form made for AI agents; seen in T0.1 on 13 Oct 2026, kept by the human) |
| JS toolchain | `vite-plus` 0.3.0 (`vp build`, `vp check` for lint + format with Oxlint/Oxfmt, `vp test` runs bundled **Vitest 4.1.11**), React Compiler, Tailwind 4, `@inertiajs/react ^3`, `laravel-vite-plugin ^3`, TypeScript |
| npm scripts | `build` = `vp build`, `check` = `vp check`, `check:fix`, `types:check` = `tsc --noEmit`. There is **no ESLint** and no `test` script |
| Composer scripts | `lint` (pint), `lint:check`, `types:check` (phpstan), `test` (config:clear, lint:check, types:check, artisan test), `ci:check` |
| Routes to JS | **Wayfinder** (typed route functions). There is no Ziggy and no `route()` helper in JS |
| Fonts | `laravel-vite-plugin` fonts: `bunny('Instrument Sans')`. Fonts are **downloaded at build time** and self-hosted. `fontsource('IBM Plex Sans')` reads from `node_modules/@fontsource/ibm-plex-sans` instead (no network at build) |
| Other root files | `.npmrc` (`ignore-scripts=true`) and `pnpm-workspace.yaml` (seen in T0.1 on 13 Oct 2026) |
| Lock files | The kit ships **no** `composer.lock` and **no** `package-lock.json`. Versions are fixed only when T0.1 creates the lock files |
| Feature installer | `php artisan install:features --answers='{"auth_features":[...]}'`. Options: `email-verification`, `registration`, `2fa`, `passkeys`, `password-confirmation`; all on by default. With `LARAVEL_INSTALLER_DEFER_HOOKS=1` and no `--answers`, the command does nothing. With `LARAVEL_INSTALLER_NO_NODE=1`, it skips npm install and build. After it runs, it deletes itself and `chisel.php` |
| Not removed by the installer | `Features::resetPasswords()` (forgot / reset password) and the `profile.destroy` route (delete account). T0.2 removes both |
| Root view | `resources/views/app.blade.php` has one inline `<script>` (dark mode) and one inline `<style>`. Inertia 3 puts page data in `<script type="application/json">`, which a Content Security Policy does not block |
| `.env.example` | sqlite, `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` |
| `phpunit.xml` | sqlite `:memory:` (T0.1 changes it to PostgreSQL) |
| Production defaults in `AppServiceProvider` | `CarbonImmutable` dates; `DB::prohibitDestructiveCommands()` in production; password rule in production: min 12, mixed case, letters, numbers, symbols, `uncompromised()` |
| `.github/workflows/` | The kit ships `tests.yml`. Not copied (our CI is `ci.yml`) |
| `.gitignore` | Ignores `/.claude`, `/CLAUDE.md`, `/AGENTS.md`, `/boost.json` (AI-tool files) and the generated Wayfinder folders `resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`. T0.1 removes the `/.claude` and `/CLAUDE.md` lines; the guard script checks it |

## Laravel installer (laravel/installer)

| Item | Fact |
| --- | --- |
| Kit version | The installer runs `composer create-project laravel/react-starter-kit <dir> --stability=dev`: the kit has no release tags, so you get the **main branch at that moment**. T0.1 records the commit Composer prints |
| Pest | `--pest` installs `pestphp/pest` and `pestphp/pest-plugin-laravel` **without a version limit** and converts tests with the drift plugin |
| Laravel Boost | Installed by default in interactive mode unless `--no-boost`. Boost writes AI guideline files such as `CLAUDE.md`, which would collide with this project's `CLAUDE.md`. **Not used** |
| Feature answers | The installer cannot pass `--answers` to `install:features`. In non-interactive mode the kit keeps **all** features. That is why T0.1 uses `composer create-project` + `install:features --answers` instead of `laravel new` |

## Pest

| Version | Requires | Fits this project? |
| --- | --- | --- |
| Pest 5.x (latest 5.3.1) | PHP **^8.4**, PHPUnit ^13.4 | No: cloud sessions and the laptop (Linux Mint 22) run PHP 8.3, and the kit pins PHPUnit 12 |
| Pest 4.7.8 | PHP ^8.3, PHPUnit ^12.5.33 | **Yes** |
| pest-plugin-laravel 4.1.0 | PHP ^8.3, Laravel ^13.0, Pest ^4.4.1 | **Yes** |

Checked from the `composer.json` of each tag on GitHub. T0.1 pins `pestphp/pest:^4.7` and `pestphp/pest-plugin-laravel:^4.1`.

## Cloud sessions (Claude Code on the web)

| Item | Fact | How checked |
| --- | --- | --- |
| Plans and access | Cloud sessions are available on Pro, Max and Team plans (and Enterprise premium seats). A private repository needs the Claude GitHub App installed on it | [Get started with Claude Code in the cloud](https://code.claude.com/docs/en/web-quickstart), 13 Oct 2026 |
| Machine | Ubuntu 24.04 with PHP 8.3 and Composer, Node.js 20–22, PostgreSQL 16 (installed, not running until started). A workspace of this kind showed PHP 8.3.6 | [Configure cloud environments → Installed tools](https://code.claude.com/docs/en/cloud-environments#installed-tools), 13 Oct 2026. **Check `php -v` in the first session**; if it differs, use that version in T0.1's platform setting |
| Network ("Trusted", the default) | The allow-list includes `packagist.org`, `repo.packagist.org`, `registry.npmjs.org`, `github.com`, `api.github.com` and `codeload.github.com`. It does **not** include `fonts.bunny.net` | [Configure cloud environments → Default allowed domains](https://code.claude.com/docs/en/cloud-environments#default-allowed-domains), 13 Oct 2026 |
| GitHub proxy and Composer | All GitHub traffic goes through a GitHub proxy, whatever the network level. It refuses GitHub **API** requests (HTTP 403, "GitHub access to this repository is not enabled for this session") for every repository not attached to the session; `git fetch` of public repositories works. Composer downloads every package as `https://api.github.com/repos/<owner>/<repo>/zipball/<commit>`, so all 139 kit packages failed, and `phpstan/phpstan` has no git source at all. Fix: `scripts/cloud-composer-cache.sh` builds each zip from git (`git archive`, as GitHub does) into Composer's cache as `<package>/<sha1 of the URL>.zip`; Composer uses it because GitHub zips have no checksum in `composer.lock`. Tested: 139 packages from an empty cache in about 35 seconds, then `composer install` with no download. Relies on Composer's cache naming (Composer 2.8.12 here); if a later Composer changes it, the start script's install fails again with 403 | [Configure cloud environments → GitHub proxy](https://code.claude.com/docs/en/cloud-environments#github-proxy), and T0.1 in a cloud session, 13 Oct 2026 |
| Repository hooks | A SessionStart hook in the repository's `.claude/settings.json` runs in cloud sessions on start and resume. `CLAUDE_CODE_REMOTE` is `true` only in the cloud. A command hook is cancelled after 600 seconds unless `timeout` is set (this project sets 900) | Same page, "Setup scripts vs. SessionStart hooks" and "Time limits" |
| Environment variables | Readable by anyone who uses the environment: never put a secret there. This project needs none | Same page |
| Permission modes | Cloud sessions offer Accept edits, Plan and Auto (no Manual) | [Permission modes](https://code.claude.com/docs/en/permission-modes), 13 Oct 2026 |
| Pull requests | The diff view has **Create PR**. Auto-fix is a per-pull-request toggle that lets Claude respond to CI failures and review comments; this project keeps it off | [Use Claude Code in the cloud](https://code.claude.com/docs/en/claude-code-on-the-web), 13 Oct 2026 |

## Laptop (Linux Mint): pull, run and test

| Item | Fact | How checked |
| --- | --- | --- |
| Linux Mint | 22.x ("Wilma", "Xia", "Zara", "Zena" = 22.3, 13 Jan 2026) is based on **Ubuntu 24.04 LTS (noble)**. Linux Mint 23 is planned for December 2026 on Ubuntu 26.04 LTS (resolute) | [Linux Mint (Wikipedia)](https://en.wikipedia.org/wiki/Linux_Mint), 13 Oct 2026 |
| Packages on a noble base | PHP **8.3.6** (`php-cli`, `php-pgsql`, `php-mbstring`, `php-xml`, `php-curl`, `php-zip`, `php-intl`, `php-bcmath`, `php-gd`, `php-gmp`), Composer **2.7.1**, PostgreSQL **16**, `gh` 2.45, `python3`. The packaged Node.js is too old (18), so `scripts/setup-local.sh` installs Node.js 22 from NodeSource | `apt-cache policy` on Ubuntu 24.04, and a full run of `scripts/setup-local.sh` there (13 Oct 2026) |
| Claude Code on the laptop (optional) | Official installer `curl -fsSL https://claude.ai/install.sh \| bash` (installs to `~/.local/bin/claude`, updates itself). Needs a Pro, Max, Team, Enterprise or Console account. Ubuntu 20.04+ / Debian 10+, 4 GB+ RAM | [Claude Code setup](https://code.claude.com/docs/en/setup), 13 Oct 2026 |
| Starting permission mode (laptop, optional) | Recent Claude Code versions start in auto mode unless settings say otherwise. A project's `.claude/settings.json` `permissions.defaultMode: "plan"` makes **terminal** sessions start in Plan mode. The VS Code extension does not read the project's starting mode; set `claudeCode.initialPermissionMode` to `plan` in VS Code user settings. Shift+Tab cycles modes | [Permission modes](https://code.claude.com/docs/en/permission-modes), 13 Oct 2026 |
| Permission rules | `Bash(git tag *)` matches `git tag` with any arguments, and the bare command too. Deny rules win over allow rules. Rules match the command as written, so `git -C . push origin main` is not matched by `Bash(git push origin main *)`; the project hook covers those forms | [Configure permissions](https://code.claude.com/docs/en/permissions), 13 Oct 2026 |
| Editors | VS Code (or Antigravity): open the folder and use its terminal to pull, run and test. The Claude Code extension is optional; it installs in VS Code 1.94+ and in VS Code forks through Open VSX | [Claude Code in VS Code](https://code.claude.com/docs/en/vs-code), 13 Oct 2026 |
| Network for building | T0.1 downloads from Packagist, GitHub (`codeload.github.com`) and the npm registry (in cloud sessions, GitHub packages come through git instead: see "GitHub proxy and Composer"). The kit's build would also download fonts from `fonts.bunny.net`; T0.1 replaces that with a self-hosted font (ADR-0010) | Starter kit source |

Two consequences, both handled in the cards:

1. `composer.json` gets `"config": {"platform": {"php": "8.3.6"}}`, so the lock file installs in cloud sessions and on the laptop (PHP 8.3) and on the servers (PHP 8.5). CI runs the tests on both. On Linux Mint 23 (PHP 8.5) the same lock file still installs.
2. T0.1 switches the font to `fontsource('IBM Plex Sans')` before the first build, so `npm run build` never needs `fonts.bunny.net`. If Composer or npm cannot download packages, T0.1 stops and reports.

## GitHub (the developer uses GitHub Free; ADR-0012)

| Item | Fact | How checked |
| --- | --- | --- |
| Branch protection | Protected branches and rulesets work in **private** repositories only with GitHub Pro, Team or Enterprise. GitHub Free has them for public repositories only | [About protected branches](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches), [About rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets), 13 Oct 2026 |
| Actions minutes | GitHub Free: 2,000 minutes a month and 500 MB artifact storage for private repositories. Without a payment method, usage is **blocked** when the quota is used up. Public repositories on standard runners are free. In this project pull requests test both PHP versions, `main` tests PHP 8.5, and Infra runs only when server files change |
| Claude cloud sessions and GitHub | The GitHub proxy keeps your credentials outside the session, rejects branch deletions and pushes of anything other than a branch (such as a tag), and **does not limit which branches a push can update**. `gh` commands that use GraphQL (`gh pr`, `gh issue`) are rejected; `gh api` REST calls work | [Configure cloud environments → GitHub proxy](https://code.claude.com/docs/en/cloud-environments#github-proxy), 13 Oct 2026 | [GitHub Actions billing](https://docs.github.com/en/billing/concepts/product-billing/github-actions), 13 Oct 2026 |
| Pull request events | Without listed types, a `pull_request` workflow runs on `opened`, `synchronize` and `reopened` only (editing the title does not re-run CI) | [Events that trigger workflows](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows) |

## Backup storage

| Item | Fact | How checked |
| --- | --- | --- |
| Backblaze B2 Object Lock | Works through the S3-compatible and native APIs; can be turned on for an existing bucket; governance mode can be overridden by keys with `bypassGovernance`, compliance mode cannot be shortened by anyone | [Backblaze Object Lock docs](https://www.backblaze.com/docs/cloud-storage-object-lock) |
| B2 key capabilities | `writeFiles` and `deleteFiles` are separate capabilities, so a key can upload but not delete. `writeFiles` also allows "hiding" a file (old versions stay) | [B2 application key capabilities](https://www.backblaze.com/docs/cloud-storage-application-key-capabilities) |
| Provider choice | **OPEN (O-32).** B2 is the worked example only. Whatever provider is chosen, `zb-backup-verify-offsite` must prove on day one that the server key cannot delete | — |

Not verified and therefore not relied on: whether rclone 1.60 sends the `Content-MD5` header that some S3 services require for Object Lock uploads. The worked example uses rclone's native **b2** backend, not the S3 API.

## Legal

| Item | Fact | How checked |
| --- | --- | --- |
| Record retention | Section 174(3) of the Income Tax Ordinance 2001 obliges a taxpayer to keep records for **six years** (Supreme Court judgment reported 21 April 2022) | [Business Recorder report](https://www.brecorder.com/news/40168577/taxpayer-cant-be-forced-to-produce-record-for-tax-year-beyond-six-years-sc). Not legal advice: the client's tax adviser confirms the period (O-35) |

## Versions installed by T0.1

Claude records the exact installed versions (from `composer.lock` and `package-lock.json`) and the starter-kit commit in **`docs/installed-versions.md`** during task T0.1. This file stays as checked facts; it is protected.
