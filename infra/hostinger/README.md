# `infra/hostinger` — deploy, health and backup glue for Hostinger shared hosting

These are the live server scripts for the **Hostinger "Unlimited" shared hosting** account
(ADR-0013). There is no root, no systemd, no nginx and no certbot: everything runs as the
account's own user over SSH, and the application lives in `~/accounts` (outside `public_html`).
The retired PostgreSQL-VPS scripts are kept for history under `infra/history/`.

| File | Runs where | What it does |
| --- | --- | --- |
| `ship-release.sh` | your computer | download the CI release, check its SHA-256, copy it to the host, run `zb-deploy-release` |
| `make-backup-key.sh` | your computer, once | make the sodium key pair that protects the backups (BR-OPS-02) |
| `zb-deploy-release` | the host (over SSH) | backup, maintenance mode, migrate, system data, caches, atomic switch, smoke test, rollback |
| `zb-health-check` | the host (over SSH or the scheduler) | check `/up`, the certificate, disk and the last backup; alert the monitor |
| `zb-lib.sh` | sourced by the two commands | shared config loading, monitor pings |

## First-time setup (once per account, over SSH)

The account has no provisioning step — hPanel does it. Deploy scripts are refreshed from each
release, so copy them into place once:

```bash
mkdir -p ~/accounts/bin
# from a checkout of this repository, or from the first release archive:
scp infra/hostinger/zb-* <host>:~/accounts/bin/
ssh <host> 'chmod 0755 ~/accounts/bin/zb-*; chmod 0644 ~/accounts/bin/zb-lib.sh'
```

Then create `~/accounts/zb-mill.conf` (outside `public_html`; no secrets in it):

```ini
APP_BASE=/home/<account>/accounts
DOMAIN=accounts.example.com
PHP_BIN=php
KEEP_RELEASES=5
# optional: the folder the app's zb:backup writes its status files to
# STATUS_DIR=/home/<account>/accounts/shared/storage/app/backup-status
```

## Release

From your computer, after the `vX.Y.Z` tag is built by CI:

```bash
bash infra/hostinger/ship-release.sh v1.4.0 staging.example.com
# client checks staging, then the same tag to production:
bash infra/hostinger/ship-release.sh v1.4.0 accounts.example.com
```

The deploy verifies the release's SHA-256, unpacks a new `~/accounts/releases/<version>/`, backs
the database up with `php artisan zb:backup`, puts the site in maintenance mode, runs the
migrations **over SSH**, runs `SystemDataSeeder` (BR-OPS-11), builds the config, route and event
caches, switches the `current` link to the new release, leaves maintenance mode and checks `/up`,
switching back by itself if that fails (BR-OPS-10). The subdomain's document root points at
`current/public`, so only the `public` folder is on the web. Roll back with:

```bash
ssh <host> '~/accounts/bin/zb-deploy-release --list'
ssh <host> '~/accounts/bin/zb-deploy-release --rollback'    # code only; repoints current
```

## Health check

```bash
ssh <host> '~/accounts/bin/zb-health-check'    # prints OK or the list of problems
```

It checks `/up`, the host's free certificate (expiry within 14 days), disk use where the host
exposes it, the age of the last `zb:backup` (26 hours), and the daily `zb:verify` file. It pings
the monitor URL from `HC_DIR/health.url` (or `MONITOR_URL`); a missing URL only logs a warning
(monitoring is set up at go-live, O-33).

## hPanel cron

There is **one** cron job, every minute:

```
* * * * *  cd ~/accounts/current && /usr/local/bin/php artisan schedule:run >> ~/accounts/cron.log 2>&1
```

Everything the system schedules goes through the Laravel scheduler: `zb:verify` (daily),
`zb:backup` (daily and monthly) and the health check (every 15 minutes). Set the PHP path in
hPanel → Advanced → Cron Jobs; no other server timers exist (D-7). Backups run from the
application (`mariadb-dump` + gzip + sodium → object storage, ADR-0015) — there are no backup
shell scripts any more.
