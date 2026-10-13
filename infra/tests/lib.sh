#!/usr/bin/env bash
# Shared helpers for the infrastructure tests. Sourced, not run.
# These tests run on a throwaway Ubuntu machine or container as root. Never on the live server.

set -Eeuo pipefail

INFRA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PASS=0
FAIL=0

pass() { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
fail() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# expect_ok "description" command...
expect_ok() {
    local d="$1"; shift
    if "$@" >/dev/null 2>&1; then pass "$d"; else fail "$d"; fi
}

# expect_fail "description" command...   (the command must fail)
expect_fail() {
    local d="$1"; shift
    if "$@" >/dev/null 2>&1; then fail "$d (it succeeded)"; else pass "$d"; fi
}

# expect_fail_with "description" "text that must appear in the error" command...
expect_fail_with() {
    local d="$1" needle="$2"; shift 2
    local out
    if out="$("$@" 2>&1)"; then
        fail "$d (it succeeded)"
    elif grep -qiF -- "$needle" <<<"$out"; then
        pass "$d"
    else
        fail "$d (failed, but without '$needle': ${out//$'\n'/ })"
    fi
}

summary() {
    printf '\n%s: %d passed, %d failed\n' "$1" "$PASS" "$FAIL"
    [ "$FAIL" -eq 0 ]
}

require_root() {
    if [ "$(id -u)" -ne 0 ]; then echo "Run as root (on a test machine, never on the live server)."; exit 2; fi
}

ensure_system_user() {
    local u="$1"
    id "$u" >/dev/null 2>&1 || useradd --system --user-group --no-create-home --shell /usr/sbin/nologin "$u"
}

pg_bin_dir() {
    local d
    d="$(ls -d /usr/lib/postgresql/*/bin 2>/dev/null | sort -V | tail -1)"
    [ -n "$d" ] || { echo "PostgreSQL binaries not found"; exit 2; }
    echo "$d"
}

# Start a private PostgreSQL cluster in $1 using the project's pg_hba/pg_ident/conf files.
# Sets PGSOCK. The cluster listens on the Unix socket only.
pg_test_cluster_start() {
    local dir="$1" bin
    bin="$(pg_bin_dir)"
    PGSOCK="$dir/sock"
    mkdir -p "$dir/data" "$PGSOCK"
    chown -R postgres:postgres "$dir"
    chmod 0755 "$dir" "$PGSOCK"
    runuser -u postgres -- "$bin/initdb" -D "$dir/data" --auth-local=peer --auth-host=reject -E UTF8 >/dev/null
    cp "$INFRA_DIR/files/postgres/pg_hba.conf" "$dir/data/pg_hba.conf"
    cp "$INFRA_DIR/files/postgres/pg_ident.conf" "$dir/data/pg_ident.conf"
    mkdir -p "$dir/data/conf.d"
    cp "$INFRA_DIR/files/postgres/zb-mill.conf" "$dir/data/conf.d/zb-mill.conf"
    # Smaller memory for a test container; everything else from the real file.
    printf "shared_buffers = 128MB\neffective_cache_size = 512MB\n" > "$dir/data/conf.d/zz-test.conf"
    echo "include_dir = 'conf.d'" >> "$dir/data/postgresql.conf"
    chown -R postgres:postgres "$dir/data"
    runuser -u postgres -- "$bin/pg_ctl" -D "$dir/data" -l "$dir/server.log" -w \
        -o "-k $PGSOCK -p 5499" start >/dev/null
    export PGHOST="$PGSOCK" PGPORT=5499
}

pg_test_cluster_stop() {
    local dir="$1" bin
    bin="$(pg_bin_dir)"
    runuser -u postgres -- "$bin/pg_ctl" -D "$dir/data" -m fast -w stop >/dev/null 2>&1 || true
}

# psql_as <os-user> <db-role> <database> <sql>
psql_as() {
    local os="$1" role="$2" db="$3" sql="$4"
    runuser -u "$os" -- env PGHOST="$PGHOST" PGPORT="$PGPORT" \
        psql -X -q -v ON_ERROR_STOP=1 -U "$role" -d "$db" -c "$sql"
}

# q_as <os-user> <db-role> <database> <sql>  -> prints the bare result
q_as() {
    local os="$1" role="$2" db="$3" sql="$4"
    runuser -u "$os" -- env PGHOST="$PGHOST" PGPORT="$PGPORT" \
        psql -X -q -t -A -v ON_ERROR_STOP=1 -U "$role" -d "$db" -c "$sql"
}

# expect_eq "description" "expected" actual-command...
expect_eq() {
    local d="$1" want="$2"; shift 2
    local got
    got="$("$@" 2>&1)" || true
    if [ "$got" = "$want" ]; then pass "$d"; else fail "$d (expected '$want', got '${got//$'\n'/ }')"; fi
}

psql_file_as() {
    local os="$1" role="$2" db="$3" file="$4"; shift 4
    runuser -u "$os" -- env PGHOST="$PGHOST" PGPORT="$PGPORT" \
        psql -X -q -v ON_ERROR_STOP=1 -U "$role" -d "$db" "$@" -f "$file"
}
