# Server setup (staging and production)

Step-by-step, from nothing to a working, backed-up server. Do **staging first** (task T8.3), then production the same way (T8.6). Each step says what you do and how you know it worked. Allow half a day the first time.

You need, on your laptop: `ssh` and `scp` (already in Linux Mint), `gh` (installed by `scripts/setup-local.sh`; run `gh auth login` once) for releases, and `age` for the backup key: `sudo apt install age`.

---

## Step 0. Accounts, in the company's name (O-30, O-32, O-33, O-34)

| Account | Used for | Notes |
| --- | --- | --- |
| VPS provider | The server | Ubuntu **26.04 LTS** image must be offered. Proposed size 2 vCPU, 4 GB RAM, 80 GB SSD; region per O-34 |
| Domain + DNS | `accounts.<company-domain>` or similar | Turn on registrar lock and two-factor |
| Object storage | Backups | Worked example below: Backblaze B2. Must support versioning or Object Lock and keys without delete rights |
| Monitoring | Uptime + backup pings | Any service with "dead man's switch" checks (for example healthchecks.io) plus an HTTP uptime check |
| Password manager | Every secret in `docs/secrets.md` | Shared vault with the Owner |

Turn on two-factor login for every account. Record each one in the password manager.

## Step 1. Create the server

1. Create the VPS with **Ubuntu 26.04 LTS**, your SSH public key (`~/.ssh/id_ed25519.pub`; make one with `ssh-keygen -t ed25519` if needed), and the provider's firewall open for 22, 80, 443 (or no provider firewall; `ufw` handles it).
2. Note the IPv4 (and IPv6 if given) address.
3. Turn on the provider's automatic snapshots if they are cheap. They are a convenience, not the backup.

## Step 2. DNS

Create an `A` record for your domain to the IPv4 address (and `AAAA` to the IPv6 address if the server has one). Wait until `dig +short <domain>` on your computer shows the address. The certificate step fails without this.

## Step 3. Copy the setup files and fill in the settings

On your computer, in the repository:

```bash
scp -r infra .env.production.example root@<server-ip>:/root/zb-setup/
ssh root@<server-ip>        # some providers use "ubuntu@" and sudo instead of root
cd /root/zb-setup
cp infra/provision/server.conf.example /root/zb-server.conf
nano /root/zb-server.conf   # ENV_NAME, DOMAIN, LETSENCRYPT_EMAIL, ADMIN_USER, ADMIN_SSH_PUBLIC_KEY, RCLONE_REMOTE
```

`ADMIN_SSH_PUBLIC_KEY` is the **public** key line from your computer. The script refuses example values and private keys.

## Step 4. Run provisioning steps 10 to 60

```bash
bash infra/provision/setup.sh /root/zb-server.conf 10 20 30 40 50 60
```

| Step | Does | You see at the end |
| --- | --- | --- |
| 10 base | Updates, packages (PHP 8.5, PostgreSQL 18, nginx…), UTC clock, swap, firewall, automatic updates | `ufw status` with 22 (LIMIT), 80, 443 |
| 20 users | Your admin login with sudo, service users, SSH hardening | A warning to test a new login (do it now, step 5) |
| 30 postgres | Settings, peer authentication, roles, database | "zbmill can log in as zb_app" |
| 40 php | PHP-FPM pool as `zbmill`, PHP settings | "pool zb-mill listening" |
| 50 nginx-tls | Certificate from Let's Encrypt, site, headers | "https://<domain> is configured" |
| 60 app-layout | Folders, `.env` with a new `APP_KEY`, `zb-*` commands, timers | A reminder to copy `APP_KEY` |

If a step stops, read the message, fix the cause, and run the same step again: every step is safe to repeat.

## Step 5. Test your login, then lock the door behind you

1. **Keep the root session open.** In a new terminal: `ssh <admin-user>@<domain>`. It must work with your key.
2. Set your sudo password: `sudo passwd <admin-user>` from the root session, then `sudo -v` in the new session.
3. Only when both work, close the root session. From now on root cannot log in over SSH and passwords are refused.

## Step 6. Backup key pair (O-37) and APP_KEY copies

On **your computer** (not the server):

```bash
bash infra/local/make-backup-key.sh
```

1. Put the private key file in the password manager (secure note) **and** print it; the Owner keeps the printed copy sealed. Then delete the file from your computer as the script says.
2. On the server, put the **public** key (one line, `age1…`) in the recipients file:
   ```bash
   sudo nano /etc/zb-mill/backup-recipients.txt
   ```
3. Copy `APP_KEY` the same way (password manager + sealed paper with the Owner):
   ```bash
   sudo grep ^APP_KEY= /srv/zb-mill/shared/.env
   ```
   Staging and production have **different** keys.

