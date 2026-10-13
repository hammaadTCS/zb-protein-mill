# Prompts for Claude Code cloud sessions

How to use this file:

1. Go to **claude.ai/code**. Choose repository `zb-protein-mill`, branch **main**, mode **Plan**.
2. Copy the prompt below, fill in the `{braces}`, paste it, and press Enter.
3. Read Claude's plan next to the task's card. When it matches, approve it; if asked, switch to **Accept edits**.
4. When Claude is done, use **Create PR**. Then, on GitHub: wait until every check is green, go through the checklist (section 5), and click **Merge**.
5. One task per session. Start a new session for the next task.

---

## 1. First task: T0.1 (paste exactly)

```text
Task: T0.1 – Laravel app, React starter kit, Pest, PostgreSQL, CI.

Read CLAUDE.md, then docs/cards/M0.md section "T0.1" completely, then
docs/stack-baseline.md and docs/conventions.md.
If docs/spec.md or docs/cards/ is missing, stop and tell me.

This repository already contains project files. Keep every one of them exactly as it is:
README.md, CLAUDE.md, START-HERE.md, .env.production.example, docs/, brand/, infra/,
scripts/, .claude/, .github/. A hook will block you if you try to change them.

Work on your task branch, never on main. Push only your task branch.

Follow the card's numbered "Build exactly" steps in order, running the exact commands.
Create the app in a TEMPORARY directory outside the repo, then move its files into the
repo root without overwriting any existing file. Do not copy the kit's .github/ folder.
Do not install Laravel Boost. Install no package the card does not name.

If any command fails, or anything differs from docs/stack-baseline.md (versions, file
names, commands), stop and report exactly what you saw. Do not work around it.

Done when (from the card): composer install, npm ci, npm run build, php artisan migrate,
php artisan test, vendor/bin/pint --test, vendor/bin/phpstan analyse, npm run check,
npm run types:check and bash scripts/secret-scan.sh all pass, on PostgreSQL, and
docs/installed-versions.md exists. Then run bash scripts/check-all.sh until it prints
ALL CHECKS PASSED.

Tick T0.1 in docs/tasks.md, commit with messages starting "T0.1: ", and push the branch.
Final message: .github/pull_request_template.md filled in, including the check-all summary.
```

---

## 2. Every other task (template)

Fill in three things:
- the task ID;
- its name exactly as in `docs/tasks.md`;
- the card file (`M0` … `M8`: the number before the dot).

Example for T0.2: `{T-ID}` = `T0.2`, `{task name}` = `Project files check, brand icons, remove kit features the spec forbids`, `{M?}` = `M0`.

```text
Task: {T-ID} – {task name exactly as in docs/tasks.md}

1. Read CLAUDE.md. Then read docs/cards/{M?}.md section "{T-ID}" completely.
   Then read every spec section and rule ID the card names in docs/spec.md,
   and docs/conventions.md. For screens also docs/frontend.md, docs/glossary.md and
   docs/brand.md. For data, money and security also docs/backend.md.
2. Run bash scripts/check-all.sh. If anything fails before you start, stop and report.
   Do not fix failures you did not cause.
3. Plan (wait for my approval before writing code):
   - every file you will create or change;
   - every migration with its exact columns (and why it works with the previous release);
   - every test from the card's "Tests" list, with its exact expected values;
   - anything the card, spec or conventions leave unclear or contradict.
4. After approval, work on your task branch (never on main). Build only the card's
   "Build exactly" list. Nothing from "Not in this task".
5. Run bash scripts/check-all.sh. Fix until it prints ALL CHECKS PASSED.
   Never edit an existing test to make it pass.
6. Tick {T-ID} in docs/tasks.md. Commit (messages start "{T-ID}: ") and push the branch.

Final message: .github/pull_request_template.md filled in, including the check-all
summary and "Questions and proposed spec changes".
```

---

## 3. Fix prompts (paste into the same session)

**A check on the pull request is red**

```text
The pull request check "{check name}" fails with:
{paste the failing lines}
Find the cause, fix it on your branch, run bash scripts/check-all.sh until ALL CHECKS
PASSED, and push. Do not edit existing tests or protected files.
```

**An old test fails after the new work**

```text
Test {test name} now fails. Do not edit that test. Tell me which rule or acceptance
case it protects and why your change breaks it. Then fix your code so both the old
and new tests pass.
```

**Claude built more than the card**

```text
You changed things outside the {T-ID} card: {list}. Revert those changes. Build only
the card's "Build exactly" list.
```

**The spec or card is unclear**

```text
Do not guess. Write the question with the document and section, the possible answers,
and what each answer would change in the code. Then stop.
```

**The pull request has conflicts or is out of date**

```text
main has moved. Run git fetch origin && git merge origin/main on your branch, fix any
conflicts without changing protected files or existing tests, run bash scripts/check-all.sh
until ALL CHECKS PASSED, and push.
```

**Continue an unfinished task** (reopen the same session; the conversation is kept)

```text
Continue {T-ID}. Run bash scripts/check-all.sh and summarise what is done, what is left
(compared with the card's "Build exactly" list) and what is failing. Then wait for me.
```

**A money number is wrong**

```text
For {entry}, the expected result is {expected} (from {docs/acceptance file or card}),
but the system shows {actual}. Write a failing test that reproduces it first, then
fix the code. Show the journal lines before and after the fix.
```

**Claude says a protected file must change**

```text
Show me the exact text you propose for {file} and why. Do not try to change it.
```

Then you make the change yourself as a `[spec-change]` (START-HERE, Step 9).

---

## 4. Dependabot pull requests (monthly)

Once a month, Dependabot opens up to two pull requests: one with PHP package updates and one with JavaScript package updates (minor and patch only).

- **All checks green:** merge it, then pull on your laptop (`composer install && npm ci`).
- **A check is red:** do not merge. Start a session on that branch with:

```text
Branch {dependabot branch}: the package update breaks "{check name}":
{paste the failing lines}
Find which updated package causes it. If a small code change following the package's
upgrade notes fixes it, make it; otherwise tell me which package to hold back.
Never edit existing tests or protected files. Run bash scripts/check-all.sh and push.
```

Major updates (for example a new Laravel or React major version) become a planned task with its own card, written by you through a `[spec-change]`.

---

## 5. Your review checklist for every pull request

The same list is at the end of each pull request's description. Do not merge until every line is true.

- [ ] The diff touches only what the card's "Build exactly" lists (GitHub: **Files changed** tab).
- [ ] Search the diff for `float`, `parseFloat`, `round(`, `number_format`, `Number(` near money, kg or rate. None.
- [ ] Journal entries are created only through `PostingService`.
- [ ] Rule IDs appear in comments and test names.
- [ ] Money tests assert exact strings; acceptance tests load the JSON files.
- [ ] No existing test was changed or deleted.
- [ ] Interface words match `docs/glossary.md`.
- [ ] No secrets, real data, personal data in logs, or anything loaded from other sites.
- [ ] New migrations work with the previous release.
- [ ] The task is ticked in `docs/tasks.md`.
- [ ] Every check is green and none is still running. On GitHub Free nothing else stops a red merge.
- [ ] After merging: pull on the laptop and check the new screens on a phone and on desktop (START-HERE, Step 8).
