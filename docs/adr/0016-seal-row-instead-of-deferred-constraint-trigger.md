# ADR-0016: A seal row instead of a deferred constraint trigger

**Status:** Accepted (16 October 2026). Replaces ADR-0002 (D-45, BR-LED-11).

**Context.** ADR-0002 kept the ledger balance guarantee inside the database: a deferred constraint trigger refused any transaction whose journal did not balance, and triggers blocked UPDATE and DELETE on the protected tables. MariaDB has no deferred constraint trigger, so that mechanism cannot carry over; the balance guarantee still has to live in the database, not only in `PostingService` (D-45).

**Decision.**
- `PostingService` writes one **`journal_seals`** row per journal entry at the end of its saving transaction (source `posting`), where the PostgreSQL code ran `SET CONSTRAINTS … IMMEDIATE`.
- A **`BEFORE INSERT` trigger** on `journal_seals` sums that entry's `journal_lines` and refuses the whole save (`SIGNAL SQLSTATE '45000'`) when the entry has fewer than two lines or its total debit does not equal its total credit (BR-LED-11).
- Seal rows are never updated or deleted; the daily `zb:verify` re-sums every seal (BR-OPS-08).
- The other database protections of ADR-0002 are rewritten in MariaDB syntax with the same behaviour: triggers block UPDATE and DELETE on journal, audit, rate-change and period-event tables, and DELETE on every master and transaction table.

**Consequences.**
- The BR-LED-03 guarantee (a journal is saved only if total debit equals total credit) stays inside the database at the same point in the save (the end of the transaction).
- The PostgreSQL deferred constraint trigger and the `journal_balance_check` name go; the same refusal tests apply with a MariaDB error.
- Money stays exact: `DECIMAL` in the database, `BigDecimal` in PHP, strings in JSON (ADR-0002, unchanged).
- `journal_seals` is added to the schema (spec §9.5) and to the immutability rules.

**Rejected alternatives.** Application-only balance checks (one bug or one console command away from silent corruption); a deferred constraint trigger (MariaDB does not have it).
