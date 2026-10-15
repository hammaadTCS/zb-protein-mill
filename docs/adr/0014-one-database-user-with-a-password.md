# ADR-0014: One database user with a password instead of peer-authenticated roles

**Status:** Accepted (16 October 2026). Replaces ADR-0007 (D-43, BR-SEC-12).

**Context.** ADR-0007 removed the database password: PostgreSQL listened only on the local Unix socket, each OS user mapped to one role, and three roles split the rights so the web role could not change the schema or remove the ledger protections. The host gives **one** database user with all rights on its own database only, cannot create a second user with fewer rights, and has no peer authentication; the database user must have a password (D-43). The protections of ADR-0002 and ADR-0016 stay, but this user can in principle drop them.

**Decision.**
- The application connects as **one database user that has all rights on its own database only**; there is no second, restricted role and no peer authentication.
- The password is a strong random value kept in `.env` **outside the web folder**, never in the repository, CI or a Claude session; remote database access is off and phpMyAdmin is reached only through hPanel.
- Because the user can in principle change or drop the tables and remove the triggers, the compensating controls are the daily `zb:verify` books check (BR-OPS-08), the off-site backups (BR-OPS-01) and the paper register kept by the other partner (BR-SEC-12).
- Trigger creation depends on `trust_function_creators` and the binary log staying off; the deployment runbook records this and `zb:verify` refuses to run when a protection trigger is missing.

**Consequences.**
- A database password exists again and must be rotated on a leak (runbook).
- The three roles, `pgsql_migrator` and the peer-authentication grants go; migrations run as the same user.
- A bug or a leaked password can drop tables; the off-site backups are the recovery.

**Rejected alternatives.** Three roles with peer authentication (the host cannot create them); an all-rights user with no password (the host gives every database user a password).
