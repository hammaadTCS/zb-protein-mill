# ADR-0004: No Redis and no queue worker in version 1

**Status:** Accepted (11 October 2026).

**Context.** Every action in version 1 is a short request: save an entry, open a report, make a PDF. Nothing needs to run in the background except scheduled jobs.

**Decision.** Sessions and cache in PostgreSQL (`SESSION_DRIVER=database`, `CACHE_STORE=database`), `QUEUE_CONNECTION=sync`, scheduler run every minute by a systemd timer.

**Consequences.** Fewer services to secure, patch and monitor. Session rows are left out of backups. If a slow background job appears later (bulk PDFs, messages), add a queue worker unit and switch the queue connection; Redis only if measurements show the database is the bottleneck.

**Rejected alternative.** Redis + Horizon: useful at scale, but an extra network service holding sessions, with nothing to gain at two users.
