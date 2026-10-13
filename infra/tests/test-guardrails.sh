#!/usr/bin/env bash
# Tests the guardrails that keep Claude Code (and mistakes) away from protected files, main and
# secrets: the PreToolUse hook, the protected-files check (pull requests and laptop), the main guard
# on every push to main, and the secret scan.
source "$(dirname "$0")/lib.sh"
REPO="$(cd "$INFRA_DIR/.." && pwd)"
WORK="$(mktemp -d /tmp/zb-grtest.XXXXXX)"
trap 'rm -rf "$WORK"' EXIT

echo "Protection hook (.claude/hooks/protect_files.py)"
hook() { # hook <tool> <json tool_input>  -> exit code of the hook
    printf '{"tool_name":"%s","tool_input":%s,"cwd":"%s"}' "$1" "$2" "$REPO" \
        | CLAUDE_PROJECT_DIR="$REPO" python3 "$REPO/.claude/hooks/protect_files.py" >/dev/null 2>&1
}
blocked() { local d="$1"; shift; if hook "$@"; then fail "$d (allowed)"; else pass "$d"; fi; }
allowed() { local d="$1"; shift; if hook "$@"; then pass "$d"; else fail "$d (blocked)"; fi; }

blocked "Write docs/spec.md"            Write '{"file_path":"'"$REPO"'/docs/spec.md"}'
blocked "Edit infra/bin/zb-backup-db"   Edit  '{"file_path":"'"$REPO"'/infra/bin/zb-backup-db"}'
blocked "Write docs/security.md"        Write '{"file_path":"'"$REPO"'/docs/security.md"}'
blocked "Write docs/adr/0001-x.md"      Write '{"file_path":"'"$REPO"'/docs/adr/0001-x.md"}'
blocked "Write docs/client/x.docx" Write '{"file_path":"'"$REPO"'/docs/client/x.docx"}'
blocked "Write docs/operations/runbook.md" Write '{"file_path":"'"$REPO"'/docs/operations/runbook.md"}'
blocked "Edit .env.production.example"  Edit  '{"file_path":"'"$REPO"'/.env.production.example"}'
blocked "Edit scripts/secret-scan.sh"   Edit  '{"file_path":"'"$REPO"'/scripts/secret-scan.sh"}'
blocked "Write .github/workflows/x.yml" Write '{"file_path":"'"$REPO"'/.github/workflows/x.yml"}'
blocked "Edit scripts/setup-local.sh"   Edit  '{"file_path":"'"$REPO"'/scripts/setup-local.sh"}'
blocked "Write README.md"               Write '{"file_path":"'"$REPO"'/README.md"}'
blocked "Write docs/frontend.md"        Write '{"file_path":"'"$REPO"'/docs/frontend.md"}'
blocked "Write docs/backend.md"         Write '{"file_path":"'"$REPO"'/docs/backend.md"}'
blocked "Edit .claude/settings.json"    Edit  '{"file_path":"'"$REPO"'/.claude/settings.json"}'
blocked "Bash: sed -i on infra/files/postgres/30-app-grants.sql" Bash '{"command":"sed -i s/a/b/ infra/files/postgres/30-app-grants.sql"}'
blocked "Bash: rm -rf infra"            Bash  '{"command":"rm -rf infra"}'
blocked "Bash: echo > docs/stack-baseline.md" Bash '{"command":"echo x > docs/stack-baseline.md"}'
blocked "Bash: rm -rf docs (contains protected files)" Bash '{"command":"rm -rf docs"}'
blocked "Bash: mv docs/cards /tmp"      Bash  '{"command":"mv docs/cards /tmp/cards"}'
allowed "Write app/Models/Party.php"    Write '{"file_path":"'"$REPO"'/app/Models/Party.php"}'
allowed "Write docs/tasks.md (Claude ticks tasks)" Write '{"file_path":"'"$REPO"'/docs/tasks.md"}'
allowed "Write docs/installed-versions.md (T0.1 records versions)" Write '{"file_path":"'"$REPO"'/docs/installed-versions.md"}'
allowed "Write docs/release-checks.md"  Write '{"file_path":"'"$REPO"'/docs/release-checks.md"}'
allowed "Edit .env.example (kit file)"  Edit  '{"file_path":"'"$REPO"'/.env.example"}'
allowed "Bash: php artisan test"        Bash  '{"command":"php artisan test"}'

