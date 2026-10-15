# ADR-0013: MariaDB on the business's Hostinger shared hosting account

**Status:** Accepted (16 October 2026). Replaces ADR-0003; supersedes the server-file part of ADR-0008 (D-8).

**Context.** The business wants the live system on the Hostinger "Unlimited" shared hosting account it already pays for, not on a VPS (D-41). Shared hosting gives no root access and no server operating system to manage, and the plan offers MariaDB 11.8 in place of PostgreSQL. The account check (15 October 2026) found triggers working with the binary log and `trust_function_creators` off; PHP 8.5 on LiteSpeed; the application runnable from a folder outside `public_html`; an hPanel cron job that can run every minute; and a free HTTPS certificate. Every entry, rule, screen, report and number is unchanged.

**Decision.**
- The live system runs on the business's Hostinger "Unlimited" shared hosting account; the database engine is **MariaDB 11.8** (D-38, D-41), the version of record in cloud sessions, the laptop, CI and production (T10.1).
- The application lives in a folder **outside `public_html`**; each release is a `releases/<version>/` folder and `current` is a link to the live one (D-6). Staging is a second subdomain on the same plan (D-9).
- One **hPanel cron job every minute** runs `php artisan schedule:run`; it drives everything the system schedules — `zb:verify` (daily), `zb:backup` (daily and monthly) and the health check (D-7).
- There is **no root and no server operating system**; we install no server packages and manage no services on the host. All security headers are set by the application (D-8) and HTTPS uses the host's free certificate (BR-SEC-10).

**Consequences.**
- The host keeps PHP and the machine patched; the application still refuses to start on a database older than MariaDB 10.2 or with wrong settings (Q-9, T10.1).
- The books sit on a shared machine, so page speed depends on other customers; the T8.2/T9.2 timings are measured again on the host.
- The host's own daily backups are an extra copy only; the off-site encrypted backups are ours (ADR-0015).
- The VPS provisioning scripts and nginx files retire to `infra/history/`; the deploy and health scripts live under `infra/hostinger/`.

**Rejected alternatives.** Staying on the VPS (the owner wants the system on the account the business already holds); PostgreSQL on shared hosting (the plan does not offer it); a managed platform (higher cost, provider lock-in, still needs off-site backups).
