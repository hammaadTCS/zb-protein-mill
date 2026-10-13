# ZB Poultry Protein Mill – Accounts System

Accounts system for **ZB Poultry Protein Mill**, a business in Pakistan that buys chicken waste and wood, renders the waste into protein product and oil/fat, and sells both to feed factories. It records every rupee in and out with double-entry accounting:

- purchases and sales, including rates decided later;
- payments and advances;
- daily expenses;
- partner money;
- cash and bank accounts;
- month-end close;
- reports and PDFs.

It also keeps a party ledger (khata) for each supplier, buyer and partner. Old records are kept in Urdu; the system is in English.

**Status:** specification and project setup are complete; the application is built task by task with Claude Code (74 tasks, `docs/tasks.md`).

| You want to… | Open |
| --- | --- |
| Start the project (Claude builds in the cloud, you run it on your laptop) | **[START-HERE.md](START-HERE.md)** |
| Find any document | [docs/README.md](docs/README.md) |
| Know the rules Claude follows | [CLAUDE.md](CLAUDE.md) |
| See what the system must do | [docs/spec.md](docs/spec.md) |
| See how the front end and back end are built | [docs/frontend.md](docs/frontend.md), [docs/backend.md](docs/backend.md) |
| Run or recover the live system | [docs/operations/](docs/operations/) |

**Stack:**
- **Application:** Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4.
- **Database:** PostgreSQL. All money is stored as exact decimals; nothing is ever deleted.
- **Tests:** Pest 4.
- **Servers:** Ubuntu 26.04 with PHP 8.5 and PostgreSQL 18, encrypted off-site backups every hour.
- **Details and sources:** [docs/stack-baseline.md](docs/stack-baseline.md).

**Rules for everyone:**
- Never put real client data, real passwords, keys or server addresses in this repository. Test values only.
- The repository is private.
