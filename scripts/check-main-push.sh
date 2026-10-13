#!/bin/bash
# Main guard: runs in CI on every push to main (job "main guard").
#
# GitHub Free cannot block direct pushes to main in a private repository, and the Claude Code
# cloud GitHub proxy does not limit which branch a session pushes to. So this check FAILS (and
# GitHub e-mails you) when:
#   - a commit reached main without a merged pull request (a direct push),
#   - main was rewritten (a force push), or
#   - a merged pull request changed protected files without [spec-change] in its title.
# The release workflow refuses to build from a commit whose CI run on main is not green.
#
# Needs: full git history (checkout with fetch-depth 0), gh with GH_TOKEN, and REPO, BEFORE, AFTER
# (github.repository, github.event.before, github.event.after). Run from the repository root.
set -euo pipefail
REPO="${REPO:?REPO is required (owner/name)}"
AFTER="${AFTER:?AFTER is required (the new main commit)}"
BEFORE="${BEFORE:-}"
ZERO="0000000000000000000000000000000000000000"

if [ -z "$BEFORE" ] || [ "$BEFORE" = "$ZERO" ]; then
  echo "First push of main (the starter pack): nothing to compare with."
  exit 0
fi
if ! git cat-file -e "${BEFORE}^{commit}" 2>/dev/null || ! git merge-base --is-ancestor "$BEFORE" "$AFTER"; then
  echo "MAIN GUARD FAIL: main was rewritten (force push). $BEFORE is no longer in its history."
  echo "Find out who did it and why before you merge or release anything (runbook, 'Suspected break-in')."
  exit 1
fi

FAIL=0
for c in $(git rev-list --first-parent --reverse "$BEFORE..$AFTER"); do
  short="$(git rev-parse --short "$c")"
  pr="$(gh api "repos/$REPO/commits/$c/pulls" \
        --jq '[.[] | select(.merged_at != null and .base.ref == "main")][0] | if . == null then "" else "\(.number)\t\(.title)" end')"
  if [ -z "$pr" ]; then
    echo "MAIN GUARD FAIL: commit $short reached main without a merged pull request (direct push):"
    git log -1 --format='    %h %an <%ae> %s' "$c"
    FAIL=1
    continue
  fi
  number="${pr%%$'\t'*}"
  title="${pr#*$'\t'}"
  echo "Commit $short came from pull request #$number: $title"
  if parent="$(git rev-parse --verify --quiet "$c^1")"; then
    if ! DIFF_RANGE="$parent..$c" PR_TITLE="$title" bash scripts/check-protected.sh; then
      FAIL=1
    fi
  fi
done

if [ "$FAIL" -ne 0 ]; then
  echo
  echo "What to do: do not release. Look at the commit(s) above. If a change must be undone, revert it"
  echo "through a normal pull request. Never rewrite main. (START-HERE section 6.)"
  exit 1
fi
echo "Main guard passed: every new commit on main came through a merged pull request."