echo "Git guard in the hook (Claude works on its task branch; only that branch may be pushed)"
G="$WORK/gitrepo"; mkdir -p "$G"
git -C "$G" init -q -b main; git -C "$G" -c user.email=t@t -c user.name=t commit -q --allow-empty -m base
git -C "$G" tag v1.0.0; git -C "$G" branch task/T0.2-files
phook() { # phook <command>  -> exit code of the hook with the throwaway repo as project dir
    python3 -c 'import json,sys; print(json.dumps({"tool_name":"Bash","tool_input":{"command":sys.argv[1]},"cwd":sys.argv[2]}))' "$1" "$G" \
        | CLAUDE_PROJECT_DIR="$G" python3 "$REPO/.claude/hooks/protect_files.py" >/dev/null 2>&1
}
pblocked() { if phook "$2"; then fail "$1 (allowed)"; else pass "$1"; fi; }
pallowed() { if phook "$2"; then pass "$1"; else fail "$1 (blocked)"; fi; }
# while on main
pblocked "commit on main"                              "git commit -m x"
pblocked "merge into main"                             "git merge task/T0.2-files"
pblocked "reset on main"                               "git reset --hard HEAD~1"
pblocked "rebase on main"                              "git rebase task/T0.2-files"
pblocked "pull into main"                              "git pull"
pblocked "commit on main hidden in bash -c"            "bash -c 'git commit -am x'"
pblocked "a file checkout does not hide a commit on main" "git checkout README.md && git commit -m x"
pblocked "moving main with git branch -f"              "git branch -f main task/T0.2-files"
pblocked "deleting main"                               "git branch -D main"
pblocked "resetting main with checkout -B"             "git checkout -B main task/T0.2-files"
pblocked "git update-ref on main"                      "git update-ref refs/heads/main HEAD"
pblocked "creating a tag"                              "git tag v2.0.0"
pblocked "check-all.sh --spec-change (human approval)" "bash scripts/check-all.sh --spec-change"
pblocked "git push origin main"                        "git push origin main"
pblocked "plain git push while on main"                "git push"
pblocked "git push origin HEAD while on main"          "git push origin HEAD"
pblocked "git push origin HEAD:main"                   "git push origin HEAD:main"
pblocked "force push into refs/heads/main"             "git push -f origin task/T0.2-files:refs/heads/main"
pblocked "git push +main"                              "git push origin +main"
pblocked "git push --tags"                             "git push --tags"
pblocked "git push of a tag by name"                   "git push origin v1.0.0"
pblocked "git push --all"                              "git push --all origin"
pblocked "deleting a remote branch"                    "git push origin :task/T0.2-files"
pblocked "git -C . push origin main after cd"          "cd . && git -C . push origin main"
pblocked "push hidden in bash -c"                      "bash -c 'git push origin main'"
pblocked "push behind sudo env VAR=1"                  "sudo env GIT_TRACE=1 git push origin main"
pblocked "checkout main then plain push"               "git checkout task/T0.2-files && git checkout main && git push"
pblocked "a file checkout does not hide a push of main" "git checkout README.md && git push"
pblocked "gh api merge (PUT)"                          "gh api -X PUT repos/o/r/pulls/1/merge"
pblocked "gh api write with fields (POST)"             "gh api repos/o/r/contents/x.md -f message=m -f content=eA=="
pblocked "gh release create"                           "gh release create v1.0.0"
pblocked "gh pr merge"                                 "gh pr merge 1 --squash"
pblocked "gh workflow run"                             "gh workflow run release.yml"
pallowed "create the task branch from main"            "git checkout -b task/T0.3-arch"
pallowed "new task branch then push it"                "git checkout -b task/T0.3-arch && git push -u origin task/T0.3-arch"
pallowed "push of the task branch by name"             "git push -u origin task/T0.2-files"
pallowed "force-with-lease push of the task branch"    "git push --force-with-lease origin task/T0.2-files"
pallowed "switch to the task branch then commit"       "git checkout task/T0.2-files && git commit -m 'T0.2: files'"
pallowed "check-all.sh"                                "bash scripts/check-all.sh"
pallowed "list tags"                                   "git tag -l"
pallowed "gh api read (GET)"                           "gh api repos/o/r/actions/runs?head_sha=abc"
pallowed "git status and diff on main"                 "git status && git diff"
git -C "$G" checkout -q task/T0.2-files
pallowed "commit on the task branch"                   "git commit -m 'T0.2: files'"
pallowed "merge main into the task branch"             "git merge main"
pallowed "plain push from the task branch"             "git push"
pallowed "a commit message that mentions git push origin main" "git commit -m 'do not git push origin main'"