## Step 7. Off-site storage (worked example: Backblaze B2)

Do this in the storage provider's web console, logged in with the company account:

1. Create a **private** bucket, for example `zb-mill-backups-<random>` (one bucket per environment).
2. Keep **all versions** (B2 keeps versions by default) and turn on **Object Lock** with a default retention of **30 days** in **governance** mode.
3. Add lifecycle rules (B2: "Lifecycle Settings", custom rules) with these file-name prefixes:

   | Prefix | Hide after (days from upload) | Delete after (days from hiding) |
   | --- | --- | --- |
   | `db/hourly/` | 2 | 1 |
   | `db/daily/` | 35 | 1 |
   | `db/monthly/` | 2557 (7 years, O-35) | 1 |
   | `files/` | 100 | 1 |
   | `canary/` | 1 | 1 |

   Files under Object Lock cannot be removed before their 30 days, whatever the rule says.
4. Create an **application key** restricted to this bucket with only: `listBuckets`, `listFiles`, `writeFiles`. **Not** `deleteFiles`, `readFiles` or `bypassGovernance`. Copy the key ID and key once (they are shown once).
5. On the server, create the rclone file (`b2` is rclone's native Backblaze backend):
   ```bash
   sudo nano /etc/zb-mill/secrets/rclone.conf
   ```
   ```ini
   [offsite]
   type = b2
   account = <key ID>
   key = <application key>
   hard_delete = false
   ```
   and make sure `RCLONE_REMOTE=offsite:<bucket name>` is in `/etc/zb-mill/zb-mill.conf`.

Using another provider: any S3-compatible storage with versioning/Object Lock and a key **without delete permission** works; write the matching rclone remote. Step 9 proves it either way.

## Step 8. Monitoring (O-33)

1. In the monitoring service create three "dead man's switch" checks:

   | Check | Expected every | Grace |
   | --- | --- | --- |
   | backup-db | 1 hour | 1 hour |
   | backup-files | 1 day | 2 hours |
   | health | 15 minutes | 15 minutes |

2. Put each ping URL in its own file on the server (one line each):
   ```bash
   sudo nano /etc/zb-mill/secrets/healthchecks/backup-db.url
   sudo nano /etc/zb-mill/secrets/healthchecks/backup-files.url
   sudo nano /etc/zb-mill/secrets/healthchecks/health.url
   ```
3. Create an HTTP uptime check for `https://<domain>/up` every 5 minutes (it answers once the app is deployed).
4. Send alerts to the Owner and to you (phone and e-mail).

## Step 9. Turn on backups and prove the storage cannot be deleted from the server

```bash
sudo bash /root/zb-setup/infra/provision/setup.sh /root/zb-server.conf 70
```

It refuses a private key in the recipients file, runs the first backup, then `zb-backup-verify-offsite` must print three `PASS` lines. Then do the manual check it prints (versioning/Object Lock on, lifecycle rules as above) and keep a screenshot with `docs/release-checks.md`.

## Step 10. First deploy

From your laptop, after CI has published a release (runbook §2; START-HERE, Step 10):

```bash
bash infra/local/ship-release.sh v0.1.0 <admin-user>@<domain>
```

The deploy also creates the system data the app needs (company, settings keys with no values, roles and permissions, system accounts, items; BR-OPS-11). It creates no users. Then create the first Owner (task T5.2 builds this command):

```bash
sudo -u zbmill php /srv/zb-mill/current/artisan zb:create-owner
```

Open `https://<domain>`: the login page appears with a valid certificate. Log in; you must set a new password at once.

## Step 11. Check everything

```bash
sudo zb-health-check                      # "OK: disk, services, clock, certificate, backups…, app"
curl -sI https://<domain>/up | grep -iE 'strict-transport|x-frame|content-security'
sudo zb-deploy-release --list             # one release, marked (live)
```

Then tick the server rows of `docs/release-checks.md` (T8.1): BR-SEC-01, BR-SEC-10, BR-SEC-12, BR-OPS-01 to 09.

## Step 12. Restore drill (before go-live)

On **staging**, restore a production-format backup as in `docs/operations/runbook.md` → "Monthly restore test". Before go-live also do the full rebuild drill once (runbook → "Server lost"), timing it against the 4-hour target (O-36).

## Afterwards: delete the setup copy

```bash
sudo rm -rf /root/zb-setup /root/zb-server.conf
```

Everything the server needs is now in `/etc/zb-mill/`, `/usr/local/sbin/zb-*` and `/srv/zb-mill/`. Future script updates come with each release's `zb-mill-infra-<tag>.tar.gz` (runbook → "Update server scripts").
