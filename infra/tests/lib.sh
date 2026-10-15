#!/usr/bin/env bash
# Shared helpers for the infrastructure tests. Sourced, not run.
# These tests run on a throwaway machine or CI runner. Never on the live server. Most of them need
# no root any more: on shared hosting everything runs as the account's own user, so there is no
# separate web/backup user to create and no service to restart.

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

# expect_eq "description" "expected" actual-command...
expect_eq() {
    local d="$1" want="$2"; shift 2
    local got
    got="$("$@" 2>&1)" || true
    if [ "$got" = "$want" ]; then pass "$d"; else fail "$d (expected '$want', got '${got//$'\n'/ }')"; fi
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

# -------------------------------------------------------------------------------------------------
# MariaDB / MySQL helpers
#
# Production is MariaDB 11.8 on shared hosting (ADR-0013), so the client tools there are named
# mariadb*, mariadb-dump and mariadbd. On a machine that only ships MySQL (the same database
# family, and what a laptop or a CI image may have) the mysql* names are used instead, so the
# tests still run. There is ONE database user with all rights on its own database (BR-SEC-12), so
# the tests prove the database RULES (triggers), not a privilege split.
# -------------------------------------------------------------------------------------------------

db_server_bin() { command -v mariadbd 2>/dev/null || command -v mysqld 2>/dev/null || true; }
db_client_bin() { command -v mariadb 2>/dev/null || command -v mysql 2>/dev/null || true; }
db_dump_bin()   { command -v mariadb-dump 2>/dev/null || command -v mysqldump 2>/dev/null || true; }
db_install_bin(){ command -v mariadb-install-db 2>/dev/null || command -v mysql_install_db 2>/dev/null || true; }

# Start a private, socket-only database server in <dir> (no networking, no password).
# Sets DBSOCK and ZB_DB_PRIVATE. Runs as the current user, or as the "mysql" service user when
# run as root (the server refuses to run as root).
db_test_server_start() {
    local dir="$1" server run_user install
    server="$(db_server_bin)"
    [ -n "$server" ] || { echo "no database server binary (mariadbd/mysqld) found"; exit 2; }
    if [ "$(id -u)" -eq 0 ]; then
        ensure_system_user mysql
        run_user=mysql
    else
        run_user="$(id -un)"
    fi
    DBSOCK="$dir/socket"
    ZB_DB_PRIVATE="$dir"
    mkdir -p "$dir/data"
    chmod 0755 "$dir"
    chown -R "$run_user" "$dir" 2>/dev/null || true
    install="$(db_install_bin)"
    if [ -n "$install" ]; then
        "$install" --no-defaults --datadir="$dir/data" --user="$run_user" \
            --auth-root-authentication-method=normal >"$dir/init.log" 2>&1
    else
        "$server" --no-defaults --initialize-insecure --datadir="$dir/data" --user="$run_user" \
            >"$dir/init.log" 2>&1
    fi
    "$server" --no-defaults --datadir="$dir/data" --socket="$DBSOCK" --pid-file="$dir/pid" \
        --skip-networking --log-error="$dir/server.log" --user="$run_user" >"$dir/start.log" 2>&1 &
    export DBSOCK ZB_DB_PRIVATE
    local i
    for i in $(seq 1 40); do
        db_client -e "SELECT 1" >/dev/null 2>&1 && return 0
        sleep 0.5
    done
    cat "$dir/server.log" "$dir/start.log" >&2 2>/dev/null || true
    echo "database server did not answer on $DBSOCK"; exit 2
}

db_test_server_stop() {
    local dir="$1" pid
    [ -n "$dir" ] || return 0
    if [ -r "$dir/pid" ]; then
        pid="$(cat "$dir/pid")"
        kill "$pid" 2>/dev/null || true
        local i
        for i in $(seq 1 20); do
            kill -0 "$pid" 2>/dev/null || break
            sleep 0.25
        done
    fi
}

# db_setup <work-dir>: use the server named by DB_* (a CI service container) when DB_EXISTING=1,
# otherwise start a private one when a server binary is present. Sets DBSOCK. Exits 2 when there is
# no server at all.
db_setup() {
    local dir="$1"
    if [ "${DB_EXISTING:-0}" != 1 ] && [ -n "$(db_server_bin)" ]; then
        db_test_server_start "$dir"
    elif [ -n "${DB_SOCKET:-}" ] || [ -n "${DB_HOST:-}" ]; then
        DBSOCK="${DB_SOCKET:-}"; export DBSOCK
    else
        echo "no database server: install mariadbd/mysqld, or set DB_HOST/DB_USERNAME (a service container)"; exit 2
    fi
    db_client -e "SELECT 1" >/dev/null 2>&1 || { echo "cannot connect to the database server"; exit 2; }
}

db_teardown() { [ -n "${ZB_DB_PRIVATE:-}" ] && db_test_server_stop "$ZB_DB_PRIVATE"; return 0; }

# The connection flags as a string, for an MIGRATE_CMD built in a test.
db_cli_str() {
    local s
    s="$(db_client_bin) --no-defaults"
    if [ -n "${DBSOCK:-}" ]; then
        s+=" --socket=$(printf '%q' "$DBSOCK")"
    else
        s+=" --host=$(printf '%q' "${DB_HOST:-127.0.0.1}") --port=$(printf '%q' "${DB_PORT:-3306}") --protocol=TCP"
    fi
    s+=" -u $(printf '%q' "${DB_ROOT_USER:-root}")"
    [ -n "${DB_ROOT_PASSWORD:-}" ] && s+=" -p$(printf '%q' "$DB_ROOT_PASSWORD")"
    printf '%s' "$s"
}

db_run() {
    local bin="$1"; shift
    local args=( --no-defaults )
    if [ -n "${DBSOCK:-}" ]; then
        args+=( --socket="$DBSOCK" )
    else
        args+=( --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --protocol=TCP )
    fi
    args+=( -u "${DB_ROOT_USER:-root}" )
    [ -n "${DB_ROOT_PASSWORD:-}" ] && args+=( "-p${DB_ROOT_PASSWORD}" )
    "$bin" "${args[@]}" "$@"
}

db_client() { db_run "$(db_client_bin)" "$@"; }
db_dump()   { db_run "$(db_dump_bin)" "$@"; }

db_q()  { local db="$1" sql="$2"; db_client -N -B "$db" -e "$sql"; }
db_x()  { local db="$1" sql="$2"; db_client "$db" -e "$sql"; }
db_file() { local db="$1" file="$2"; shift 2; db_client "$@" "$db" < "$file"; }
