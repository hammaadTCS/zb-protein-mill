# Traceability: client requirement → rule → task → proof

Every confirmed fact (F) and decision (D) from the specification, the rules that implement it, the task that builds it, and the test that proves it. If a requirement has no proving test after its task is merged, the task is not done.

## Client facts (spec §2)

| ID | Requirement (short) | Rules | Task | Proof |
| --- | --- | --- | --- | --- |
| F-01 | Waste bought from many suppliers | BR-PUR-01, BR-ITM-01 | T2.3, T2.7 | AT-1 #1, #2, #10 |
| F-02 | Wood bought from suppliers | BR-PUR-01 | T2.7 | AT-1 #11 |
| F-03 | Protein product and oil/fat sold | BR-SAL-01, BR-ITM-01 | T3.4 | AT-1 #5, #6; AT-2 #6 |
| F-04 | No middlemen | BR-PTY-01 | T2.4 | design (supplier = person who brings material) |
| F-05 | Supplier and buyer always different | BR-PTY-04 | T2.4 | BR-PTY-04 tests |
| F-06 | All quantities in kg | BR-ITM-01, BR-PUR-02 | T2.3, T2.7 | BR-PUR-02 tests |
| F-07 | Rate on every entry, by person and day | BR-PUR-03, BR-SAL-01 | T2.7, T3.4 | AT-1 (rates 48, 50, 52, 205, 210) |
| F-08 | One rate per delivery | spec §9.3 (single rate column) | T2.7 | schema |
| F-09 | Rate sometimes decided later | BR-PUR-03, BR-PUR-04, BR-LED-06, BR-LED-08 | T3.1 | T-05; AT-1 #4, #12 |
| F-10 | Advances both ways | BR-PAY-11, §8.2 | T3.5 | AT-1 #3 (Bashir), #14 (Rehman) |
| F-11 | Deductions agreed, then written | BR-ADJ-01 to 06 | T4.6 | AT-2 #7 |
| F-12 | Returns settled on site | D-14 (no returns module) | — | out of scope §20.1 |
| F-13 | Supplier's / buyer's weight is final | BR-PUR-07, BR-SAL-05 | T2.7, T3.3, T3.4 | T-13 weight difference |
| F-14 | Cash or bank transfer only | BR-PAY-03 | T3.5 | BR-PAY-03 (no cheque option) |
| F-15 | Buyers pay full amount, no tax deducted | D-16 | — | out of scope §20.1 |
| F-16 | Wallet money → bank, or spent as cash | BR-PAY-09 | T3.5 (training rule) | go-live training |
| F-17 | Business pays some transport | BR-EXP-05, BR-EXP-08 | T4.2 | AT-2 #8 |
| F-18 | No loans | D-16 | — | out of scope §20.1 |
| F-19 | Partners invest and withdraw | BR-PTN-01 to 04 | T4.4 | AT-1 #15, #16; R-09 test |
| F-20 | One expense list, note required | BR-EXP-01, BR-EXP-02 | T4.1, T4.2 | BR-EXP-02 tests |
| F-21 | Petty cash one by one | BR-EXP-09 | T4.3 | under-10-seconds check |
| F-22 | No CNIC | spec §9.2 (no column) | T2.4 | schema |
| F-23 | Owner and accountant add parties | §7, BR-PTY-06 | T1.3, T2.4 | BR-PTY-06 test |
| F-24 | No stock in version 1 | BR-RPT-08 | T6.7 | banner test |
| F-25 | English, Urdu names allowed | NFR-04 | T2.5 | Urdu name input (`dir="rtl"`) |
| F-26 | Start from opening balances | BR-OPB-01 to 04 | T2.6 | AT-1 openings (OBE 0.00) |

## Decisions (spec §3)

