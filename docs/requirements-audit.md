# Requirements audit (v3, 11 October 2026; corrections v3.1 and v3.2, and v4.1, 13 October 2026)

Every requirement you stated in our discussion, checked against the specification, the task cards and the tests. Then every gap found, and where it is now fixed.

**Result:** all business requirements were already covered in spec v1.0. The gaps were in **production operations and security** (first owner account, backups, sessions, monitoring, headers, deploy, handover) and in **outdated starter-kit facts**. Spec v1.1 and the v3 cards fix them. Six new questions are OPEN (O-32 to O-37).

**v4.1 (13 October 2026):** Claude Code builds each task in a cloud session and opens a pull request; you merge it on GitHub when every check is green, then pull and run it on your Linux Mint laptop. All requirements, rules, tasks and server design are unchanged (Part 2c, ADR-0012).

**v3.1 (13 October 2026):** writing the step-by-step build guide found five more gaps (G-25 to G-29, Part 2b). The most serious, G-29, would have stopped the first deploy: nothing created the company, roles or system accounts on a new server.

## Part 1: Your requirements → where they are covered

| # | What you said | Where it is covered | Proof |
| --- | --- | --- | --- |
| 1 | Business buys chicken waste, boils it, makes protein, sells to feed factories | F-01, F-03; items D-07 | AT-1, AT-2 |
| 2 | Money comes and goes; material comes in and is sold; buyers and partners are kept | Modules list (spec §1); ledger (§8) | AT-1 |
| 3 | The main thing: finances, partner investments, every rupee in and out | F-19, D-21, D-23; PR-01 to PR-27 | AT-1 #15, #16; T-01 |
| 4 | Raw material with quantity, price, money given; daily expenses; money in and out of bank accounts | Purchases (§10.4), expenses (§10.8), money accounts | AT-1 |
| 5 | Old records are in Urdu; new system in English | F-25, F-26, NFR-04 | T2.5 Urdu input |
| 6 | Waste and wood bought from different people, paid or pending | F-01, F-02, BR-PUR-*, BR-PAY-* | AT-1 #1, #2, #11 |
| 7 | Expenses recorded | F-20, BR-EXP-* | AT-1 #7 to #9 |
| 8 | Sales: quantity, unit price, total, paid or still to pay | BR-SAL-*, §8.2 balances | AT-1 #5, #6 |
| 9 | Units in kg; advances happen; rate sometimes decided later | F-06, F-10, F-09 | AT-1 #3, #4, #12, #14 |
| 10 | At month end, credits and debits must be equal | D-23, D-26, BR-LED-03, BR-MEC-* | T-02, AT-3 |
| 11 | Rate entered on every entry; changes by person and by day | F-07, D-08 | AT-1 rates |
| 12 | Office expenses such as food | F-20, O-11 category list | AT-1 #8 |
| 13 | Profiles for buyers and sellers; know exactly to whom everything goes | D-01, D-02, BR-PTY-* | T2.4 tests |
| 14 | No middleman, no CNIC, owner or accountant can both add | F-04, F-22, F-23 | schema; BR-PTY-06 |
| 15 | No stock for now (maybe later); one delivery one rate; food for both office and plant; petty cash one by one | F-24, F-08, F-20, F-21; §20.2 | banner test; schema |
| 16 | Every expense needs a note; no office/production split | BR-EXP-02, D-18 | BR-EXP-02 test |
| 17 | Future changes without rebuilding from scratch | Modules + Action classes (§6), settings not code, `company_id` (D-37), roles in database, ADRs | Architecture tests (T0.3) |
| 18 | A document that assures the client | Requirements v1.2 (Word/PDF, `docs/client/Accounts_System_Requirements_v1.2.docx`) | Client signature |
| 19 | Every page, nothing missed | Spec §11 page list | T2.10, T8.4 reviews |
| 20 | Tax, deductions, cheques, wallets, oil, weight, returns, loans, transport, supplier ≠ buyer answers | F-05, F-11 to F-18 | traceability table |
| 21 | Facts on framework choice; good-looking React front end | D-38, §5, `stack-baseline.md` | — |
| 22 | Every detail written; no bluffs or assumptions | OPEN items never guessed (spec §4); this audit; `stack-baseline.md` | — |
| 23 | Plan for front end and back end; how to start | Cards M0 to M8; START-HERE | — |
| 24 | Build with Claude cloud sessions in small tested iterations; run it on the laptop | One task = one session = one branch = one pull request; Plan first; `check-all.sh`; CI on every pull request; main guard; protection hook; `setup-local.sh` to pull and run | `test-guardrails.sh`; CI |
| 25 | Company name; logo used minimally | §15.7, rule 12 in CLAUDE.md, guard script | guard.sh |
| 26 | In-depth rules so the model does not deviate | Six enforcement layers (START-HERE) | hook + CI tests |
| 27 | Production-ready: passwords, security, tokens, clean data handling, reliable on a VPS, handed to the company, documented | **Gaps G-01 to G-29 below**, spec v1.1 §14, §18; `docs/architecture.md`, `docs/security.md`, `docs/secrets.md`, `docs/data-handling.md`, `docs/environments.md`, `docs/operations/`, `infra/` | infra tests (`infra/tests/run-all.sh`), T1.11, T1.12, T8.1 |
| 28 | Efficient, fast front end; strong back end | NFR-01, NFR-02, new NFR-11; nginx compression and caching (`infra/files/nginx`); React Compiler and per-page code splitting from the kit | T8.2 measurements |

