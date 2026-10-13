# Task cards (binding)

One card per task in `docs/tasks.md`. A card says exactly what to build, which rules it enforces, which tests prove it, and what is out of scope. It never contradicts `docs/spec.md`; it only adds the "how" detail. If a card and the spec seem to disagree, **stop and ask**.

| File | Milestone | Tasks |
| --- | --- | --- |
| `M0.md` | Setup | T0.1 – T0.3 |
| `M1.md` | Accounting core and security baseline | T1.1 – T1.13 |
| `M2.md` | First complete slice | T2.1 – T2.10 |
| `M3.md` | Core entries | T3.1 – T3.9 |
| `M4.md` | Remaining entries | T4.1 – T4.8 |
| `M5.md` | Controls and settings | T5.1 – T5.6 |
| `M6.md` | Checks and reports | T6.1 – T6.10 |
| `M7.md` | Month-end and remaining controls | T7.1 – T7.8 |
| `M8.md` | Release and handover | T8.1 – T8.7 |

## Card sections

- **Goal**: one sentence.
- **Client requirement**: the facts (F-xx) and decisions (D-xx) from spec §2 and §3 this task serves.
- **Depends on**: tasks that must be merged first.
- **Build exactly**: files, tables, classes, routes and pages. Nothing else.
- **Rules**: rule IDs enforced in this task. Each needs a comment in code and at least one test.
- **Tests (write first)**: named tests with exact inputs and outputs.
- **Not in this task**: things that look related but belong to a later task. Building them now is a deviation.
- **Done when**: the proof required before the pull request is merged.

## Who does what

Tasks marked **[Human]** are done by you, not by Claude Code. Claude may prepare files for them when the card says so.
