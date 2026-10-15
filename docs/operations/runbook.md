# Runbook

What to do, routinely and when something goes wrong. Commands run over SSH in the hosting account's shell (hPanel → Advanced → SSH Access); shared hosting has no `sudo` and no root, so everything runs as the account's own user and the application lives in `~/accounts` (outside `public_html`). Two rules apply to every section:

- **Never delete** logs, backups, releases marked `.failed` or database rows while investigating. They are the evidence.
- **Never edit the production database by hand.** Mistakes in entries are corrected in the app (edit, cancel, adjustment). The database refuses most hand edits anyway (ADR-0002).

## 1. Routine

| When | Task | How |
| --- | --- | --- |
| Daily (automatic) | Backups, health checks, integrity check — all driven by the one hPanel cron job that runs `schedule:run` every minute | Alerts come to you if any fails (O-33). The host renews the HTTPS certificate and applies its own security updates |
| Monthly | Dependabot pull requests (grouped): merge when green (`docs/prompts.md` §4); release if needed. Read any Dependabot alert e-mail at once | GitHub |
| Monthly | Check GitHub Actions minutes used (github.com/settings/billing); GitHub Free has 2,000 a month and stops CI when they run out | GitHub |
| Weekly | Glance at the monitoring dashboard | Monitoring service |
| Monthly | **Restore test** on staging, record it | Section 4 |
| Monthly | Check disk growth | The account's usage in hPanel (confirm in hPanel) |
| Quarterly | Review app users (disable leavers), the SSH keys in hPanel (Advanced → SSH Access), who knows which secret | `docs/secrets.md` §3 |
| Yearly | Rebuild drill (section 9), change the storage upload key, check support dates in `docs/stack-baseline.md`, check the host still offers a supported PHP version (ADR-0013) | |
| Before March 2028 | Upgrade to the next Laravel major version (Laravel 13 security support ends 17 March 2028) | Planned task |

Backups are the application command `zb:backup` (`mariadb-dump` + gzip + sodium encryption + upload to the object storage): **daily** (kept 35 days) and **monthly** (kept 7 years) (BR-OPS-01). The scheduler runs both; the storage key can upload but never delete, so the server cannot remove its own copies. Restore is tested monthly (section 4). Hostinger's own daily backups are an extra copy (D-4).

## 2. Release and deploy

On your laptop, after the pull requests for a release are merged into `main` and CI on `main` is green:

```bash
git checkout main && git pull
git tag v1.4.0 && git push origin v1.4.0       # CI builds and publishes the release
bash infra/hostinger/ship-release.sh v1.4.0 <staging-domain>
# client checks staging
bash infra/hostinger/ship-release.sh v1.4.0 <production-domain>
```

The deploy verifies the release's SHA-256 checksum, unpacks a new `releases/<version>/` folder, backs up the database, puts the site in maintenance mode, runs the migrations **over SSH**, adds any missing system data (company, settings keys, roles and permissions, system accounts, items; BR-OPS-11), builds the config, route and event caches, switches the `current` link to the new release, leaves maintenance mode, and checks `/up`, switching back by itself if that fails. The subdomain's document root points at `current/public`, so only the `public` folder is on the web (§18.1). Every deploy is appended to `deploy.log` in the application folder (outside `public_html`).

The site is served by **LiteSpeed**, not nginx: the only server file it reads is the `.htaccess` from `public/` — no nginx or systemd files are involved (T10.7).

Migrations run only from a deploy **over SSH** — never through phpMyAdmin or the web. `migrate:rollback` is never run in production; a bad migration is fixed forward with a new release (BR-OPS-10).

## 3. Roll back

Over SSH, in the application folder:

```bash
zb-deploy-release --list
zb-deploy-release --rollback
```

Code only: it repoints the `current` link to the previous release. The database keeps the newer migrations; this is safe because every migration must work with the previous release (BR-OPS-10). If a migration itself is wrong, fix it **forward** with a new migration and a new release; restoring a backup over production is the last resort (section 9) because it loses every entry since the backup.

## 4. Monthly restore test (BR-OPS-04)

