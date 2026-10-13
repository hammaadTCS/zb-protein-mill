# Secrets inventory

Every password, key and token the system depends on: where it is made, where it lives, who holds it, how to change it, and what to do if it leaks. **No secret ever goes into git, a Claude session, a Claude environment variable, chat, e-mail or a screenshot.**

Use a password manager (any reputable one with two-factor login) for everything marked "password manager". Before handover the vault, or a copy of each entry, belongs to the **company** (`docs/operations/handover.md`).

## 1. Inventory

| # | Secret | Protects | Made where / by | Stored | Holders | Change | If leaked |
| --- | --- | --- | --- | --- | --- | --- | --- |
| S-01 | **`APP_KEY`** | Session encryption, two-factor secrets, signed URLs | On the server by `60-app-layout.sh` (32 random bytes) | `/srv/zb-mill/shared/.env` (0640) **and** sealed copy with the two key holders (O-37) | Server; 2 key holders | Yearly or on leak: runbook "Change APP_KEY" (uses `APP_PREVIOUS_KEYS`) | Change it; all users log in again; two-factor keeps working through `APP_PREVIOUS_KEYS` until users re-enrol |
| S-02 | **Backup private key** (age, `AGE-SECRET-KEY-1…`) | Reading every backup | On a trusted computer: `infra/local/make-backup-key.sh` | Paper in a sealed envelope (Owner) + password manager (developer, later a second company person) | 2 people (O-37). **Never on a server** | Every 2 years or on leak: new key pair, put the new public key on the server; keep the old private key as long as backups made with it are kept | Make a new pair now; old backups stay readable by whoever has the leaked key until they expire: tell the client |
| S-03 | Backup public key (`age1…`) | Nothing (it only encrypts) | Same as S-02 | `/etc/zb-mill/backup-recipients.txt`, can be in docs | Anyone | With S-02 | Not a secret |
| S-04 | **Storage account login** (e.g. Backblaze) + its two-factor | Deleting/reading backups, creating keys | Company creates the account | Password manager | Owner + developer | On staff change | Change password, check keys and lifecycle rules, check Object Lock still on |
| S-05 | **Storage upload key** (can upload, cannot delete) | Adding backups | In the storage console (server-setup step 7) | `/etc/zb-mill/secrets/rclone.conf` (root:zbbackup 0640) | Server only | Yearly (runbook) | Delete the key in the console, make a new one; `zb-backup-verify-offsite` |
| S-06 | Storage read key (for restores) | Downloading backups | Created only when needed, short life | Your computer during a restore | You | Delete after each restore | Delete it |
| S-07 | **VPS provider account** + two-factor | Console, rescue mode, snapshots, deleting the server | Company | Password manager | Owner + developer | On staff change | Change password; check for new SSH keys, snapshots, users |
| S-08 | **Domain registrar / DNS account** + two-factor | Pointing the domain elsewhere (phishing) | Company | Password manager | Owner + developer | On staff change | Change password; check DNS records |
| S-09 | **Admin SSH private key** | Logging in to the server | On your computer: `ssh-keygen -t ed25519` with a passphrase | Your computer (and its backup) | You | Yearly or on leak | Remove the public key from `/home/<admin>/.ssh/authorized_keys`, add a new one |
| S-10 | Admin user's sudo password | `sudo` on the server | You, `sudo passwd <admin>` | Password manager | You | Yearly | Change it |
| S-11 | **GitHub account** + two-factor | Code, CI, releases | You (company organisation at handover) | Password manager | Developer; company owner after handover | On staff change | Change password, revoke tokens and sessions, check deploy keys (there should be none) |
| S-12 | Monitoring account + **ping URLs** | Faking "backup OK" | Company account (O-33) | Account: password manager. URLs: `/etc/zb-mill/secrets/healthchecks/*.url` | Server; Owner + developer | If leaked | Create new checks, replace the URL files |
| S-13 | App user passwords (Owner, Accountant) | Using the system | Owner sets a temporary one; user changes it at first login | Hashed (bcrypt) in the database | Each user | When the user wants; reset by Owner | Owner resets it (sessions end) |
| S-14 | Two-factor secrets and recovery codes | Second login step | Each user enrols | Encrypted with `APP_KEY` in the database; recovery codes kept by the user | Each user | Re-enrol | Server command `zb:reset-two-factor` (audited) |
| S-15 | Let's Encrypt account key | Certificate renewals | certbot on the server | `/etc/letsencrypt` (root) | Server | Not needed | Revoke the certificate, re-issue |

**There are no database passwords** (peer authentication, ADR-0007). **There are no server credentials in GitHub** (ADR-0005).

## 2. Values that look like secrets but are not

| Value | Where | Why it is fine |
| --- | --- | --- |
| `local_test_only` | `.env.example`, `scripts/setup-local.sh`, `scripts/cloud-session-start.sh`, CI | Test database password for throwaway test databases; real servers have no DB password |
| `ci-only-not-a-secret` | `infra/tests/ci-db-privileges.sh` | Throwaway role password inside a CI container |
| Test keys in `infra/tests/` | Infrastructure tests | Generated or obviously fake; the secret scan skips that folder |

## 3. Rules

1. Secrets are created **where they are used** (APP_KEY on the server, SSH key on your computer, backup key on a trusted computer) and never travel through chat or e-mail.
2. Two people can reach every company-critical secret (S-01, S-02, S-04, S-07, S-08) so the business never depends on one person.
3. When someone with access leaves: change S-04, S-07, S-08, S-10, S-11 that they knew; remove their SSH key; disable their app user.
4. If a secret is committed to git, removing the commit is **not** enough: change the secret (runbook "A secret leaked").
5. Claude Code never needs a real secret. If a task seems to need one, the task is wrong: stop and ask.
