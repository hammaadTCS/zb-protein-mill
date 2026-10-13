# infra/ – everything that runs on the servers

Protected folder: only you change it, through a `[spec-change]` (START-HERE, Step 9). Claude Code never edits it and never runs it against a server.

| Path | What it is | Installed on the server as |
| --- | --- | --- |
| `provision/setup.sh`, `provision/steps/*.sh` | Builds a server from a fresh Ubuntu 26.04 (steps 10–70) | run once, safe to repeat |
| `provision/server.conf.example` | Settings for provisioning (domain, admin user, your public SSH key…) | `/root/zb-server.conf` (no secrets) |
| `files/postgres/` | PostgreSQL settings, peer authentication, roles, grants | `/etc/postgresql/18/main/…`, `/usr/local/lib/zb-mill/postgres/` |
| `files/php/` | PHP-FPM pool (`zbmill` user) and PHP settings | `/etc/php/8.5/fpm/pool.d/zb-mill.conf`, `conf.d/99-zb-mill.ini` |
| `files/nginx/` | Site, security headers, server tokens off | `/etc/nginx/sites-available/zb-mill.conf`, `snippets/`, `conf.d/` |
| `files/systemd/` | Timers: scheduler (1 min), DB backup (hourly), files backup (daily), health (15 min); PHP-FPM umask | `/etc/systemd/system/` |
| `files/ssh/` | SSH hardening (keys only, one user) | `/etc/ssh/sshd_config.d/10-zb-mill.conf` |
| `files/apt/` | Automatic security updates, reboot time | `/etc/apt/apt.conf.d/` |
| `files/logrotate/`, `files/certbot/` | PHP-FPM log rotation; nginx reload after certificate renewal | `/etc/logrotate.d/`, `/etc/letsencrypt/renewal-hooks/deploy/` |
| `bin/zb-deploy-release` | Deploy a CI-built release, rollback, list, refresh config | `/usr/local/sbin/` |
| `bin/zb-backup-db`, `bin/zb-backup-files` | Encrypted off-site backups | `/usr/local/sbin/` (run by timers) |
| `bin/zb-restore` | Restore test (staging) and disaster recovery | `/usr/local/sbin/` |
| `bin/zb-backup-verify-offsite` | Proves the server cannot delete backups | `/usr/local/sbin/` |
| `bin/zb-health-check` | Disk, services, clock, certificate, backups, app | `/usr/local/sbin/` (run by timer) |
| `bin/zb-lib.sh` | Shared functions | `/usr/local/lib/zb-mill/zb-lib.sh` |
| `local/make-backup-key.sh` | Make the backup key pair **on your computer** | not installed |
| `local/ship-release.sh` | Download a release, check it, copy it, deploy it, **from your computer** | not installed |
| `tests/` | Tests for all of the above with real PostgreSQL, age, rclone, nginx, PHP-FPM | run in CI (`infra` job) or `sudo bash infra/tests/run-all.sh` on a throwaway machine |

How to use it: `docs/operations/server-setup.md` (first time) and `docs/operations/runbook.md` (everything after).

## What the tests prove (and what they cannot)

`sudo bash infra/tests/run-all.sh` — last run 13 October 2026 in an Ubuntu 24.04 container: **278 checks passed, 0 failed**.

| Test | Proves |
| --- | --- |
| `test-guardrails.sh` | The protection hook blocks Claude from protected files (including `rm -rf docs` and `docs/client/`) and from changing `main` (commit, merge, reset, push to main, tags, merges, GitHub writes), while work on and pushes of its task branch still run; the protected-files check fails untagged changes on pull requests and branches; the main guard fails direct pushes, force pushes and protected changes without `[spec-change]`; the CI check fails untagged changes; the secret scan catches keys, `APP_KEY`, monitor URLs, passwords, `.env` |
| `test-provision.sh` | Settings validation (refuses example values and private keys), no placeholder left in rendered files, APP_KEY format, backup key file checks |
| `test-postgres.sh` | Peer mapping (each OS user → one role), no TCP listener, the web role cannot delete/alter/truncate/disable triggers/edit journals, unbalanced journals refused, backup role read-only, triggers survive dump and restore, PHP connects through the socket with no password |
| `test-ci-privileges.sh` | The CI privilege check passes with the real grants and **fails** when grants are too wide |
| `test-backup-restore.sh` | Encrypted hourly/daily/monthly copies, nothing readable without the key, failure status and no partial files, incremental uploads chain with retry after a failed upload, test restore with checks and clean-up, refusal to overwrite production, disaster-recovery restore into an empty database, delete-protection check passes/fails correctly |
| `test-deploy.sh` | Checksum and unsafe-path refusal, shared `.env`/storage links, `APP_KEY` cache readable by the web user only, web user cannot change code, backup before migrate, migrate as `zbdeploy`, system data added right after the migrations (BR-OPS-11) and a failure there leaves the old release live, grants applied, maintenance mode, failed migration leaves the old release live, failed smoke test rolls back, backup failure stops the deploy, rollback, lock, old releases pruned |
| `test-health-check.sh` | Each problem is reported: certificate, stopped service, old backup, app down, full disk, failed or stale ledger integrity check |
| `test-web.sh` | nginx config valid, HTTP→HTTPS, unknown hosts dropped, headers present, no version leaks, only `index.php` runs, `.env` never served, 8 MB limit, PHP runs as `zbmill` with `exec`/`proc_open` disabled, assets cached and compressed, no query strings in logs |

**Not provable here, verified on staging instead (T8.3):** Ubuntu 26.04 package names and versions in a real install (taken from the official package index, see `docs/stack-baseline.md`), PostgreSQL 18 (CI runs the privilege check on 18; the local tests ran on 16), nginx 1.28 `http2 on;` (the local test removes it on 1.24), systemd timers actually firing, Let's Encrypt issuance, UFW, SSH hardening on a live connection (the config was validated with `sshd -t` and the first-value-wins order was checked with `sshd -T`), the real storage provider's delete protection (`zb-backup-verify-offsite` proves it on day one).
