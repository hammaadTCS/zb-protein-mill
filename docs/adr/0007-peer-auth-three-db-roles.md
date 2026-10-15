# ADR-0007: Unix-socket peer authentication and three database roles

**Status:** Replaced by ADR-0014.

**Status:** Accepted (BR-SEC-12).

**Context.** A database password in `.env` can leak through a backup of the server, a mistake in git, or a file-read bug. The application role must not be able to change the schema or remove the protections of ADR-0002.

**Decision.**
- PostgreSQL listens on **no network address**; only the local Unix socket.
- Each OS user maps to exactly one role (`pg_ident.conf`): `zbmill` → `zb_app`, `zbdeploy` → `zb_migrator`, `zbbackup` → `zb_backup`. No role has a password.
- `zb_migrator` owns every table. `zb_app` gets SELECT, INSERT, UPDATE; DELETE only on listed housekeeping tables; nothing on journal/audit rows beyond insert and read. `zb_backup` gets `pg_read_all_data`.
- `infra/files/postgres/30-app-grants.sql` re-applies the rights after every migration (deploy does it).

**Consequences.** Nothing to rotate or leak for database access. Laravel connects with `DB_HOST=/var/run/postgresql`. Migrations use a second connection `pgsql_migrator` (T1.11). CI cannot use peer auth, so it creates the same roles with a throwaway password and runs the same checks over TCP. Proven by `infra/tests/test-postgres.sh` (39 checks on PostgreSQL 16) and CI on PostgreSQL 18.

**Rejected alternatives.** One superuser-like role with a password (the kit default; any bug can drop tables); passwords + TLS on localhost (more secrets, no gain on one machine).
