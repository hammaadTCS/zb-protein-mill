#!/usr/bin/env bash
# CI check (BR-SEC-12, task T1.11): run the REAL migrations as the owner role, apply the
# production grants, then prove the web role cannot change structure or delete business rows.
#
# Needs a PostgreSQL 14+ superuser connection in the standard variables:
#   PGHOST, PGPORT, PGUSER, PGPASSWORD   (CI: the postgres service container)
# Optional:
#   PRIV_DB          database to create for the check (default zb_mill_privcheck)
#   MIGRATE_CMD      command that migrates the database as zb_migrator
#                    (default: php artisan migrate --force --no-interaction)
#   SQL_DIR          folder with the grant files (default infra/files/postgres; tests only)
#
# Roles get a throwaway password HERE ONLY, because CI connects over TCP.
# On real servers the same roles have no password and use peer authentication.
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SQL="${SQL_DIR:-$ROOT/infra/files/postgres}"
DB="${PRIV_DB:-zb_mill_privcheck}"
CI_ROLE_PASSWORD="ci-only-not-a-secret"
: "${PGHOST:?PGHOST is required}" "${PGUSER:?PGUSER is required}"
PGPORT="${PGPORT:-5432}"

PASS=0; FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

su_psql() { psql -X -q -v ON_ERROR_STOP=1 "$@"; }
as_role() {
    local role="$1"; shift
    PGUSER="$role" PGPASSWORD="$CI_ROLE_PASSWORD" psql -X -q -v ON_ERROR_STOP=1 -d "$DB" "$@"
}

echo "Database privilege check on $DB"

su_psql -d postgres -c "DROP DATABASE IF EXISTS $DB" >/dev/null
su_psql -d postgres -v db_name="$DB" -f "$SQL/10-roles.sql" >/dev/null
for r in zb_migrator zb_app zb_backup; do
    su_psql -d postgres -c "ALTER ROLE $r PASSWORD '$CI_ROLE_PASSWORD'" >/dev/null
done
su_psql -d "$DB" -f "$SQL/20-schema.sql" >/dev/null

echo "  migrating as zb_migrator"
if [ -n "${MIGRATE_CMD:-}" ]; then
    PGUSER=zb_migrator PGPASSWORD="$CI_ROLE_PASSWORD" PGDATABASE="$DB" bash -c "$MIGRATE_CMD"
else
    DB_CONNECTION=pgsql DB_HOST="$PGHOST" DB_PORT="$PGPORT" DB_DATABASE="$DB" \
        DB_USERNAME=zb_migrator DB_PASSWORD="$CI_ROLE_PASSWORD" \
        php "$ROOT/artisan" migrate --force --no-interaction
fi
as_role zb_migrator -f "$SQL/30-app-grants.sql" >/dev/null

# The two lists live only in 30-app-grants.sql; read them from there so they never drift.
list_from_sql() {
    sed -n "/$1 text\[\] := ARRAY\[/,/\];/p" "$SQL/30-app-grants.sql" | grep -o "'[a-z_]*'" | tr -d "'"
}
mapfile -t HOUSEKEEPING < <(list_from_sql housekeeping)
mapfile -t APPEND_ONLY < <(list_from_sql append_only)
in_list() { local x="$1"; shift; for i in "$@"; do [ "$i" = "$x" ] && return 0; done; return 1; }

mapfile -t TABLES < <(as_role zb_app -tA -c "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY 1")
echo "  ${#TABLES[@]} tables found"

denied() { # denied "<sql>"  -> true when the app role gets "permission denied" or "must be owner"
    local out
    if out="$(as_role zb_app -c "$1" 2>&1)"; then return 1; fi
    grep -qiE 'permission denied|must be owner' <<<"$out"
}

for t in "${TABLES[@]}"; do
    if in_list "$t" "${HOUSEKEEPING[@]}"; then
        if as_role zb_app -c "BEGIN; DELETE FROM \"$t\" WHERE false; ROLLBACK;" >/dev/null 2>&1; then
            ok "$t: app may delete (housekeeping)"
        else
            bad "$t: housekeeping table, but app cannot delete"
        fi
    else
        if denied "DELETE FROM \"$t\" WHERE false"; then ok "$t: app cannot delete"; else bad "$t: app CAN delete"; fi
    fi
    if denied "TRUNCATE \"$t\""; then ok "$t: app cannot truncate"; else bad "$t: app CAN truncate"; fi
    if denied "ALTER TABLE \"$t\" ADD COLUMN zb_priv_probe int"; then ok "$t: app cannot alter"; else bad "$t: app CAN alter"; fi
    if denied "ALTER TABLE \"$t\" DISABLE TRIGGER ALL"; then ok "$t: app cannot disable triggers"; else bad "$t: app CAN disable triggers"; fi
    if in_list "$t" "${APPEND_ONLY[@]}"; then
        if denied "UPDATE \"$t\" SET id = id WHERE false"; then ok "$t: app cannot update (append-only)"; else bad "$t: append-only table, but app CAN update"; fi
    fi
done

if denied "CREATE TABLE zb_priv_probe (id int)"; then ok "app cannot create tables"; else bad "app CAN create tables"; fi
if denied "INSERT INTO migrations (migration, batch) VALUES ('zb_priv_probe', 999)"; then ok "app cannot write migrations"; else bad "app CAN write migrations"; fi
if denied "SET session_replication_role = replica"; then ok "app cannot switch off triggers"; else bad "app CAN switch off triggers"; fi
server_major="$(as_role zb_app -tA -c "SHOW server_version_num")"; server_major=$((server_major / 10000))
client_major="$(pg_dump --version | grep -oE '[0-9]+' | head -1)"
if [ "$client_major" -lt "$server_major" ]; then
    echo "  skip  pg_dump $client_major is older than server $server_major (the backup test in infra/tests covers dumps)"
elif dump_err="$(PGUSER=zb_backup PGPASSWORD="$CI_ROLE_PASSWORD" pg_dump -d "$DB" -Fc --no-sync -f /dev/null 2>&1)"; then
    ok "backup role can dump everything"
else
    bad "backup role cannot dump: ${dump_err//$'\n'/ }"
fi

su_psql -d postgres -c "DROP DATABASE IF EXISTS $DB" >/dev/null || true

printf '\nPrivilege check: %d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
