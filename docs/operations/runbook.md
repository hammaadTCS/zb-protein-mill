# Runbook

What to do, routinely and when something goes wrong. Commands run on the server as your admin user unless stated. Two rules apply to every section:

- **Never delete** logs, backups, releases marked `.failed` or database rows while investigating. They are the evidence.
- **Never edit the production database by hand.** Mistakes in entries are corrected in the app (edit, cancel, adjustment). The database refuses most hand edits anyway (ADR-0002).

## 1. Routine

| When | Task | How |
| --- | --- | --- |
| Daily (automatic) | Backups, health checks, integrity check, certificate renewal, security updates | Alerts come to you if any fails (O-33) |
| Monthly | Dependabot pull requests (grouped): merge when green (`docs/prompts.md` §4); release if needed. Read any Dependabot alert e-mail at once | GitHub |
| Monthly | Check GitHub Actions minutes used (github.com/settings/billing); GitHub Free has 2,000 a month and stops CI when they run out | GitHub |
| Weekly | Glance at the monitoring dashboard | Monitoring service |
| Monthly | **Restore test** on staging, record it | Section 4 |
| Monthly | Check disk growth | `df -h /` |
| Quarterly | Review app users (disable leavers), SSH keys in `~/.ssh/authorized_keys`, who knows which secret | `docs/secrets.md` §3 |
| Yearly | Rebuild drill (section 9), change the storage upload key, check support dates in `docs/stack-baseline.md`, re-check that PHP security updates still arrive (ADR-0003) | |
| Before March 2028 | Upgrade to the next Laravel major version (Laravel 13 security support ends 17 March 2028) | Planned task |

## 2. Release and deploy

On your laptop, after the pull requests for a release are merged into `main` and CI on `main` is green:

```bash
git checkout main && git pull
git tag v1.4.0 && git push origin v1.4.0       # CI builds and publishes the release
bash infra/local/ship-release.sh v1.4.0 <admin>@<staging-domain>
# client checks staging
bash infra/local/ship-release.sh v1.4.0 <admin>@<production-domain>
```

`zb-deploy-release` backs up the database first, migrates, adds any missing system data (company, settings keys, roles and permissions, system accounts, items; BR-OPS-11), switches, smoke-tests, and switches back by itself if `/up` fails. Every deploy is written to `/srv/zb-mill/deploy.log`.

## 3. Roll back

```bash
sudo zb-deploy-release --list
sudo zb-deploy-release --rollback
```

Code only. The database keeps the newer migrations; this is safe because every migration must work with the previous release (BR-OPS-10). If a migration itself is wrong, fix it **forward** with a new migration and a new release; restoring a backup over production is the last resort (section 9) because it loses every entry since the backup.

## 4. Monthly restore test (BR-OPS-04)

1. Download the newest **daily** database backup and the current month's `files/<YYYY-MM>/` archives from the storage console (or with a temporary read-only key, S-06), to your computer.
2. Copy them to **staging**: `scp production-db-*.dump.age <admin>@<staging>:/tmp/` and the files folder likewise.
3. Put the private key into memory on staging only for the test:
   ```bash
   sudo install -m 0600 /dev/stdin /dev/shm/zb-backup-key.txt     # paste the key, then Ctrl-D
   sudo zb-restore --identity /dev/shm/zb-backup-key.txt --db /tmp/production-db-<…>.dump.age \
        --files-dir /tmp/files-<YYYY-MM> --verify
   ```
4. It restores into a temporary database, checks every journal balances, runs `php artisan zb:verify`, restores the uploads into a temporary folder, then **drops the temporary database and deletes the key** from memory.
5. Record in `docs/release-checks.md`: date, backup file name, table count and trigger count (as `zb-restore` prints them), "every journal balances: yes", file count, "RESTORE OK". `zb-restore` also prints the journal totals on your screen; **do not copy them, or any name, into the file**: it is in git (`docs/data-handling.md` rule 9). Delete the downloaded files: `sudo shred -u /tmp/production-db-*.age; sudo rm -rf /tmp/files-*`. Delete the temporary read key if you made one.

## 5. Alerts and what to do