## Part 2: Gaps found and how each is fixed

| ID | Gap | Risk if left | Fix | Where |
| --- | --- | --- | --- | --- |
| G-01 | No way to create the first Owner on a new server (no registration, by design) | Nobody can log in after go-live | `php artisan zb:create-owner`: works only when no Owner exists; asks for name, email and password with hidden input; audited | BR-USR-07, T5.2 |
| G-02 | Owner sets passwords for users, but users never change them | Owner knows everyone's password; "who did it" is weak | `must_change_password` flag: set when the Owner creates or resets a password; the user must choose a new one before anything else | BR-USR-08, T5.2 |
| G-03 | Backups: daily only; password stored on the server; deletable from the server | A server break-in or ransomware deletes the live data **and** the backups; up to 24 hours lost | Hourly encrypted database backup with a **public key** (the server cannot decrypt), daily uploads backup, sent to storage where the server's key **cannot delete**; private key kept offline in two places | BR-SEC-08 (rewritten), BR-OPS-01 to 05, ADR-0006, T7.6 |
| G-04 | Disabling a user or resetting a password does not end that user's open sessions | A dismissed accountant stays logged in on a phone | Sessions for that user are deleted at once; middleware logs out disabled users on the next request | BR-SEC-13, T1.12, T5.2 |
| G-05 | No monitoring or alerts | A failed backup, full disk or expired certificate is found weeks later | Uptime check on `/up` (includes a database check), backup "dead man" pings, disk and certificate checks, daily integrity check | BR-OPS-06 to 09, T1.12, T7.8 |
| G-06 | No rules for what logs contain or how long they are kept | Personal data and secrets leak through logs | Log rules: no request bodies, passwords, tokens or amounts in logs; 14 days application logs; rotation | BR-SEC-17, `data-handling.md` |
| G-07 | No security headers; the kit has inline script | Clickjacking, script injection harder to contain | CSP with a per-request nonce, HSTS, frame denial, `noindex` | BR-SEC-14, T1.12, nginx config |
| G-08 | No record-retention rule | Backups or records deleted too early for the tax authority | Monthly backups kept 7 years (P); legal period to be confirmed by the client's tax adviser | BR-OPS-03, O-35 |
| G-09 | Phone photos can be larger than 5 MB and carry GPS location | Uploads fail on site; location data stored | Photos resized in the browser (longest side 2000 px, JPEG) before upload, which also drops the location data; server still enforces 5 MB | BR-SEC-06 (extended), T2.8 |
| G-10 | PDF library cannot join Urdu letters correctly | Broken names on statements | PDFs print the English `name` only; the Urdu name is shown on screen only | §13 note, T6.10 |
| G-11 | Double tap on "Save" on a slow phone | Two identical purchases or payments | Each create form sends a one-time `idempotency_key`; a repeat returns the first result | BR-GEN-11, T1.13 |
| G-12 | Deploy steps had no rollback and no rule about safe migrations | A bad release cannot be undone quickly | Release folders + symlink switch + one-command rollback; migrations must work with the previous release | BR-OPS-10, ADR-0005, `zb-deploy-release` |
| G-13 | Nothing said who owns the server, domain, storage and code after handover | The company depends on one person's accounts | Handover checklist: every account in the company's name, keys handed over, two people trained to restore | T8.7, `operations/handover.md` |
| G-14 | Spec and cards described an older starter kit (Inertia 2, Ziggy, ESLint, separate Vitest, Pest by default, Larastan to install) | Claude follows wrong instructions or improvises | Corrected everywhere from the real kit; facts in `stack-baseline.md` | T0.1 to T0.3, T2.1, CI |
| G-15 | Pest 5 needs PHP 8.4; cloud sessions and the laptop (Linux Mint 22) run PHP 8.3 | `composer install` fails there | Pest pinned to ^4.7; Composer platform PHP 8.3.6; CI tests on PHP 8.3 and 8.5 | T0.1 |
| G-16 | The kit's build downloads fonts from `fonts.bunny.net` | Build fails where that site is blocked | Self-hosted IBM Plex Sans from npm from T0.1 | T0.1 |
| G-17 | Laravel Boost (offered by the installer) writes its own `CLAUDE.md` | Overwrites the project rules | Not installed | T0.1 |
| G-18 | Spec said passwords of 10+ characters; the kit enforces 12+ with mixed case, numbers, symbols and a breach check in production | Two rules disagree | Spec adopts the kit rule | BR-SEC-02 (changed) |
| G-19 | Staging was allowed to hold a copy of live data | Real financial data on a less-watched server | Staging uses test data. The monthly restore test restores into a temporary database, checks it, and drops it | BR-OPS-04, §18 |
| G-20 | "Backup download" from the app | The web app would need to read backups | Backup page shows status only. Restores are done from the offline key holder | §7 `backup.view`, T7.6 |
| G-21 | No check for leaked secrets or vulnerable packages | A key committed by mistake; a known-vulnerable package | Guard script scans for private keys and filled `APP_KEY`; CI runs `composer audit` and `npm audit`; Dependabot monthly, grouped (v3.2, to fit GitHub Free Actions minutes) | T0.3 (guard), CI, `.github/dependabot.yml` |
| G-22 | No account-recovery path when the only Owner loses the password or phone | Business locked out | Server-only commands `zb:reset-password` and `zb:reset-two-factor`, audited, run by the server administrator | BR-USR-09, T5.2 |
| G-23 | Single server: no written recovery time | Nobody knows how long an outage lasts | Recovery targets RPO 1 hour, RTO 4 hours (P); a rebuild-from-scratch drill before go-live | BR-OPS-05, O-36, `operations/runbook.md` |
| G-24 | The starter kit's `.gitignore` ignores `CLAUDE.md` and `.claude/` | New project hooks or rules silently not committed | T0.1 removes those lines; the guard script fails if they come back | T0.1, `scripts/guard.sh` |