| ID | Rules | Task | Proof |
| --- | --- | --- | --- |
| D-01 | BR-PTY-01 | T2.4, T2.5 | PartyPicker only, no free text |
| D-02 | BR-PTY-02 | T2.4 | enum test |
| D-03 | BR-PTY-04 | T2.4 | type lock test |
| D-04 | BR-PTY-03 | T2.4 | duplicate_party test |
| D-05 | BR-PTY-06, BR-PTY-07 | T2.4, T5.4 | review tests |
| D-06 | BR-PTY-05, BR-PTY-08 | T2.4, T7.4 | AT-6, T-15 |
| D-07 | BR-ITM-01 | T2.3 | seeder test |
| D-08 | BR-PUR-02, BR-PUR-03 | T2.7, T3.1 | AT-1 |
| D-09 | BR-PUR-04, BR-LED-06 | T3.1 | T-05 |
| D-10 | BR-PUR-09 | T3.1, T3.2 | AT-1 rate_changes |
| D-11 | BR-PUR-05, BR-PUR-06 | T3.3 | BR-PUR-05/06 tests |
| D-12 | BR-PUR-07, BR-SAL-05 | T3.3 | slip tests, T-13 |
| D-13 | BR-ADJ-01 to 06 | T4.6 | AT-2 #7 |
| D-14 | — | — | out of scope |
| D-15 | BR-PAY-01 to 12 | T3.5 | AT-1 payments |
| D-16 | — | — | out of scope |
| D-17 | BR-PAY-09 | T3.5 | training |
| D-18 | BR-EXP-01 to 03 | T4.1, T4.2 | expense tests |
| D-19 | BR-EXP-04 to 07 | T4.2 | PR-09/10/11 tests |
| D-20 | BR-EXP-08 | T4.2 | AT-2 #8 |
| D-21 | BR-PTN-01 to 04 | T4.4 | AT-1 #15, #16 |
| D-22 | BR-TRF-01 to 03 | T4.5 | AT-1 #17 |
| D-23 | BR-LED-01 to 10 | T1.4–T1.10 | T-01, T-02, trial balance in AT-1/AT-2 |
| D-24 | BR-GEN-03, BR-AUD-01 | T1.8, T3.2 | T-03, T-06, AT-1 audit |
| D-25 | BR-CHK-01 to 04 | T6.1 | cash count tests |
| D-26 | BR-MEC-01 to 09 | T7.1–T7.3 | AT-3 |
| D-27 | BR-MEC-07, BR-LED-09 | T4.7, T7.1 | AT-5 |
| D-28 | BR-PAY-06 to 08 | T3.7 | T-08, AT-4 |
| D-29 | BR-EXP-03 | T4.2 | BR-EXP-03 test |
| D-30 | BR-RPT-08 | T6.7 | banner test |
| D-31 | BR-GEN-05 | T1.6 | T-04, AT-1 vouchers |
| D-32 | BR-CHK-05, BR-CHK-06 | T3.1, T6.3 | T-12 |
| D-33 | BR-PUR-08 | T3.3 | duplicate_entry test |
| D-34 | BR-PTY-09 | T2.5 | search label test |
| D-35 | §13 | T6.10 | T-14 |
| D-36 | §12.5 | T7.5 | AT-1 insights |
| D-37 | BR-GEN-01 | T1.1 | company scope test |
| D-38 | §5 | T0.1 | CI |
| D-39 | BR-GEN-06 | T2.7 | browser amount ignored test |
| D-40 | BR-SEC-08, BR-OPS-01 to 05 | `infra/bin/zb-backup-*`, T7.6 | `infra/tests/test-backup-restore.sh`; monthly restore on staging |
| D-41 | BR-SEC-10, §18.1 | `infra/provision`, T8.3 | `infra/tests/test-provision.sh`, `test-web.sh`; T8.1 rows on staging |
| D-42 | BR-OPS-10 | `infra/bin/zb-deploy-release`, `.github/workflows/release.yml` | `infra/tests/test-deploy.sh` |
| D-42 | BR-OPS-11 | `SystemDataSeeder` (T1.1, T1.2, T1.3, T1.4, T2.3), `infra/bin/zb-deploy-release` | seeder tests in those tasks (run twice, no duplicates, no overwrite, refuses production); `test-deploy.sh` |
| D-43 | BR-SEC-12 | `infra/files/postgres`, T1.11 | `infra/tests/test-postgres.sh`; CI privilege check (T-19) |
| D-44 | §5, §15.4 | T2.5 onward | form tests in each page task |

## Automated tests required by spec §17.1

