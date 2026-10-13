# Handover to the company (task T8.7)

The goal: after handover the business owns everything, can keep running without you, and can bring in any other developer. Complete every row; the Owner signs at the end.

## 1. Accounts the company must own

| Account | Owner of record | Two-factor on | Second person with access | Done |
| --- | --- | --- | --- | --- |
| VPS provider (production + staging) | Company | ☐ | ☐ | ☐ |
| Domain registrar and DNS | Company | ☐ | ☐ | ☐ |
| Backup storage | Company | ☐ | ☐ | ☐ |
| Monitoring service | Company | ☐ | ☐ | ☐ |
| GitHub organisation holding the repository (Free works with the checks of ADR-0012; **Team** adds a server-side lock on `main` and is recommended if the company will pay for it) | Company (you as a member) | ☐ | ☐ | ☐ |
| Password manager vault with every entry of `docs/secrets.md` | Company | ☐ | ☐ | ☐ |

If any account was opened in your name, transfer it now (most providers support moving a resource or a whole account).

## 2. Keys and copies

| Item | Holder 1 | Holder 2 | Done |
| --- | --- | --- | --- |
| Backup private key (S-02) | Owner (sealed paper) | Company's second person (password manager) | ☐ |
| `APP_KEY` production (S-01) | Owner (sealed paper) | Password manager | ☐ |
| `APP_KEY` staging | Password manager | — | ☐ |
| Server admin access (SSH key + sudo) | You (until support ends) | The company's IT person or next developer | ☐ |

After support ends: remove your SSH key from the servers, leave the GitHub organisation, and hand over your password-manager entries. The company changes every secret you knew (`runbook.md` §8).

## 3. Documents handed over

- Signed Requirements v1.2 and the final `docs/spec.md` with its change log
- `docs/architecture.md`, `docs/adr/`, `docs/security.md`, `docs/secrets.md`, `docs/data-handling.md`, `docs/environments.md`
- `docs/operations/` (this file, server setup, runbook)
- `docs/release-checks.md` with the go-live evidence and the last restore test
- User guide for Owner and Accountant (from T8.5 training notes)

## 4. Proven before signing

| Check | Evidence | Done |
| --- | --- | --- |
| A person other than you restored a backup on staging using the runbook alone | Entry in `release-checks.md` | ☐ |
| The Owner can find the backup key and `APP_KEY` copies | Owner confirms | ☐ |
| Alerts reach the Owner's phone | Test alert received | ☐ |
| All OPEN items answered and entered | Spec §4 table complete | ☐ |
| Trial month closed with zero unexplained differences (T8.5) | `docs/feedback/T8.5.md` | ☐ |

## 5. Sign-off

| | Name | Date | Signature |
| --- | --- | --- | --- |
| For the company (Owner) | | | |
| Developer | | | |

Support after handover (bug fixes, upgrades, changes) is a separate agreement (spec §20.3).
