# ADR-0015: Daily and monthly backups from PHP, encrypted with sodium, off-site

**Status:** Accepted (16 October 2026). Replaces ADR-0006; owner's decision 15 October 2026 (D-40, O-35).

**Context.** ADR-0006 took an hourly `pg_dump` as the read-only role, encrypted with age to a public key and uploaded with an upload-only key, so a compromised server could neither read nor delete the backups. On shared hosting there is no root and no systemd timer, so the backup must run from the application; and the owner decided on 15 October 2026 on a gentler cadence — daily and monthly, RPO 1 day — accepted for a startup (D-40).

**Decision.**
- Backups run from the application: `zb:backup` runs `mariadb-dump` (triggers included) → gzip → encrypt with PHP's built-in **sodium** to the storage's public key → upload to object storage (worked example: Backblaze B2) with an **upload-only** key.
- **Daily** (after the day's `zb:verify`): full database and uploads, kept **35 days**. **Monthly** (1st of the month): full database and uploads, kept **7 years** (O-35).
- The recovery figure is **1 day** (RPO); the owner accepted it.
- A dead-man ping (healthchecks.io) alerts when a daily or monthly run is late or fails, and the app's Backups page shows the same status.
- `zb:restore` restores a backup on staging or the laptop.

**Consequences.**
- At most one day of entries can be lost; the host's own daily backups are an extra copy only.
- The private key stays offline (two copies, one per partner, O-37); losing every copy loses every backup, so a restore is tested monthly.
- Naming and retention are done by the script itself, so the upload-only storage key cannot delete a copy early.

**Rejected alternatives.** Hourly backups with systemd timers and rclone (the owner chose daily + monthly, 15 October 2026); the hosting provider's backups only (same account as the host, not off-site, not encrypted by us).