| Alert | Check | Usual fix |
| --- | --- | --- |
| **Site down** (`/up` not 200) | `sudo zb-health-check`; `sudo systemctl status nginx php8.5-fpm postgresql`; `sudo tail -50 /var/log/nginx/zb-mill.error.log`; `sudo tail -50 /srv/zb-mill/shared/storage/logs/laravel-$(date +%F).log` | Restart the failed service (`sudo systemctl restart …`). If it began right after a deploy: `sudo zb-deploy-release --rollback` |
| **Database backup missed/failed** | `journalctl -u zb-mill-backup-db -n 50`; `cat /var/lib/zb-mill/status/backup-db.json` | Storage key expired or changed → section 8; disk full → below; then `sudo systemctl start zb-mill-backup-db` and wait for the success ping |
| **Uploads backup failed** | `journalctl -u zb-mill-backup-files -n 50` | Same as above. A failed run is repeated in full next time |
| **Disk over 80%** | `sudo du -xh / --max-depth=2 \| sort -h \| tail`; `sudo journalctl --vacuum-time=30d` | Logs rotate themselves; old releases are pruned to 5. If uploads or the database grew, resize the disk with the provider |
| **Certificate expires in < 14 days** | `sudo certbot renew --dry-run`; `systemctl status certbot.timer` | Usually DNS changed or port 80 blocked. Fix, then `sudo certbot renew` |
| **Clock not synchronised** | `timedatectl timesync-status` | `sudo systemctl restart systemd-timesyncd` (or chrony if installed) |
| **CI or main guard failed on `main`** (e-mail from GitHub Actions) | The run names the failing step: a test, or the main guard (a commit without a merged pull request, a protected file changed without `[spec-change]`, or `main` rewritten) | Do not release. Fix it with a normal task, or undo a wrong change with **Revert** on the merged pull request (a revert of a protected change needs `[spec-change]` in its title). Never rewrite `main`. If neither you nor a Claude session made the commit: section 10, and revoke the Claude GitHub App and your GitHub tokens |
| **Integrity check failed** (`zb:verify`) | Read its output: which journal, which voucher | **Stop entering data**, do not "fix" rows. Tell the developer; restore-test the last good backup on staging to compare. This means a bug or tampering |

## 6. Users

| Need | Do |
| --- | --- |
| New user | Owner: Users page → Add user (temporary password, must change at first login) |
| User leaves | Owner: Users page → Disable. Their sessions end at once. Then section 8 for any secret they knew |
| Forgot password | Owner: Users page → Reset password |
| The **only** Owner forgot the password | Server: `sudo -u zbmill php /srv/zb-mill/current/artisan zb:reset-password <owner e-mail>` (prints a temporary password once; audited) |
| Lost two-factor phone and recovery codes | Server: `sudo -u zbmill php /srv/zb-mill/current/artisan zb:reset-two-factor <e-mail>` (audited); the user enrols again |
| First Owner on a new server | `sudo -u zbmill php /srv/zb-mill/current/artisan zb:create-owner` (refuses if an Owner exists) |

## 7. Change a server setting

`sudoedit /srv/zb-mill/shared/.env`, then `sudo zb-deploy-release --refresh-config` (`docs/environments.md` §3).

## 8. Change a secret

| Secret | Steps |
| --- | --- |
| Storage upload key (S-05) | Console: create a new key (same three capabilities) → edit `/etc/zb-mill/secrets/rclone.conf` → `sudo systemctl start zb-mill-backup-db` → `sudo zb-backup-verify-offsite` (three PASS) → delete the old key in the console |
| `APP_KEY` (S-01) | 1. Generate: `echo "base64:$(openssl rand -base64 32)"`. 2. In `.env`: move the old value into `APP_PREVIOUS_KEYS=`, put the new one in `APP_KEY=`. 3. `sudo zb-deploy-release --refresh-config`. 4. Update both sealed copies. 5. After every user has logged in again and two-factor users have re-confirmed, clear `APP_PREVIOUS_KEYS` and refresh again |
| Backup key pair (S-02) | New pair with `make-backup-key.sh`; replace the line in `/etc/zb-mill/backup-recipients.txt`; next hourly backup uses it. **Keep the old private key** until the last backup made with it expires (7 years for monthly) |
| Admin SSH key (S-09) | Add the new public key to `~/.ssh/authorized_keys`, log in with it in a new session, then remove the old line |
| Monitor URLs (S-12) | Create new checks, replace the `.url` files, delete the old checks |
| Provider passwords (S-04, S-07, S-08, S-11) | In each console; check its access log and active sessions afterwards |

