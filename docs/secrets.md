# Secrets inventory

Every password, key and token the system depends on: where it is made, where it lives, who holds it, how to change it, and what to do if it leaks. **No secret ever goes into git, a Claude session, a Claude environment variable, chat, e-mail or a screenshot.**

Use a password manager (any reputable one with two-factor login) for everything marked "password manager". Before handover the vault, or a copy of each entry, belongs to the **company** (`docs/operations/handover.md`).

## 1. Inventory

| # | Secret | Protects | Made where / by | Stored | Holders | Change | If leaked |
| --- | --- | --- | --- | --- | --- | --- | --- |
| S-01 | **`APP_KEY`** | Session encryption, two-factor secrets, signed URLs | On the host by `php artisan key:generate` (32 random bytes) | `~/accounts/shared/.env` (0640) **and** sealed copy with the two key holders (O-37) | Host; 2 key holders | Yearly or on leak: runbook "Change APP_KEY" (uses `APP_PREVIOUS_KEYS`) | Change it; all users log in again; two-factor keeps working through `APP_PREVIOUS_KEYS` until users re-enrol |
| S-02 | **Backup private key** (sodium/X25519, `…`) | Reading every backup | On a trusted computer (offline): a small keygen step (see the runbook) | Paper in a sealed envelope (Owner) + password manager (developer, later a second company person) | 2 people (O-37), one copy each. **Never on the host** | Every 2 years or on leak: new key pair, put the new public key on the host; keep the old private key as long as backups made with it are kept | Make a new pair now; old backups stay readable by whoever has the leaked key until they expire: tell the client |
| S-03 | Backup public key (sodium/X25519) | Nothing (it only encrypts) | Same as S-02 | `~/accounts/shared/secrets/backup-public-key.txt`, can be in docs | Anyone | With S-02 | Not a secret |
| S-04 | **Storage account login** (e.g. Backblaze) + its two-factor | Deleting/reading backups, creating keys | Company creates the account | Password manager | Owner + developer | On staff change | Change password, check keys and lifecycle rules, check Object Lock still on |
| S-05 | **Storage upload key** (can upload, cannot delete) | Adding backups | In the storage console (Account setup) | `~/accounts/shared/.env` (0640), outside `public_html` | Host only | Yearly (runbook) | Delete the key in the console, make a new one; `zb-backup-verify-offsite` |
| S-06 | Storage read key (for restores) | Downloading backups | Created only when needed, short life | Your computer during a restore | You | Delete after each restore | Delete it |
| S-07 | **Hostinger hosting account** + two-factor | hPanel, SSH, databases, cron, certificate, deleting the account | Company creates the account | Password manager | Owner + developer | On staff change | Change password; check for new SSH keys, databases, cron jobs, users |
| S-08 | **Domain registrar / DNS account** + two-factor | Pointing the domain elsewhere (phishing) | Company | Password manager | Owner + developer | On staff change | Change password; check DNS records |
| S-09 | **Admin SSH private key** | Logging in to the host (hPanel → SSH Access) | On your computer: `ssh-keygen -t ed25519` with a passphrase | Your computer (and its backup) | You | Yearly or on leak | Remove the public key in hPanel, add a new one |
| S-10 | **MariaDB database password** | The one database user with all rights on its own database (ADR-0014) | In hPanel when the database and user are created | `~/accounts/shared/.env` (0640), **outside** `public_html` | Host only | Yearly or on leak: change it in hPanel and `.env` | Change it in hPanel and `.env`; grant no more access than before |
| S-11 | **GitHub account** + two-factor | Code, CI, releases | You (company organisation at handover) | Password manager | Developer; company owner after handover | On staff change | Change password, revoke tokens and sessions, check deploy keys (there should be none) |
| S-12 | Monitoring account + **ping URLs** | Faking "backup OK" | Company account (O-33) | Account: password manager. URLs: `~/accounts/shared/secrets/healthchecks/*.url` | Host; Owner + developer | If leaked | Create new checks, replace the URL files |
| S-13 | App user passwords (Owner, Accountant) | Using the system | Owner sets a temporary one; user changes it at first login | Hashed (bcrypt) in the database | Each user | When the user wants; reset by Owner | Owner resets it (sessions end) |
| S-14 | Two-factor secrets and recovery codes | Second login step | Each user enrols | Encrypted with `APP_KEY` in the database; recovery codes kept by the user | Each user | Re-enrol | Server command `zb:reset-two-factor` (audited) |
| S-15 | Host SSL certificate | HTTPS | The host issues the free certificate and renews it | Managed by Hostinger | Host | Not needed | The host re-issues it |

The database password is **S-10**: one password for the one database user, in `.env` outside the web folder, **never** in the repository, CI or a Claude session (BR-SEC-12, ADR-0014). There are no server credentials in GitHub (ADR-0005).

## 2. Values that look like secrets but are not

| Value | Where | Why it is fine |
| --- | --- | --- |
| `local_test_only` | `.env.example`, `scripts/setup-local.sh`, `scripts/cloud-session-start.sh`, CI | Test database password for throwaway test databases; the host uses one real password (S-10) |
| `ci-only-not-a-secret` | `infra/tests/ci-db-rules.sh` | Throwaway database password inside a CI container (one user only, D-43) |
| Test keys in `infra/tests/` | Infrastructure tests | Generated or obviously fake; the secret scan skips that folder |

## 3. Rules

1. Secrets are created **where they are used** (APP_KEY on the host, SSH key on your computer, backup key on a trusted computer) and never travel through chat or e-mail.
2. Two people can reach every company-critical secret (S-01, S-02, S-04, S-07, S-08, S-10) so the business never depends on one person.
3. When someone with access leaves: change S-04, S-07, S-08, S-10, S-11 that they knew; remove their SSH key; disable their app user.
4. If a secret is committed to git, removing the commit is **not** enough: change the secret (runbook "A secret leaked").
5. Claude Code never needs a real secret. If a task seems to need one, the task is wrong: stop and ask.
