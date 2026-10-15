#!/usr/bin/env bash
# Runs every check the project requires, in the same order as CI, and prints a summary.
# Claude runs it before finishing every task (cloud session or laptop); CI runs the same checks
# again on every pull request and on main.
#
#   bash scripts/check-all.sh                  # on a task branch
#   bash scripts/check-all.sh --spec-change    # human only: protected files may differ from main
#
# Before task T0.1 there is no Laravel app yet, so only the repository checks run.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

SPEC_CHANGE=0
for a in "$@"; do
  case "$a" in
    --spec-change) SPEC_CHANGE=1 ;;
    *) echo "Unknown option: $a"; exit 2 ;;
  esac
done

RESULTS=()
FAILED=0
run() { # run <label> <command...>
  local label="$1"; shift
  printf '\n==> %s\n' "$label"
  if "$@"; then
    RESULTS+=("PASS  $label")
  else
    RESULTS+=("FAIL  $label")
    FAILED=1
  fi
}
skip() { RESULTS+=("skip  $1 ($2)"); }

run "Secret scan" bash scripts/secret-scan.sh
if git rev-parse --verify --quiet main >/dev/null && [ "$(git rev-parse --abbrev-ref HEAD)" != main ]; then
  run "Protected files unchanged (or approved)" env SPEC_CHANGE="$SPEC_CHANGE" bash scripts/check-protected.sh
else
  skip "Protected files check" "run it on a task branch"
fi

if [ -f artisan ]; then
  run "PHP tests (Pest, MariaDB)" php artisan test
  if [ -f infra/tests/ci-db-rules.sh ]; then
    run "Database rules" env DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=zb_mill_rulescheck DB_USERNAME=zb_test DB_PASSWORD=local_test_only bash infra/tests/ci-db-rules.sh
  else
    skip "Database rules" "added in T10.2"
  fi
  run "PHP code style (Pint)" vendor/bin/pint --test
  run "Static analysis (Larastan level 7)" vendor/bin/phpstan analyse --memory-limit=1G --no-progress
  run "Front-end build (also generates Wayfinder routes)" npm run build
  run "Front-end lint and format (vite-plus)" npm run check
  run "TypeScript" npm run types:check
  if node -e "process.exit(require('./package.json').scripts?.test ? 0 : 1)" 2>/dev/null; then
    run "Front-end unit tests" npm test
  else
    skip "Front-end unit tests" "added in T2.1"
  fi
  if [ -f tests/Arch/ArchitectureTest.php ]; then
    run "Project guard" bash scripts/guard.sh
  else
    skip "Project guard" "starts in T0.3"
  fi
  run "Known-vulnerable PHP packages (needs internet)" composer audit --locked
  run "Known-vulnerable JS packages (needs internet)" npm audit --omit=dev --audit-level=high
else
  skip "App checks" "no Laravel app yet: task T0.1 creates it"
fi

printf '\n==================== Summary ====================\n'
printf '%s\n' "${RESULTS[@]}"
if [ "$FAILED" -ne 0 ]; then
  printf '\nSOME CHECKS FAILED. Do not merge.\n'
  exit 1
fi
printf '\nALL CHECKS PASSED.\n'
