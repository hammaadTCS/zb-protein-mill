# Server setup (staging and production)

Step-by-step, from nothing to a working, backed-up account on the business's existing **Hostinger "Unlimited" shared hosting** account. Do **staging first** (task T8.3), then production the same way (T8.6). Each step says what you do in hPanel or over SSH and how you know it worked. Allow half a day the first time.

There is no root, no server operating system and nothing to provision: hPanel manages PHP, MariaDB, the certificate and the firewall. You need, on your laptop: `ssh` and `scp` (already in Linux Mint), and `gh` (installed by `scripts/setup-local.sh`; run `gh auth login` once) for releases.

---

## Step 0. Accounts, in the company's name (O-30, O-32, O-33, O-34)

| Account | Used for | Notes |
| --- | --- | --- |
| Hostinger "Unlimited" account (already owned by the business) | The hosting | PHP **8.5 (LiteSpeed)** and **MariaDB 11.8**; the application runs in a folder outside `public_html`. Two-step login on (BR-SEC-10) |
| Domain + DNS | `accounts.<company-domain>` or similar | Already at Hostinger. Registrar lock and two-factor |
| Object storage | Backups | Worked example below: Backblaze B2. Must support versioning or Object Lock and keys without delete rights |
| Monitoring | Uptime + backup pings | Any service with "dead man's switch" checks (for example healthchecks.io) plus an HTTP uptime check |
| Password manager | Every secret in `docs/secrets.md` | Shared vault with the Owner |

Turn on two-factor login for every account. Record each one in the password manager.

## Step 1. Turn on SSH and note the versions (hPanel)

1. hPanel → Advanced → **SSH Access**: turn it on and add your SSH public key (`~/.ssh/id_ed25519.pub`; make one with `ssh-keygen -t ed25519` if needed). There is no root and no `sudo`; every command runs as the account's own user.
2. Note the PHP version (Advanced → PHP Configuration) and the MariaDB version (Databases). The system needs **PHP 8.5 (LiteSpeed)** and **MariaDB 11.8** (InnoDB, `utf8mb4`); the app refuses to start on MariaDB older than 10.2 or with wrong settings (Q-9, T10.1).
3. Keep the host's malware scanner on and PHP on the newest version hPanel offers (BR-SEC-10).

## Step 2. Create the database and user (hPanel → Databases)

1. Create one database and one user (the host creates them together). It gives **one database user with all rights on its own database only** (D-43, BR-SEC-12) — there is no separate web and migrator user as on the VPS.
2. Give the user a strong random password; it goes in `.env` outside `public_html`, never in the repository (BR-SEC-12). Note the database name (`DB_DATABASE`), the user and the password.
3. Turn **remote database access off**; phpMyAdmin is reached only through hPanel (BR-SEC-10).

**Settings the app makes itself, so you do not set them.** The host's `sql_mode` lacks strict mode and `ONLY_FULL_GROUP_BY`, and its default isolation is `READ COMMITTED`; the app sets its own session `sql_mode`, isolation and `+00:00` time zone on every connection (T10.1). An idle database connection is dropped after **20 seconds** (`wait_timeout`), so anything slow runs through the scheduler, not an idle shell — see the runbook.

**Known dependency — triggers** (BR-SEC-12). The database rules and the seal-row check are triggers. Creating a trigger works **only while the host's binary log is off** (`trust_function_creators` is off on this plan). The binary log is off today; if it is ever turned on, migrations that create triggers fail, and the runbook records the recovery step.

## Step 3. Set the PHP version and add the subdomain (hPanel)

1. Advanced → **PHP Configuration**: choose PHP **8.5**.
2. Domains → **Subdomains**: create `accounts.<company-domain>`, choose a folder **outside `public_html`** (for example `~/accounts`). hPanel also issues the free HTTPS certificate for the subdomain (confirm in hPanel).
3. The application lives in that folder as `releases/<version>/` folders plus a `current` link to the live one (D-6). The subdomain's **document root must point at `current/public`**, so only the `public` folder is on the web (§18.1). Confirm in hPanel that you can point the subdomain at a folder inside the chosen folder; if you can only pick one folder, point it straight at `current/public` (confirm in hPanel).
4. Repeat for `staging.<company-domain>` when you set up staging, with its own folder and database and test data only (D-9).

## Step 4. Create the `.env` and the releases layout

From your laptop, and over SSH:

```bash
ssh <account-user>@<host> 'mkdir -p ~/accounts/releases'
scp .env.production.example <account-user>@<host>:~/accounts/.env
```

1. Fill in `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://accounts.<company-domain>` and the database name, user and password from step 2.
2. Generate `APP_KEY` on your laptop (`echo "base64:$(openssl rand -base64 32)"`) and paste it in. The `.env` sits **outside `public_html`** and is shared by every release, so a deploy never loses it (BR-SEC-10, D-43).
3. `current` is a link the deploy script creates and switches; you do not make it by hand (runbook §2).

## Step 5. Test your SSH login, then lock the door behind you

1. In a new terminal: `ssh <account-user>@<host>`. It must work with your key.
2. In hPanel, keep **key login only** and turn password login off if that option exists (confirm in hPanel). There is no root and no `sudo`, so there is no door behind you to lock.

