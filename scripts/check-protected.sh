#!/bin/bash
# Protected-files check: fails when rule files, tests of record, server files or tooling changed
# without the human's [spec-change] approval. Only the human changes protected files.
#
# Where it runs and what it compares:
#   in a session or on the laptop (scripts/check-all.sh): this branch against main
#   in CI on a pull request:   BASE_REF=main PR_TITLE="..."  (compares with origin/main)
#   in CI on a push to main:   DIFF_RANGE=<parent>..<commit> PR_TITLE=<merged pull request title> (check-main-push.sh)
# Approval: "[spec-change]" in the pull request title (PR_TITLE), in COMMIT_MESSAGE, or SPEC_CHANGE=1
# (check-all.sh --spec-change, human only).
set -u
BASE_REF="${BASE_REF:-}"
PR_TITLE="${PR_TITLE:-}"
COMMIT_MESSAGE="${COMMIT_MESSAGE:-}"
SPEC_CHANGE="${SPEC_CHANGE:-0}"
DIFF_RANGE="${DIFF_RANGE:-}"

if [ -z "$DIFF_RANGE" ]; then
  if [ -n "$BASE_REF" ]; then
    git fetch --quiet origin "$BASE_REF" || { echo "Cannot fetch base branch $BASE_REF"; exit 1; }
    DIFF_RANGE="origin/$BASE_REF...HEAD"
  else
    git rev-parse --verify --quiet main >/dev/null || { echo "No main branch to compare with"; exit 1; }
    DIFF_RANGE="main...HEAD"
  fi
fi
CHANGES=$(git diff --name-status "$DIFF_RANGE") || { echo "Cannot compare $DIFF_RANGE"; exit 1; }

PROTECTED_ANY='^(CLAUDE\.md|START-HERE\.md|README\.md|\.env\.production\.example|docs/(README|spec|requirements|requirements-audit|conventions|glossary|brand|prompts|traceability|architecture|frontend|backend|security|secrets|data-handling|environments|stack-baseline)\.md|docs/(cards|acceptance|templates|adr|operations|client)/|brand/|infra/|scripts/|\.claude/|\.github/)'
PROTECTED_EXISTING='^(tests/Acceptance/|tests/Arch/)'

VIOLATIONS=""
while IFS=$'\t' read -r status path rest; do
  [ -z "${status:-}" ] && continue
  if [[ "$path" =~ $PROTECTED_ANY ]] || [[ "${rest:-}" =~ $PROTECTED_ANY ]]; then
    VIOLATIONS+="$status $path ${rest:-}"$'\n'
  elif [[ "$status" != A ]] && [[ "$path" =~ $PROTECTED_EXISTING ]]; then
    VIOLATIONS+="$status $path ${rest:-}"$'\n'
  fi
done <<< "$CHANGES"

if [ -z "$VIOLATIONS" ]; then
  echo "No protected files changed."
  exit 0
fi
if [ "$SPEC_CHANGE" = 1 ] || [[ "$PR_TITLE" == *"[spec-change]"* ]] || [[ "$COMMIT_MESSAGE" == *"[spec-change]"* ]]; then
  echo "Protected files changed, approved as [spec-change]:"
  echo "$VIOLATIONS"
  exit 0
fi
echo "PROTECTED FILES CHANGED without [spec-change] approval:"
echo "$VIOLATIONS"
echo "Only the human changes these files (START-HERE, Step 9: 'When a rule has to change')."
exit 1
