#!/bin/bash
# Secret scan (BR-SEC-11, BR-SEC-18): fails if a key, password file or monitor URL is committed.
# Runs in CI and in scripts/check-all.sh (before every merge): bash scripts/secret-scan.sh
# Scans files tracked by git (or every file when this is not a git checkout).
# infra/tests/ is skipped: it holds clearly labelled, throwaway test values only.
set -u

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  mapfile -t FILES < <(git ls-files)
else
  mapfile -t FILES < <(find . -type f -not -path './.git/*' -not -path './node_modules/*' -not -path './vendor/*' | sed 's|^\./||')
fi

FAIL=0
report() { echo "SECRET SCAN FAIL: $1"; printf "%s\n" "$2" | while IFS= read -r l; do echo "    $l"; done; FAIL=1; }

SCAN=()
for f in "${FILES[@]}"; do
  case "$f" in
    infra/tests/*|vendor/*|node_modules/*|public/build/*|*.png|*.jpg|*.jpeg|*.webp|*.ico|*.pdf|*.woff|*.woff2|*.zip|*.gz) continue ;;
  esac
  [ -f "$f" ] && SCAN+=("$f")
done

# 1. Environment files with real values. Only the two templates may be committed.
ENVS="$(printf '%s\n' "${FILES[@]}" | grep -E '(^|/)\.env(\..+)?$' | grep -vE '(^|/)\.env\.(example|production\.example)$' || true)"
[ -n "$ENVS" ] && report "environment file committed (keep it on the server only)" "$ENVS"

if [ ${#SCAN[@]} -gt 0 ]; then
  # 2. Private keys of any kind
  H="$(grep -nE 'AGE-SECRET-KEY-1[0-9A-Z]{58}|-----BEGIN ([A-Z]+ )*PRIVATE KEY-----' "${SCAN[@]}" 2>/dev/null || true)"
  [ -n "$H" ] && report "private key in the repository" "$H"

  # 3. A filled-in Laravel APP_KEY
  H="$(grep -nE 'APP_KEY=base64:[A-Za-z0-9+/]{20,}' "${SCAN[@]}" 2>/dev/null || true)"
  [ -n "$H" ] && report "APP_KEY value in the repository" "$H"

  # 4. Monitor ping URLs (anyone with the URL can fake "backup OK")
  H="$(grep -nE 'hc-ping\.com/[0-9a-f]{8}-[0-9a-f]{4}-' "${SCAN[@]}" 2>/dev/null || true)"
  [ -n "$H" ] && report "monitor ping URL in the repository" "$H"

  # 5. Database or storage passwords other than the documented test value
  H="$(grep -nE '^(DB_PASSWORD|AWS_SECRET_ACCESS_KEY|B2_APPLICATION_KEY)=[^[:space:]]+' "${SCAN[@]}" 2>/dev/null \
       | grep -vE 'DB_PASSWORD=local_test_only$' || true)"
  [ -n "$H" ] && report "password or storage key in the repository" "$H"
fi

if [ $FAIL -ne 0 ]; then
  echo ""
  echo "Remove the secret from the commit AND treat it as leaked: change it (docs/operations/runbook.md, 'A secret leaked')."
  exit 1
fi
echo "Secret scan passed."