## Step 6. Backup key pair (O-37) and `APP_KEY` copies

On **your computer** (not the server):

1. Make a **sodium** key pair for backup encryption with `bash infra/hostinger/make-backup-key.sh` (run on your computer). Put the **private** key in the password manager (secure note) **and** print it; the Owner keeps the printed copy sealed. The private key is **never** stored on any server (BR-OPS-02). Destroy the file on your computer when done (`shred -u`).
2. On the server, put the **public** key in the application's `.env` (outside `public_html`), where `zb:backup` reads it.
3. Copy `APP_KEY` the same way (password manager + sealed paper with the Owner):
   ```bash
   grep ^APP_KEY= .env
   ```
   Staging and production have **different** keys.

## Step 7. Off-site storage (worked example: Backblaze B2)

Do this in the storage provider's web console, logged in with the company account:

1. Create a **private** bucket, for example `zb-mill-backups-<random>` (one bucket per environment).
2. Keep **all versions** (B2 keeps versions by default) and turn on **Object Lock** with a default retention of **30 days** in **governance** mode.
3. Add lifecycle rules (B2: "Lifecycle Settings", custom rules) with these file-name prefixes:

   | Prefix | Hide after (days from upload) | Delete after (days from hiding) |
   | --- | --- | --- |
   | `db/daily/` | 35 | 1 |
   | `db/monthly/` | 2557 (7 years, O-35) | 1 |
   | `files/` | 100 | 1 |
   | `canary/` | 1 | 1 |

   Files under Object Lock cannot be removed before their 30 days, whatever the rule says.
4. Create an **application key** restricted to this bucket with only: `listBuckets`, `listFiles`, `writeFiles`. **Not** `deleteFiles`, `readFiles` or `bypassGovernance`. Copy the key ID and key once (they are shown once) and put them in the application's `.env` (outside `public_html`), where `zb:backup` reads them. The server can add a backup but can never delete or overwrite one.

Using another provider: any S3-compatible storage with versioning/Object Lock and a key **without delete permission** works. Step 9 proves it either way.

## Step 8. Monitoring (O-33)

1. In the monitoring service create two "dead man's switch" checks:

   | Check | Expected every | Grace |
   | --- | --- | --- |
   | backup | 1 day | 2 hours |
   | health | 15 minutes | 15 minutes |

2. Put each ping URL in the application's configuration (outside `public_html`), one for the daily backup job and one for the health check (BR-OPS-07, BR-OPS-09).
3. Create an HTTP uptime check for `https://<domain>/up` every 5 minutes (it answers once the app is deployed).
4. Send alerts to the Owner and to you (phone and e-mail).

## Step 9. Turn on the scheduler and backups, and prove the storage cannot be deleted from the server

1. In hPanel → Advanced → **Cron Jobs**, add **one** job that runs **every minute** and calls the scheduler (D-7):
   ```bash
   * * * * * php /home/<account-user>/accounts/current/artisan schedule:run
   ```
   (confirm the exact command box in hPanel). The `crontab` binary is absent on the host, so the job is added in hPanel only.
2. Everything the system schedules goes through it: `zb:verify` (daily), `zb:backup` (daily and monthly) and the health check (every 15 minutes) (§18.1).
3. Trigger one backup by hand over SSH (`php artisan zb:backup`) and check the Backups page (T7.6) and the storage console show it. The storage key has no delete right, so the server cannot remove its own copies; keep a screenshot with `docs/release-checks.md`.

## Step 10. First deploy

From your laptop, after CI has published a release (runbook §2; START-HERE, Step 10):

```bash
bash infra/hostinger/ship-release.sh v0.1.0 accounts.<company-domain>
```

The deploy unpacks a `releases/<version>/` folder, backs up the database, migrates, adds the system data the app needs (company, settings keys with no values, roles and permissions, system accounts, items; BR-OPS-11), switches the `current` link and smoke-tests `/up`. It creates no users. Then create the first Owner (task T5.2 builds this command), over SSH:

```bash
php ~/accounts/current/artisan zb:create-owner
```

Open `https://accounts.<company-domain>`: the login page appears with a valid certificate. Log in; you must set a new password at once.

## Step 11. Check everything

Over SSH, from the application folder:

```bash
zb-health-check                                           # "OK: clock, database, backups…, app"
curl -sI https://<domain>/up | grep -iE 'strict-transport|x-frame|content-security'
zb-deploy-release --list                                  # one release, marked (live)
```

Every header is set by the application (BR-SEC-14, D-8); the `.htaccess` from `public/` is the only server file LiteSpeed reads. Then tick the server rows of `docs/release-checks.md` (T8.1): BR-SEC-01, BR-SEC-10, BR-SEC-12, BR-OPS-01 to 09.

## Step 12. Restore drill (before go-live)

On **staging**, restore a production-format backup as in `docs/operations/runbook.md` → "Monthly restore test". Before go-live also do the full rebuild drill once (runbook → "Server lost"), timing it against the 4-hour target (O-36).

## Afterwards: remove the local copies

Delete any local copy of `.env` you used, and confirm no secret sits in a temporary folder or in git. Everything the system needs is now in the application folder outside `public_html` (`.env`, `releases/`, `current/`) and in hPanel. Future script updates come with each release (runbook → "Update server scripts").
