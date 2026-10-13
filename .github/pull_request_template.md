## Task

<!-- Task ID and name, exactly as in docs/tasks.md. Example: T2.7 Purchases with fixed rate, payments table, "paid now" -->

Card: `docs/cards/M?.md` → T?.?

## What was built

<!-- Files, tables, routes, pages. Only what the card lists. -->

## Tests added

<!-- Test names (each starts with the rule or test ID). -->

## Checks run

`bash scripts/check-all.sh` summary, pasted as printed:

```text
(paste the Summary block here)
```

## Questions and proposed spec changes

<!-- Anything unclear, and any change Claude proposes to protected files. Claude never makes these changes itself. -->

## Review checklist (you, before Merge)

- [ ] The diff touches only what the card lists
- [ ] No float, parseFloat, round or number_format near money, kg or rate
- [ ] Journal rows only through PostingService
- [ ] Rule IDs in comments and test names
- [ ] Money tests assert exact strings
- [ ] No existing test changed or deleted
- [ ] Interface words match docs/glossary.md
- [ ] No secrets, real data, personal data in logs, or calls/scripts/fonts from other sites
- [ ] New migrations work with the previous release (add first, remove later)
- [ ] Task ticked in docs/tasks.md
- [ ] Every check on this pull request is green and none is still running (both PHP jobs; Infra if it ran)
- [ ] After merging: pulled on the laptop and checked the screens on a phone and on desktop