## Part 2b: Corrections found on 13 October 2026 (v3.1)

| ID | Gap | Risk if left | Fix | Where |
| --- | --- | --- | --- | --- |
| G-25 | GitHub Free cannot protect `main` in a private repository, and cloud sessions can push to any branch | Unreviewed or failing code reaches `main` and then a server | v4.1: the hook stops Claude from changing or pushing `main`, tags, merges and GitHub writes except its own branch; every pull request runs all checks including protected files; the main guard reports any commit on `main` without a merged pull request; the release workflow builds only from a commit whose CI on `main` is green (ADR-0012) | `.claude/`, `scripts/check-main-push.sh`, `.github/workflows/`, ADR-0012 |
| G-26 | Network facts for building were uncertain | Wrong advice about blocked sites | The cloud "Trusted" list includes Packagist, GitHub downloads and npm; the laptop downloads directly; the self-hosted font stays (ADR-0010) | `docs/stack-baseline.md` |
| G-27 | No card gave the local test user a role | From T1.3 a developer can log in locally but see nothing, and cannot review screens | `DatabaseSeeder` gives `test@example.com` the Owner role in local and testing only; it refuses to run in production | T1.1, T1.3 |
| G-28 | Card T8.5 put trial-month differences in a file in git, and the restore record asked for journal totals | Real names and amounts in the repository, against the data rules | Notes in git hold voucher numbers, dates, kinds and counts only; names and figures stay in the company's own sheet | T8.5, runbook §4, BR-OPS-04, `docs/data-handling.md` rule 9 |
| G-29 | Nothing created system data (company, settings keys, roles and permissions, system accounts, items) on a server: the seeders were described for tests only | The first deploy works but `zb:create-owner` has no company, and no role or account exists: go-live blocked; a later release adding a permission would not reach the servers | New BR-OPS-11: idempotent `SystemDataSeeder`, run by `zb-deploy-release` as `zb_migrator` right after the migrations on every deploy; it never changes saved data and never creates users; local seeders refuse production; `zb:create-owner` refuses without system data | spec §14.3, §18.2; T1.1 to T1.4, T2.3, T5.2; `infra/bin/zb-deploy-release`, `infra/tests/test-deploy.sh` |

