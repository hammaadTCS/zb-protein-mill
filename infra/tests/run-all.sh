#!/usr/bin/env bash
# Runs every infrastructure test. On a THROWAWAY machine or a CI runner only:
#   bash infra/tests/run-all.sh
# Needs: PHP CLI, openssl, git, python3, age (one secret-scan check), and a database server +
# client (MariaDB 11.8 is the version of record, T10.1; mariadb* or mysql* are both found).
# No PostgreSQL, nginx or rclone, and, for most of them, no root.
set -uo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
FAILED=()
for t in test-guardrails.sh test-provision.sh test-mariadb.sh test-db-rules.sh test-backup-restore.sh test-deploy.sh test-health-check.sh test-web.sh; do
    printf '\n######## %s\n' "$t"
    if ! bash "$DIR/$t"; then FAILED+=("$t"); fi
done
echo
if [ ${#FAILED[@]} -eq 0 ]; then echo "ALL INFRASTRUCTURE TESTS PASSED"; exit 0; fi
echo "FAILED: ${FAILED[*]}"; exit 1
