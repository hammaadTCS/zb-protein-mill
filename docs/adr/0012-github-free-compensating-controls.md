# ADR-0012: GitHub Free with compensating controls instead of branch protection

**Status:** Accepted (13 October 2026, pack v4.1).

**Context.**
- **GitHub plan:** the developer uses GitHub Free. On a **private** repository, GitHub Free has no protected branches and no rulesets. GitHub accepts a push to `main` and lets anyone with write access merge a pull request whose checks are red.
- **Cloud sessions:** Claude Code builds each task in a cloud session that pushes with the developer's GitHub access. Its GitHub proxy rejects tag pushes and branch deletions, but the documentation says it "doesn't limit which branches a push can update".
- **Actions minutes:** GitHub Free gives 2,000 a month for private repositories and blocks Actions when they run out, unless a payment method is on file.
- **No public repository:** the code and documents describe the client's business, so making the repository public (free branch protection, unlimited minutes) is not acceptable.

**Decision.** Keep the repository private on GitHub Free and replace the server-side lock with:

1. **Prevent (Claude):** the PreToolUse hook and the deny rules in `.claude/settings.json` block:
   - committing, merging, rebasing, resetting or pulling while on `main`;
   - moving or deleting `main`;
   - pushing `main`, `--all`, `--mirror` or tags;
   - creating tags or deleting remote branches;
   - `gh pr merge`, `gh release`, `gh workflow`, and every `gh api` call that is not a GET;
   - approving its own protected-file changes (`check-all.sh --spec-change`).

   Claude may push only its own task branch. Sessions start in Plan mode. `CLAUDE.md` states the rule.
2. **Check every pull request:** both PHP versions, PostgreSQL 18, guards, secret scan, audits, and the protected-files check, which fails unless the title contains `[spec-change]`.
3. **Detect (every push to `main`):** the CI job **main guard** (`scripts/check-main-push.sh`) fails, and GitHub e-mails the developer, when:
   - a new first-parent commit on `main` is not part of a merged pull request;
   - `main` was rewritten;
   - a merged pull request changed protected files without `[spec-change]` in its title.

   Runs on `main` are never cancelled, so every push is checked.
4. **Gate (release):** the release workflow refuses to build unless the CI run on `main` for the tagged commit completed green (and the Infra run, when there was one). It then runs the full test suite again. Only released archives reach a server (ADR-0005).
5. **Human rule:** merge only when every check is green and none is still running.
6. **Minutes budget:**
   - both PHP versions on pull requests, PHP 8.5 only on `main`;
   - server-script tests only when server files change;
   - Dependabot monthly and grouped.

**Consequences.**
- **What can still happen:** a red pull request can still be merged by mistake, and a direct push to `main` is reported after it happens, not refused. Neither can reach a server, because the release gate checks CI on the exact commit. A bad commit stays in history and is undone with a revert pull request.
- **Hook limits:** the hook is a client-side check. A determined workaround, such as a script file that pushes, would get past it; the main guard then reports it.
- **Upgrade path:** if the company later pays for GitHub Pro or Team, a branch ruleset (repository Settings → Rules → Rulesets) is added on top of all of this.

**Rejected alternatives.**
- **Public repository:** exposes the client's business rules and operating documents.
- **GitHub Pro for the developer:** not available now; it remains the recommended upgrade.
- **Self-hosted runner for unlimited minutes:** another machine to secure and keep online, with the repository's code on it.