## 9. Server lost or destroyed (target: running again within 4 hours, O-36)

1. **Decide** (Owner + you): rebuild. Tell users to write entries on paper meanwhile.
2. New VPS, same region, **Ubuntu 26.04**; point DNS to the new address (lower the DNS TTL in advance if you can).
3. `docs/operations/server-setup.md` steps 3 to 8 (new `APP_KEY` is fine: users simply log in again; two-factor users re-enrol unless you put the old `APP_KEY` back from the sealed copy — prefer putting it back).
4. Download the newest **hourly** database backup and the current month's uploads archives (section 4, step 1) and copy them to the new server.
5. Put the data back **first**, while the new database is still empty (step 30 created it empty):
   ```bash
   sudo install -m 0600 /dev/stdin /dev/shm/zb-backup-key.txt      # paste the private key, Ctrl-D
   sudo zb-restore --identity /dev/shm/zb-backup-key.txt --db /tmp/<latest>.dump.age \
        --files-dir /tmp/files-<YYYY-MM> --into-empty-production
   ```
   It refuses to run if the database already has tables, so it can never overwrite live data.
6. Deploy the release that was live: `bash infra/local/ship-release.sh <tag> <admin>@<domain>` (it backs up the restored database first; there are no new migrations to run, and the system-data step finds nothing missing).
7. `sudo zb-health-check`, open the app, compare today's cash and bank balances with the last known figures; the Accountant re-enters anything from paper made after the backup time.
8. Step 9 of server setup (backups on, verify off-site). Record the time taken in `docs/release-checks.md`.

## 10. Suspected break-in

1. Do **not** shut down or wipe the server yet; take a provider snapshot (evidence).
2. Change provider, storage and GitHub passwords from a clean computer (section 8); revoke the storage upload key **after** you have downloaded the latest backups.
3. Look: `last -a | head`, `sudo journalctl -u ssh --since "-7 days" | grep Accepted`, `/srv/zb-mill/deploy.log`, unknown files in `/srv/zb-mill/releases`, the app's audit log for unusual logins.
4. Build a **new** server (section 9) from the repository and the last backup you trust. Do not reuse the old server.
5. Change `APP_KEY` and every user password; tell the client what was exposed (`docs/data-handling.md` §1 lists what is on the server).

## 11. A secret leaked (git, chat, e-mail, screenshot)

Change the secret at once (section 8). Removing it from git history does not help: assume it was copied. If it was the backup private key, also tell the client (old backups can be read by whoever has it until they expire).

## 12. Update server scripts

Each release also publishes `zb-mill-infra-<tag>.tar.gz`. When its `infra/` changed:

```bash
mkdir -p /tmp/zb-infra && tar -xzf zb-mill-infra-<tag>.tar.gz -C /tmp/zb-infra
sudo bash /tmp/zb-infra/infra/provision/setup.sh /root/zb-server.conf 60     # commands, units
# or the specific step that changed (30 postgres, 40 php, 50 nginx)
```

Keep `/root/zb-server.conf` (no secrets in it) if you plan to do this; otherwise recreate it from `server.conf.example`.

## 13. Upgrades

| What | How |
| --- | --- |
| Ubuntu packages | Automatic (security). Run `sudo apt update && sudo apt full-upgrade` monthly for non-security updates, after a backup |
| PHP / Laravel minor versions | Dependabot pull requests → CI → merge → release |
| Laravel major version | A planned task with its own card, tested on staging; before March 2028 |
| Ubuntu next LTS (2028) | Build a new server with the new release, restore into it, switch DNS. Do not upgrade the live server in place |
| PostgreSQL major version | Comes with the next Ubuntu LTS: same new-server method (pg_dump/restore is what backups already use) |
