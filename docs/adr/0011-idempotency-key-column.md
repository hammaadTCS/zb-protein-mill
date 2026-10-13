# ADR-0011: Double-submit protection with an idempotency key column

**Status:** Accepted (BR-GEN-11, 11 October 2026).

**Context.** On a slow phone connection a user taps "Save" twice, or the browser retries. Without protection that creates two purchases or two payments with two voucher numbers; voucher numbers can never be reused (BR-GEN-05), so the duplicate must be cancelled with a reason.

**Decision.** Every create form sends a UUID made in the browser when the form opens (`idempotency_key`). Each transaction table has a nullable `uuid` column `idempotency_key` with a unique index on (`company_id`, `idempotency_key`). Inside the Action's transaction, if a row with that key already exists **for the same user**, the Action returns that row and creates nothing; the user sees "Already saved. PUR-000123". A concurrent second insert hits the unique index and is answered the same way. "Save and add another" makes a new key.

**Consequences.** No extra table, nothing to clean up, nothing deleted. Applies to purchases, sales, payments, expenses, partner transactions, transfers and agreed adjustments.

**Rejected alternative.** A separate idempotency table with stored responses: more moving parts and rows to delete later.