| Test | Task |
| --- | --- |
| T-01 every posting rule | each task that fills a PR rule (T2.6, T2.7, T3.4, T3.5, T4.2, T4.4–T4.7, T7.1, T7.4) |
| T-02 unbalanced journal refused | T1.5 |
| T-03 journal/audit rows immutable | T1.5, T1.8 |
| T-04 voucher numbers gapless | T1.6 |
| T-05 rate pending | T3.1, T3.4 |
| T-06 edit and cancel reversals | T3.2, T3.4 |
| T-07 closed month refused | T1.9, T3.2 |
| T-08 approvals | T3.7 |
| T-09 permission matrix | T5.3 |
| T-10 setup gate | T1.2 |
| T-11 warnings | T3.3, T3.4, T3.8 |
| T-12 ageing | T6.3 |
| T-13 report formulas | T6.8 |
| T-14 PDFs | T6.10 |
| T-15 merge | T7.4 |
| T-16 security headers and CSP | T1.12 |
| T-17 session revocation, temporary passwords | T1.12, T5.2 |
| T-18 idempotency key | T1.13, T2.7, T3.4, T3.5, T4.2, T4.4, T4.5, T4.6 |
| T-19 database privilege check | T1.11 (CI, every pull request and push to `main` after) |
| T-20 integrity check | T7.8 |
| AT-1, AT-2 | T4.8 (then every task) |
| AT-3 to AT-6 | T3.7 (AT-4), T4.7 (AT-5 part), T7.1, T7.4, T7.7 |

## Production readiness (spec update 3, `docs/requirements-audit.md`)

| Gap | Rules | Built in | Proof |
| --- | --- | --- | --- |
| G-01 first Owner | BR-USR-07 | T5.2 | `zb:create-owner` tests |
| G-02 temporary passwords | BR-USR-08 | T1.12, T5.2 | T-17 |
| G-03 backups | BR-SEC-08, BR-OPS-01 to 05 | `infra/`, T7.6 | `test-backup-restore.sh`; `zb-backup-verify-offsite` on day one; monthly restore |
| G-04 session revocation | BR-SEC-13 | T1.12, T5.2 | T-17 |
| G-05 monitoring | BR-OPS-06 to 09 | `infra/bin/zb-health-check`, T1.12 (`/up`), T7.8 | `test-health-check.sh`; test alert at go-live |
| G-06 log rules | BR-SEC-17 | T1.12, conventions §12 | T1.12 test; review |
| G-07 headers and CSP | BR-SEC-14 | nginx snippet, T1.12 | `test-web.sh`; T-16 |
| G-08 retention | BR-OPS-03, O-35 | storage lifecycle (server setup step 7) | screenshot in release checks |
| G-09 photo size and location data | BR-SEC-06 | T2.8 | Exif test |
| G-10 Urdu in PDFs | §13 | T6.10 | T-14 extra assertion |
| G-11 double submit | BR-GEN-11 | T1.13 and every create task | T-18 |
| G-12 deploy and rollback | BR-OPS-10 | `zb-deploy-release` | `test-deploy.sh` |
| G-13 handover | — | T8.7 | signed handover sheet |
| G-14 to G-17 stack facts | §5 | T0.1, T0.3, T2.1 | CI on PHP 8.3 and 8.5 |
| G-18 password rule | BR-SEC-02 | T5.2 | production-rule test |
| G-19 staging data | §18, BR-OPS-04 | runbook §4, `zb-restore` | restore drops the temporary database (`test-backup-restore.sh`) |
| G-20 no backup download | §7 | T7.6 | "no download route" test |
| G-21 secrets and audits | BR-SEC-11, BR-SEC-18 | `scripts/secret-scan.sh`, `check-all.sh`, CI, Dependabot | `test-guardrails.sh`; CI |
| G-22 owner recovery | BR-USR-09 | T5.2 | command tests |
| G-23 recovery targets | BR-OPS-05, O-36 | runbook §9 | timed rebuild drill before go-live |
| G-24 kit .gitignore | — | T0.1 | guard rule 7 |

## Corrections (spec update 4, 13 October 2026)

| Gap | Rules | Built in | Proof |
| --- | --- | --- | --- |
| G-25 no branch protection on GitHub Free | BR-OPS-10 | hook git guard, protected-files check on pull requests, main guard, release gate, ADR-0012 | `test-guardrails.sh` (git guard and main guard cases); release workflow gate step |
| G-26 network facts for building | — | `docs/stack-baseline.md` | T0.1 runs Composer and npm in the cloud session |
| G-27 local test user has no role | spec §7 | T1.3 | `local DatabaseSeeder gives test@example.com the owner role` |
| G-28 real data in trial-month notes | BR-SEC-17, `data-handling.md` rule 9 | T8.5 | review of `docs/feedback/T8.5.md` before each commit |
| G-29 no system data on a new server | BR-OPS-11 | T1.1 to T1.4, T2.3, T5.2, `zb-deploy-release` | seeder tests; `test-deploy.sh`; `zb:create-owner` refuses without system data |
