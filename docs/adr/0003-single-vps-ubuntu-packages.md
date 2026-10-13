# ADR-0003: One VPS, Ubuntu 26.04 LTS, distribution packages, no containers

**Status:** Accepted (11 October 2026). Server provider, size and region are O-34.

**Context.** Two users, modest volume (NFR-02), one developer who will hand the system to the company. Facts (`docs/stack-baseline.md`): Ubuntu 26.04 is supported until May 2031 and ships PHP 8.5 (security until end of 2029) and PostgreSQL 18 (until November 2030). Ubuntu 24.04 ships PHP 8.3, whose upstream security support ends December 2027.

**Decision.**
- One VPS (proposed 2 vCPU, 4 GB RAM, 80 GB SSD) running nginx, PHP-FPM 8.5 and PostgreSQL 18 from Ubuntu's own repositories, with automatic security updates.
- No Docker, no Kubernetes, no third-party package repositories on the server.
- The whole setup is scripted (`infra/provision`), so a new server can be built from the repository in the recovery-time target.
- Staging is a second, smaller VPS built with the same scripts.

**Consequences.**
- Security updates for every installed package come from one source and install themselves; the server reboots itself at 03:30 Pakistan time when a kernel update needs it.
- `php8.5-fpm`, `-intl`, `-bcmath` and `-zip` are in Ubuntu's "universe" component; security fixes for them were being published in `resolute-security` on 11 October 2026. Re-check this yearly (runbook).
- The server is a single point of failure; recovery is by rebuild + restore (RTO 4 h, O-36), not by failover.

**Rejected alternatives.** Containers (extra layer to patch and understand, no benefit on one server); shared hosting (no control over PostgreSQL, backups or isolation); a managed platform (higher cost, provider lock-in, still needs off-site backups).
