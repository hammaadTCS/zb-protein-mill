# ADR-0006: Hourly backups encrypted with a public key, off-site, delete-proof

**Status:** Accepted (11 October 2026). Replaces `spatie/laravel-backup` (spec v1.0 §5, T7.6). Provider is O-32, retention O-35, targets O-36.

**Context.** Spec v1.0 had a daily backup protected by a password stored on the server, sent to storage the server could also delete. A break-in or ransomware on the server would then reach the live data **and** every backup, and up to a day of entries could be lost.

**Decision.**
- `zb-backup-db` every hour: `pg_dump` as the read-only role, encrypted with **age** to a **public key**; the matching private key is never on any server (two offline copies, O-37). Uploaded with **rclone** to object storage using a key that can upload but **cannot delete**; the bucket keeps versions or uses Object Lock; lifecycle rules apply retention.
- `zb-backup-files` daily: uploads folder as a monthly full + daily incremental tar chain, encrypted the same way.
- Status files for the app's backup page; dead-man pings to the monitor; `zb-backup-verify-offsite` proves the delete protection on day one.
- Restores (`zb-restore`) happen on staging or a new server, with the private key placed in memory (`/dev/shm`) only for the restore and removed afterwards.

**Consequences.** At most 1 hour of entries lost (RPO); the backups survive a full server compromise; the server cannot read its own backups. Losing **every** copy of the private key loses every backup, so two people hold it and a restore is tested monthly. Proven by `infra/tests/test-backup-restore.sh`.

**Rejected alternatives.** `spatie/laravel-backup` with a zip password (secret on the server, deletable destination); provider snapshots only (same account as the server, not off-site, not encrypted by us); continuous WAL archiving with pgBackRest (point-in-time recovery, but much more to operate for a database this small; reconsider if RPO must be minutes).