1. Download the newest **daily** database backup and the current month's `files/<YYYY-MM>/` archives from the storage console (or with a temporary read-only key, S-06) to your computer.
2. Copy them to **staging** (or the laptop): `scp <backup-archive> files-<YYYY-MM>-* <staging>:/tmp/`.
3. Put the sodium **private** key in memory on staging only for the test. The private key is never stored on any server (BR-OPS-02).
4. Run `php artisan zb:restore` from the staging release folder. Its options take the private key, the database archive and the uploads archive. It decrypts, restores into a temporary database, checks every journal balances and runs `php artisan zb:verify`, restores the uploads into a temporary folder, then **drops the temporary database and deletes the key** from memory.
5. Record in `docs/release-checks.md`: date, backup file name, table count and trigger count (as `zb:restore` prints them), "every journal balances: yes", file count, "RESTORE OK". `zb:restore` also prints the journal totals on your screen; **do not copy them, or any name, into the file**: it is in git (`docs/data-handling.md` rule 9). Delete the downloaded files and the temporary read key.

## 5. Alerts and what to do

| Alert | Check | Usual fix |
| --- | --- | --- |
| **Site down** (`/up` not 200) | `zb-health-check`; `tail -50 ~/accounts/current/storage/logs/laravel-$(date +%F).log` (over SSH); the host's own status in hPanel | If it began right after a deploy: `zb-deploy-release --rollback`. Otherwise read the app log; the host's support can check the shared server |
| **Database or uploads backup missed/failed** | The Backups page (T7.6) and the dead-man monitor (healthchecks.io) | Storage key expired or changed → section 8; then re-run `php artisan zb:backup` and wait for the success ping |
| **Disk over 80%** | The account's usage in hPanel (confirm in hPanel) | Old releases are pruned to 5 by the deploy script; if uploads or the database grew, ask the host about the plan |
| **Certificate expires soon** | hPanel → SSL (confirm in hPanel) | The host renews its free certificate; if it has not, ask the host |
| **A long SSH or CLI command dies with a connection error** | The host drops an idle database connection after **20 seconds** (`wait_timeout`) | Keep the session busy, or run the job through the scheduler; re-run and continue |
| **`zb:verify` refuses to run: a protection trigger is missing** | `php artisan zb:verify` names the missing trigger. Creating a trigger works **only while the host's binary log is off** (`trust_function_creators` is off) | Ask the host to keep the binary log off (or set `trust_function_creators=ON`), then re-run the migration that creates the trigger. Do not recreate triggers by hand |
| **CI or main guard failed on `main`** (e-mail from GitHub Actions) | The run names the failing step: a test, or the main guard (a commit without a merged pull request, a protected file changed without `[spec-change]`, or `main` rewritten) | Do not release. Fix it with a normal task, or undo a wrong change with **Revert** on the merged pull request (a revert of a protected change needs `[spec-change]` in its title). Never rewrite `main`. If neither you nor a Claude session made the commit: section 10, and revoke the Claude GitHub App and your GitHub tokens |
| **Integrity check failed** (`zb:verify`) | Read its output: which journal, which voucher | **Stop entering data**, do not "fix" rows. Tell the developer; restore-test the last good backup on staging to compare. This means a bug or tampering |

**Known dependency — triggers.** The database rules (BR-SEC-11) and the seal-row balance check (BR-LED-11) are MariaDB triggers. Creating one works **only while the host's binary log is off**; `trust_function_creators` is off on this plan, and with the binary log on MariaDB refuses `CREATE TRIGGER`. The binary log is off on the "Unlimited" plan today. If a release's migrations ever fail with a trigger error, or `zb:verify` reports a missing trigger, the cause is the binary log: ask the host to turn it off again (or set `trust_function_creators=ON`), then re-run that migration. Do not drop, recreate or disable a trigger by hand.

## 6. Users

| Need | Do |
| --- | --- |
| New user | Owner: Users page → Add user (temporary password, must change at first login) |
| User leaves | Owner: Users page → Disable. Their sessions end at once. Then section 8 for any secret they knew |
| Forgot password | Owner: Users page → Reset password |
| The **only** Owner forgot the password | Over SSH: `php ~/accounts/current/artisan zb:reset-password <owner e-mail>` (prints a temporary password once; audited) |
| Lost two-factor phone and recovery codes | Over SSH: `php ~/accounts/current/artisan zb:reset-two-factor <e-mail>` (audited); the user enrols again |
| First Owner on a new server | `php ~/accounts/current/artisan zb:create-owner` (refuses if an Owner exists) |

## 7. Change a server setting