echo "Protected-files check, CI pull request mode (scripts/check-protected.sh)"
cp -r "$REPO" "$WORK/repo"; cd "$WORK/repo"
rm -rf .git; git init -q -b main; git add -A; git -c user.email=t@t -c user.name=t commit -qm base
git clone -q --bare . "$WORK/origin.git"; git remote add origin "$WORK/origin.git"; git fetch -q origin
scenario() { # scenario <description> <expect pass|fail> <title> <command to change files>
    local d="$1" want="$2" title="$3" cmd="$4" rc
    git checkout -q -B "t-$RANDOM" origin/main
    bash -c "$cmd"
    git add -A; git -c user.email=t@t -c user.name=t commit -qm change --allow-empty
    if BASE_REF=main PR_TITLE="$title" bash scripts/check-protected.sh >/dev/null 2>&1; then rc=0; else rc=$?; fi
    if { [ "$want" = pass ] && [ $rc -eq 0 ]; } || { [ "$want" = fail ] && [ $rc -ne 0 ]; }; then pass "$d"; else fail "$d (exit $rc)"; fi
    git checkout -q main; git clean -qfdx
}
scenario "app change passes"                         pass "T2.7: purchases"  "mkdir -p app && echo x > app/A.php"
scenario "infra change without tag fails"           fail "T2.7: purchases"  "echo '# x' >> infra/files/postgres/30-app-grants.sql"
scenario "infra change with [spec-change] passes"   pass "[spec-change] grants" "echo '# x' >> infra/files/postgres/30-app-grants.sql"
scenario "new ADR without tag fails"                fail "T1.12: headers"   "mkdir -p docs/adr && echo x > docs/adr/0099-new.md"
scenario ".env.production.example change fails"    fail "T1.12: headers"   "echo X=1 >> .env.production.example"
scenario "a new script in scripts/ fails"           fail "T2.1: ui"         "echo x > scripts/new.sh"
scenario "docs/tasks.md tick passes"                pass "T0.1: app"        "echo '- [x] T0.1' >> docs/tasks.md"
scenario "new test in tests/Arch passes (adding is allowed)" pass "T0.3: arch" "mkdir -p tests/Arch && echo x > tests/Arch/New.php"

echo "Protected-files check, laptop mode (branch against main)"
local_case() { # local_case <description> <expect pass|fail> <SPEC_CHANGE 0|1> <command>
    local d="$1" want="$2" spec="$3" cmd="$4" rc
    git checkout -q -B "l-$RANDOM" main
    bash -c "$cmd"
    git add -A; git -c user.email=t@t -c user.name=t commit -qm change --allow-empty
    if SPEC_CHANGE="$spec" bash scripts/check-protected.sh >/dev/null 2>&1; then rc=0; else rc=$?; fi
    if { [ "$want" = pass ] && [ $rc -eq 0 ]; } || { [ "$want" = fail ] && [ $rc -ne 0 ]; }; then pass "$d"; else fail "$d (exit $rc)"; fi
    git checkout -q main; git clean -qfdx
}
local_case "app change passes"                          pass 0 "mkdir -p app && echo x > app/A.php"
local_case "card change on a task branch fails"         fail 0 "echo x >> docs/cards/M2.md"
local_case "card change approved with --spec-change passes" pass 1 "echo x >> docs/cards/M2.md"
cd "$REPO"