## Part 2c: v4.1 – build in the cloud, run on the laptop (13 October 2026)

| Change | Why | Where |
| --- | --- | --- |
| Claude Code builds in cloud sessions and opens pull requests; you merge on GitHub and pull and run on your Linux Mint laptop | Claude works without your laptop on; you see and test the result locally | START-HERE, CLAUDE.md, `docs/prompts.md` |
| Each cloud session prepares itself: PostgreSQL, test databases `zb_mill_test` and `zb_mill_dev`, packages and `.env` | No environment variables or setup script to maintain in the cloud | `scripts/cloud-session-start.sh` |
| One-command laptop setup for pulling, running and testing | Simple start on Linux Mint | `scripts/setup-local.sh` |
| Sessions start in Plan mode; safe commands allowed, risky ones denied; Claude may push only its task branch | Nothing changes before you approve; nothing reaches `main` without you | `.claude/settings.json`, `.claude/hooks/` |
| `check-all.sh` runs every check in CI order before each pull request | Fewer red pull requests, fewer Actions minutes | `scripts/check-all.sh` |
| Main guard, release gate, monthly grouped Dependabot pull requests | GitHub Free has no branch protection; 2,000 Actions minutes a month | ADR-0012 |
| Front-end and back-end guides, document map, project README | One place to read how each side is built and why data is safe | `docs/frontend.md`, `docs/backend.md`, `docs/README.md`, `README.md` |
| T8.3 writes `docs/developer-guide.md` instead of `README.md` | `README.md` exists and is protected | card T8.3 |

## Part 3: New OPEN questions

| ID | Question | Who answers | Until answered |
| --- | --- | --- | --- |
| O-32 | Which off-site storage provider for backups? | You and client | Worked example: Backblaze B2. Any provider must pass `zb-backup-verify-offsite` |
| O-33 | Which monitoring service, and who receives alerts (phone/email)? | You and client | Go-live blocked |
| O-34 | Server provider, size and region? Must data stay in Pakistan? | You and client | Proposed size: 2 vCPU, 4 GB RAM, 80 GB SSD (P) |
| O-35 | How many years must records and backups be kept? | Client's tax adviser | Monthly backups kept 7 years (P), based on the 6-year rule in s.174(3) plus one year margin |
| O-36 | Is "at most 1 hour of entries lost, back within 4 hours" acceptable? | Client | Built for RPO 1 h / RTO 4 h (P) |
| O-37 | Who holds the two copies of the backup private key and the `APP_KEY`? | Client (Owner) | Proposed: Owner (sealed envelope at home or bank locker) + you (password manager) until handover |

## Part 4: What this audit does not cover

- It cannot find requirements that were never said. The trial month (T8.5) is the real test.
- The 37 OPEN items still need the client's answers before go-live.
- Legal points (retention period, data location) need the client's tax adviser or lawyer. This audit is not legal advice.
