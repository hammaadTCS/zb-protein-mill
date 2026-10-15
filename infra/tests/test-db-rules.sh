#!/usr/bin/env bash
# Runs infra/tests/ci-db-rules.sh against a throwaway database server that loads the fixture schema
# (stand-in for the real migrations), so the CI check is proved to (a) pass on a correct schema and
# (b) fail, naming the table, when a protection trigger is missing. No root required.
#   bash infra/tests/test-db-rules.sh
source "$(dirname "$0")/lib.sh"
[ -n "$(db_client_bin)" ] || { echo "no mariadb/mysql client found"; exit 2; }

WORK="$(mktemp -d /tmp/zb-dbtest.XXXXXX)"; chmod 0755 "$WORK"
cleanup() { db_teardown; rm -rf "$WORK"; }
trap cleanup EXIT

db_setup "$WORK"

# ci-db-rules.sh gets its own connection through the environment and loads the fixture as the
# "migration". It creates and drops its own database.
export DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}" DB_SOCKET="${DBSOCK:-}"
export DB_DATABASE=zb_mill_rulescheck
export DB_USERNAME="${DB_ROOT_USER:-root}" DB_PASSWORD="${DB_ROOT_PASSWORD:-}"
export DB_ADMIN_USERNAME="$DB_USERNAME" DB_ADMIN_PASSWORD="$DB_PASSWORD"

LOAD_FIXTURE="$(db_cli_str) \"\$DB_DATABASE\" < '$INFRA_DIR/tests/fixtures/schema.sql'"

echo "CI database-rules check with the real rules (must pass)"
export MIGRATE_CMD="$LOAD_FIXTURE"
if bash "$INFRA_DIR/tests/ci-db-rules.sh" > "$WORK/good.log" 2>&1; then
    pass "check passes on a correct schema ($(grep -c '  ok' "$WORK/good.log") assertions)"
else
    cat "$WORK/good.log"; fail "check should pass on a correct schema"
fi

echo "CI database-rules check with a protection trigger dropped (must fail)"
export MIGRATE_CMD="$LOAD_FIXTURE && $(db_cli_str) \"\$DB_DATABASE\" -e 'DROP TRIGGER journal_lines_prevent_update'"
if bash "$INFRA_DIR/tests/ci-db-rules.sh" > "$WORK/bad.log" 2>&1; then
    fail "check should fail when a protection trigger is missing"
else
    if grep -qi "journal_lines: UPDATE trigger missing" "$WORK/bad.log"; then
        pass "check fails and names the missing rule (journal_lines: UPDATE trigger missing)"
    else
        cat "$WORK/bad.log"; fail "check failed for the wrong reason"
    fi
fi

summary "CI database-rules check"
