# `infra/history` — retired PostgreSQL-VPS scripts (history only)

These are the server scripts of the days when the system ran on its own **Ubuntu VPS**, before the
move to the business's **Hostinger "Unlimited" shared hosting** account (ADR-0013). They are kept
for history and for anyone reading an old runbook or release; **nothing here runs any more.**

What they did, and what replaced them:

| Retired here | What it did | Replaced by |
| --- | --- | --- |
| `bin/zb-backup-db`, `bin/zb-backup-files`, `bin/zb-backup-verify-offsite` | `pg_dump`/tar piped through **age**, uploaded with **rclone** to object storage, run by **systemd timers** | the application command `zb:backup` (`mariadb-dump` + gzip + **sodium** + upload-only key), run by the scheduler (ADR-0015) |
| `bin/zb-restore` | decrypt an age backup and `pg_restore` it | the application command `zb:restore` (ADR-0015) |
| `bin/zb-deploy-release` | unpack a release, migrate as `zb_migrator`, apply Postgres grants, reload **PHP-FPM**, switch the link | `infra/hostinger/zb-deploy-release` (single MariaDB user, no root, no service reload) |
| `bin/zb-health-check` | start SQL, then check **nginx**, **systemd** timers, **postgresql**, the Let's Encrypt file | `infra/hostinger/zb-health-check` (checks `/up`, the host's certificate, disk, last backup) |
| `bin/zb-lib.sh` | shared helpers, installed to `/usr/local/lib/zb-mill/` | `infra/hostinger/zb-lib.sh` |
| `files/postgres/*` | PostgreSQL roles, schema template, `pg_hba`/`pg_ident`, app grants | the MariaDB migrations and triggers in the application (ADR-0002 replaced by ADR-0016) |
| `files/nginx/*`, `files/systemd/*`, `files/certbot/*`, `files/logrotate/*`, `files/php/*`, `files/ssh/*`, `files/apt/*` | nginx site and snippets, systemd units and timers, certbot reload hook, log rotation, PHP-FPM pool, SSH hardening, unattended upgrades | nothing: shared hosting has no root, no nginx, no systemd and no certbot; security headers are set by the application (D-8) and HTTPS uses the host's free certificate |
| `provision/*` | build a new Ubuntu VPS from scratch, with peer-authenticated database roles | hPanel, `docs/operations/server-setup.md` and the scripts under `infra/hostinger/` |

The live scripts are `infra/hostinger/`; the current rules are in `docs/cards/M10.md`,
`docs/spec.md` §18 and ADR-0013 / ADR-0015 / ADR-0016.
