# ADR-0002: Double-entry ledger enforced by the database

**Status:** Accepted (D-23, spec §8 and §9.7).

**Context.** The system's main promise is that debits equal credits and history is never altered. Code can have bugs; a future developer can bypass a service class.

**Decision.**
- Only `PostingService` writes journal rows (code rule, architecture test, guard script).
- PostgreSQL also enforces it: triggers block UPDATE and DELETE on journal, audit, rate-change and period-event tables; a deferred constraint trigger refuses any transaction whose journal does not balance; every master and transaction table refuses DELETE.
- The web database role has no DELETE, TRUNCATE or ALTER rights on those tables at all (ADR-0007).
- Money is `numeric` in the database and `BigDecimal` in PHP; strings in JSON; never floats.

**Consequences.**
- Mistakes are corrected with reversal entries, never edits (spec §6).
- A daily `zb:verify` command re-checks the books (T7.8); backups are checked the same way on restore.
- Data fixes "by hand in the database" are impossible by design: they go through the app as entries, with an audit trail.

**Rejected alternatives.** Application-only checks (one bug or one console command away from silent corruption); soft deletes (still editable, and they hide history).