echo "Main guard (scripts/check-main-push.sh, every push to main)"
M="$WORK/main"; cp -r "$REPO" "$M"; cd "$M"
rm -rf .git; git init -q -b main; git add -A; git -c user.email=t@t -c user.name=t commit -qm "starter pack"
mkdir -p "$WORK/fakebin"; : > "$WORK/prs.tsv"
cat > "$WORK/fakebin/gh" <<'GH'
#!/bin/bash
# Stand-in for "gh api repos/R/commits/SHA/pulls --jq ...": prints "number<TAB>title" for commits
# listed in prs.tsv (sha, number, title), or nothing for a commit without a merged pull request.
sha="$(sed -E 's|.*/commits/([0-9a-f]+)/pulls.*|\1|' <<<"$2")"
awk -F'\t' -v s="$sha" '$1 == s { print $2 "\t" $3 }' "$PRS"
GH
chmod 0755 "$WORK/fakebin/gh"
commit() { # commit <message> <command>; prints the new sha
    bash -c "$2"; git add -A; git -c user.email=t@t -c user.name=t commit -qm "$1" --allow-empty; git rev-parse HEAD
}
merged() { printf '%s\t%s\t%s\n' "$1" "$2" "$3" >> "$WORK/prs.tsv"; }
guard() { # guard <before> <after>
    PATH="$WORK/fakebin:$PATH" PRS="$WORK/prs.tsv" REPO=o/r BEFORE="$1" AFTER="$2" bash scripts/check-main-push.sh
}
B0="$(git rev-parse HEAD)"
C1="$(commit "T2.7: purchases" "mkdir -p app && echo x > app/A.php")"; merged "$C1" 12 "T2.7: purchases"
expect_ok   "a merged pull request passes"                    guard "$B0" "$C1"
C2="$(commit "quick fix" "mkdir -p app && echo y > app/B.php")"
expect_fail_with "a direct push to main fails"                "without a merged pull request" guard "$C1" "$C2"
C3="$(commit "T2.8: photos" "echo '# x' >> infra/files/postgres/30-app-grants.sql")"; merged "$C3" 14 "T2.8: photos"
expect_fail_with "protected change merged without [spec-change] fails" "PROTECTED FILES CHANGED" guard "$C2" "$C3"
C4="$(commit "grants" "echo '# y' >> infra/files/postgres/30-app-grants.sql")"; merged "$C4" 15 "[spec-change] grants"
expect_ok   "protected change merged with [spec-change] passes" guard "$C3" "$C4"
expect_fail "a push with several commits fails if one of them was direct" guard "$C1" "$C4"
expect_ok   "the first push of main (the starter pack) passes" guard "0000000000000000000000000000000000000000" "$B0"
git checkout -q -b other "$B0"; X="$(commit "rewrite" "mkdir -p app && echo z > app/Z.php")"; git checkout -q main
expect_fail_with "a force push that rewrote main fails"       "rewritten" guard "$C4" "$X"
cd "$REPO"

echo "Secret scan (scripts/secret-scan.sh)"
cp -r "$REPO" "$WORK/s"; cd "$WORK/s"; rm -rf .git; git init -q; git add -A; git -c user.email=t@t -c user.name=t commit -qm base
expect_ok "the project itself has no secrets" bash scripts/secret-scan.sh
plant() { # plant <description> <content> [file name]
    local f="${3:-leak.txt}"
    printf '%s\n' "$2" > "$f"; git add -f "$f"
    if bash scripts/secret-scan.sh >/dev/null 2>&1; then fail "$1 (missed)"; else pass "$1"; fi
    git rm -q --cached "$f"; rm -f "$f"
}
plant "age private key is caught" "$(age-keygen 2>/dev/null | grep AGE-SECRET-KEY)"
plant "SSH private key is caught" "-----BEGIN OPENSSH PRIVATE KEY-----"
plant "filled APP_KEY is caught" "APP_KEY=base64:$(openssl rand -base64 32)"
plant "monitor ping URL is caught" "https://hc-ping.com/$(cat /proc/sys/kernel/random/uuid)"
plant "a database password is caught" "DB_PASSWORD=Very-Real-1"
plant "a committed .env is caught" "APP_ENV=production" ".env"
printf 'DB_PASSWORD=local_test_only\n' > ok.txt; git add ok.txt
expect_ok "the documented test password is allowed" bash scripts/secret-scan.sh
cd "$REPO"

summary "Guardrails"
