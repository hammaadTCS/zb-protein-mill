# ADR-0005: CI builds releases; a person deploys; symlink switch and rollback

**Status:** Accepted (11 October 2026). Replaces the manual "upload code" steps of spec v1.0 §18.2.

**Context.** Code is written by Claude Code on task branches on the developer's laptop and merged by the developer. The rule "Claude never deploys" must hold even if a repository, a GitHub token or a CI workflow is compromised. Deploys must be repeatable and reversible.

**Decision.**
- Pushing a `v*` tag on `main` makes GitHub Actions run the tests again and build `zb-mill-vX.Y.Z.tar.gz` (production `vendor`, built front end, no tests, no `.env`) with a SHA-256 checksum, published as a GitHub release.
- **No server credentials exist in GitHub or in Claude's environment.** You download the release on your computer (`infra/hostinger/ship-release.sh`), which checks the checksum, copies it to the host over SSH and runs `zb-deploy-release <file> --sha256 <hash>` there (no `sudo`; shared hosting has no root).
- The script verifies the checksum, unpacks into a new `releases/<id>` folder, takes a database backup, uses maintenance mode, migrates (as the one database user, D-43), adds any missing system data (`SystemDataSeeder`, BR-OPS-11, added 13 October 2026), rebuilds caches, switches the `current` link atomically, and smoke-tests `/up`; it switches back automatically if the smoke test fails. `--rollback` switches to the previous release.
- **Migrations must work with the previous release** (add columns and tables first, remove only in a later release). `migrate:rollback` is never run in production.

**Consequences.** Deploys take a few seconds of maintenance mode. Every deploy is logged with who, what and the checksum. A bad release is undone in seconds; a bad migration is fixed forward with a new migration. Proven by `infra/tests/test-deploy.sh`.

**Rejected alternatives.** CI deploying over SSH (puts a server key in GitHub; one leaked token = server access); `git pull` on the server (needs GitHub credentials and build tools on the server); building on the server (Node and dev packages on production).
