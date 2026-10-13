#!/usr/bin/env bash
# Runs infra/tests/ci-db-privileges.sh against a local PostgreSQL with TCP + password auth
# (the way GitHub Actions connects), using the fixture schema instead of Laravel migrations.
# Also proves the check FAILS when the grants are too wide.
source "$(dirname "$0")/lib.sh"
require_root

WORK="$(mktemp -d /tmp/zb-citest.XXXXXX)"; chmod 0755 "$WORK"
BIN="$(pg_bin_dir)"
cleanup() { runuser -u postgres -- "$BIN/pg_ctl" -D "$WORK/data" -m fast -w stop >/dev/null 2>&1 || true; rm -rf "$WORK"; }
trap cleanup EXIT

mkdir -p "$WORK/data" "$WORK/sock"; chown -R postgres:postgres "$WORK"
runuser -u postgres -- "$BIN/initdb" -D "$WORK/data" --auth-local=peer --auth-host=scram-sha-256 >/dev/null
runuser -u postgres -- "$BIN/pg_ctl" -D "$WORK/data" -l "$WORK/log" -w \
    -o "-k $WORK/sock -p 5498 -c listen_addresses=127.0.0.1" start >/dev/null
runuser -u postgres -- psql -X -q -h "$WORK/sock" -p 5498 -c "ALTER ROLE postgres PASSWORD 'local-test'" postgres

export PGHOST=127.0.0.1 PGPORT=5498 PGUSER=postgres PGPASSWORD=local-test
cp "$INFRA_DIR/tests/fixtures/schema.sql" "$WORK/schema.sql"
export MIGRATE_CMD="psql -X -q -v ON_ERROR_STOP=1 -f $WORK/schema.sql"

echo "CI privilege check with the real grants (must pass)"
if bash "$INFRA_DIR/tests/ci-db-privileges.sh" > "$WORK/good.log" 2>&1; then
    pass "check passes with infra/files/postgres grants ($(grep -c '  ok' "$WORK/good.log") assertions)"
else
    cat "$WORK/good.log"; fail "check should pass with the real grants"
fi

echo "CI privilege check with deliberately wrong grants (must fail)"
mkdir -p "$WORK/badsql"; cp "$INFRA_DIR"/files/postgres/*.sql "$WORK/badsql/"
echo "GRANT DELETE, TRUNCATE ON ALL TABLES IN SCHEMA public TO zb_app;" >> "$WORK/badsql/30-app-grants.sql"
if SQL_DIR="$WORK/badsql" bash "$INFRA_DIR/tests/ci-db-privileges.sh" > "$WORK/bad.log" 2>&1; then
    fail "check should fail when the app may delete business rows"
else
    if grep -q "purchases: app CAN delete" "$WORK/bad.log" && grep -q "app CAN truncate" "$WORK/bad.log"; then
        pass "check fails and names the table (purchases: app CAN delete / truncate)"
    else
        cat "$WORK/bad.log"; fail "check failed for the wrong reason"
    fi
fi

summary "CI privilege check"