Over SSH, edit the application's `.env` (kept outside `public_html` and shared by every release), then `zb-deploy-release --refresh-config` (`docs/environments.md` §3).

## 8. Change a secret

| Secret | Steps |
| --- | --- |
| Storage upload key (S-05) | Console: create a new upload-only key (same three capabilities) → put it in the application's `.env` (outside `public_html`) → `php ~/accounts/current/artisan zb:backup` → check the Backups page shows success → delete the old key in the console |
| `APP_KEY` (S-01) | 1. Generate: `echo "base64:$(openssl rand -base64 32)"`. 2. In `.env`: move the old value into `APP_PREVIOUS_KEYS=`, put the new one in `APP_KEY=`. 3. `zb-deploy-release --refresh-config`. 4. Update both sealed copies. 5. After every user has logged in again and two-factor users have re-confirmed, clear `APP_PREVIOUS_KEYS` and refresh again |
| Backup key pair (S-02) | New **sodium** key pair; put the new public key where `zb:backup` reads it (`.env`, outside `public_html`); the next daily backup uses it. **Keep the old private key** until the last backup made with it expires (7 years for monthly) |
| Admin SSH key (S-09) | Add the new public key in hPanel → Advanced → SSH Access, log in with it in a new session, then remove the old one |
| Monitor URLs (S-12) | Create new checks, replace the URLs in the application's configuration, delete the old checks |
| Provider passwords (S-04, S-07, S-08, S-11) | In each console; check its access log and active sessions afterwards |

## 9. Server lost or destroyed (target: running again within 4 hours, O-36)

1. **Decide** (Owner + you): rebuild. Tell users to write entries on paper meanwhile.
2. Open or move to a Hostinger account (same plan), point the subdomain at it (lower the DNS TTL in advance if you can), and follow `docs/operations/server-setup.md` to create the database, the subdomain and SSH access.
3. Download the newest **daily** database backup and the current month's uploads archives (section 4, step 1) and copy them to the new account.
4. Put the data back **first**, while the new database is still empty: run `php artisan zb:restore` on the new account (section 4). It refuses to run if the database already has tables, so it can never overwrite live data.
5. Deploy the release that was live: `bash infra/hostinger/ship-release.sh <tag> <domain>` (it backs up the restored database first; there are no new migrations to run, and the system-data step finds nothing missing).
6. `zb-health-check`, open the app, compare today's cash and bank balances with the last known figures; the Accountant re-enters anything from paper made after the backup time.
7. Turn backups on and prove the storage cannot be deleted from the server (server-setup step 9). Record the time taken in `docs/release-checks.md`.

## 10. Suspected break-in

1. Do **not** wipe or reinstall the account yet; keep the evidence — take a `zb:backup` copy and note the host's own backups (hPanel → Files → Backups).
2. Change the hosting, storage and GitHub passwords from a clean computer (section 8); revoke the storage upload key **after** you have downloaded the latest backups.
3. Look: the app's audit log for unusual logins; `~/accounts/deploy.log`; unknown folders under `~/accounts/releases`; the account's login history in hPanel (confirm in hPanel).
4. Build a **new** account (section 9) from the repository and the last backup you trust. Do not reuse the old one.
5. Change `APP_KEY` and every user password; tell the client what was exposed (`docs/data-handling.md` §1 lists what is on the server).

## 11. A secret leaked (git, chat, e-mail, screenshot)

Change the secret at once (section 8). Removing it from git history does not help: assume it was copied. If it was the backup private key, also tell the client (old backups can be read by whoever has it until they expire).

## 12. Update server scripts

The account is managed through hPanel — there is nothing to provision. The only server scripts are the deploy, health and backup glue under `infra/hostinger/`; they ship with the release archive and the deploy script installs them, so they update with every deploy. If you ever need to install one by hand, unpack the release archive on the server and copy the changed file into the application folder over SSH.

## 13. Upgrades

| What | How |
| --- | --- |
| Hostinger plan / PHP version | The host upgrades PHP; pick the newest it offers in hPanel → Advanced → PHP Configuration, then re-check the app on staging |
| PHP / Laravel minor versions | Dependabot pull requests → CI → merge → release |
| Laravel major version | A planned task with its own card, tested on staging; before March 2028 |
| MariaDB major version | The host upgrades MariaDB; the app refuses to start on older than 10.2 (Q-9), so confirm staging still passes after a host upgrade |
