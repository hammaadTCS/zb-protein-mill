#!/usr/bin/env bash
# Runs every infrastructure test. Root on a THROWAWAY Ubuntu machine or CI runner only:
#   sudo bash infra/tests/run-all.sh
# Needs: PostgreSQL 14+ server binaries, PHP with pdo_pgsql and php-fpm, nginx, age, rclone, openssl.
set -uo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
FAILED=()
for t in test-guardrails.sh test-provision.sh test-postgres.sh test-ci-privileges.sh test-backup-restore.sh test-deploy.sh test-health-check.sh test-web.sh; do
    printf '\n######## %s\n' "$t"
    if ! bash "$DIR/$t"; then FAILED+=("$t"); fi
done
echo
if [ ${#FAILED[@]} -eq 0 ]; then echo "ALL INFRASTRUCTURE TESTS PASSED"; exit 0; fi
echo "FAILED: ${FAILED[*]}"; exit 1
