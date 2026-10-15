#!/usr/bin/env bash
# CI check (BR-SEC-12/ADR-0014, ADR-0016, T10.2): run the REAL migrations, then prove the database
# rules are really in the schema. Shared hosting gives ONE database user with all rights on its own
# database, so the two-role privilege split of the VPS days (T1.11) cannot exist any more. What
# keeps the books safe now is the database itself: the protection triggers and the seal trigger.
# This check proves, on the migrated schema, that
#   - the server is a supported MariaDB (10.2+) or the same MySQL family;
#   - every table is InnoDB;
#   - journal_entries, journal_lines, journal_seals and purchases exist;
#   - the required triggers are present (update/delete refused on the journal, delete refused on a
#     master/transaction table, an insert trigger on journal_seals);
#   - every trigger body uses SIGNAL and none is left as a PostgreSQL plpgsql RAISE;
#   - the seal trigger reads journal_lines;
#   - journal_lines carries the one-side CHECK;
#   - no PostgreSQL helper routines (prevent_change/prevent_delete/check_journal_balance) remain;
#   - mariadb-dump keeps the triggers.
# The refusal BEHAVIOUR on a real schema is proved by the app's own refusal tests (T10.2) and, with
# the fixture, by infra/tests/test-mariadb.sh; here a missing or wrong trigger is caught by name.
#
# Connection (CI: a mariadb:11.8 service container):
#   DB_HOST, DB_PORT, DB_SOCKET, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#   DB_ADMIN_USERNAME / DB_ADMIN_PASSWORD   (default: the same user; used only to create/drop the
#                                            throwaway check database)
# Optional:
#   MIGRATE_CMD   command that migrates the check database (default: php artisan migrate --force)
#   DB_CLIENT     client binary   (default: mariadb, else mysql)
#   DB_DUMP       dump binary     (default: mariadb-dump, else mysqldump)
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
: "${DB_USERNAME:?DB_USERNAME is required}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_SOCKET="${DB_SOCKET:-}"
DB_DATABASE="${DB_DATABASE:-zb_mill_rulescheck}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_ADMIN_USERNAME="${DB_ADMIN_USERNAME:-$DB_USERNAME}"
DB_ADMIN_PASSWORD="${DB_ADMIN_PASSWORD:-$DB_PASSWORD}"
MIGRATE_CMD="${MIGRATE_CMD:-php \"$ROOT/artisan\" migrate --force --no-interaction}"

CLIENT="${DB_CLIENT:-$(command -v mariadb || command -v mysql)}"
DUMP="${DB_DUMP:-$(command -v mariadb-dump || command -v mysqldump)}"
: "${CLIENT:?no mariadb/mysql client found}"
: "${DUMP:?no mariadb-dump/mysqldump found}"

PASS=0; FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

connection() { # connection <user> <password> ; sets CONN
    CONN=( "$CLIENT" --no-defaults -u "$1" )
    [ -n "$2" ] && CONN+=( "-p$2" )
    if [ -n "$DB_SOCKET" ]; then
        CONN+=( "--socket=$DB_SOCKET" )
    else
        CONN+=( "--host=$DB_HOST" "--port=$DB_PORT" "--protocol=TCP" )
    fi
}
connection "$DB_ADMIN_USERNAME" "$DB_ADMIN_PASSWORD"; ADMIN=( "${CONN[@]}" )
connection "$DB_USERNAME" "$DB_PASSWORD";             APP=( "${CONN[@]}" )
# The dump tool is a different binary but the same connection.
DUMPCONN=( "$DUMP" --no-defaults -u "$DB_USERNAME" )
[ -n "$DB_PASSWORD" ] && DUMPCONN+=( "-p$DB_PASSWORD" )
if [ -n "$DB_SOCKET" ]; then
    DUMPCONN+=( "--socket=$DB_SOCKET" )
else
    DUMPCONN+=( "--host=$DB_HOST" "--port=$DB_PORT" "--protocol=TCP" )
fi

app_q() { "${APP[@]}" -N -B "$DB_DATABASE" -e "$1"; }
one()   { app_q "$1" | tr -d '\r'; }

echo "Database rules check on $DB_DATABASE"

"${ADMIN[@]}" -e "DROP DATABASE IF EXISTS \`$DB_DATABASE\`" >/dev/null
"${ADMIN[@]}" -e "CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" >/dev/null

echo "  migrating with: $MIGRATE_CMD"
DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_SOCKET="$DB_SOCKET" DB_DATABASE="$DB_DATABASE" \
    DB_USERNAME="$DB_USERNAME" DB_PASSWORD="$DB_PASSWORD" bash -c "$MIGRATE_CMD"

# --- server version (MariaDB >= 10.2, or a MySQL of the same family)
VERSION_STR="$(one "SELECT VERSION()")"
case "$VERSION_STR" in
    *MariaDB*) MIN_VERSION=10.2 ;;
    *)         MIN_VERSION=8.0 ;;
esac
NUM_VERSION="$(grep -oE '^[0-9]+\.[0-9]+' <<<"$VERSION_STR" || echo 0.0)"
if awk "BEGIN { exit !($NUM_VERSION >= $MIN_VERSION) }"; then
    ok "server version $VERSION_STR is $MIN_VERSION or newer"
else
    bad "server version $VERSION_STR is older than $MIN_VERSION"
fi

# --- InnoDB only
NON_INNODB="$(one "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE' AND engine <> 'InnoDB'")"
if [ "$NON_INNODB" = 0 ]; then ok "every table is InnoDB"; else bad "$NON_INNODB table(s) are not InnoDB"; fi

# --- the protected tables exist
for t in journal_entries journal_lines journal_seals purchases; do
    if [ "$(one "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE' AND table_name='$t'")" != 0 ]; then
        ok "table $t exists"
    else
        bad "table $t is missing"
    fi
done

# --- the required triggers are present: table, event
while read -r table event; do
    [ -n "$table" ] || continue
    n="$(one "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$DB_DATABASE' AND event_object_table='$table' AND event_manipulation='$event'")"
    if [ "$n" != 0 ]; then ok "$table: $event trigger present"; else bad "$table: $event trigger missing"; fi
done <<'RULES'
journal_entries UPDATE
journal_entries DELETE
journal_lines UPDATE
journal_lines DELETE
journal_seals INSERT
purchases DELETE
RULES

# --- trigger bodies are the MariaDB rewrite: SIGNAL, never RAISE
TRIGGERS="$(one "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$DB_DATABASE'")"
if [ "$TRIGGERS" -gt 0 ]; then
    ok "the schema has $TRIGGERS protection triggers"
    NOT_SIGNAL="$(one "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$DB_DATABASE' AND action_statement NOT LIKE '%SIGNAL%'")"
    if [ "$NOT_SIGNAL" = 0 ]; then ok "every trigger body uses SIGNAL"; else bad "$NOT_SIGNAL trigger(s) do not use SIGNAL"; fi
    RAISE="$(one "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$DB_DATABASE' AND action_statement LIKE '%RAISE%'")"
    if [ "$RAISE" = 0 ]; then ok "no trigger still uses the PostgreSQL RAISE"; else bad "$RAISE trigger(s) still use RAISE"; fi
else
    bad "no protection triggers were created"
fi

# --- the seal trigger sums journal_lines
SEAL="$(one "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$DB_DATABASE' AND event_object_table='journal_seals' AND event_manipulation='INSERT' AND action_statement LIKE '%journal_lines%'")"
if [ "$SEAL" != 0 ]; then ok "the journal_seals INSERT trigger checks journal_lines"; else bad "the seal trigger does not read journal_lines"; fi

# --- the one-side CHECK on journal_lines (BR-LED-02)
CHECKS="$(one "SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema='$DB_DATABASE' AND table_name='journal_lines' AND constraint_type='CHECK'" 2>/dev/null || echo "?")"
case "$CHECKS" in
    ""|"?") echo "  note  CHECK constraints are not listed by this server: one-side rule not checked here" ;;
    0) bad "journal_lines has no CHECK constraint" ;;
    *) ok "journal_lines has a CHECK constraint" ;;
esac

# --- no PostgreSQL helper routines survive the rewrite
LEFTOVER="$(one "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='$DB_DATABASE' AND routine_name IN ('prevent_change','prevent_delete','check_journal_balance')")"
if [ "$LEFTOVER" = 0 ]; then ok "no PostgreSQL helper routines remain"; else bad "$LEFTOVER PostgreSQL helper routine(s) remain"; fi

# --- a dump keeps the triggers (the monthly restore depends on it)
DUMPFILE="$(mktemp)"
if "${DUMPCONN[@]}" --routines --triggers "$DB_DATABASE" > "$DUMPFILE" 2>/dev/null && grep -q "TRIGGER" "$DUMPFILE"; then
    ok "mariadb-dump keeps the triggers"
else
    bad "mariadb-dump did not keep the triggers"
fi
rm -f "$DUMPFILE"

"${ADMIN[@]}" -e "DROP DATABASE IF EXISTS \`$DB_DATABASE\`" >/dev/null 2>&1 || true

printf '\nDatabase rules check: %d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
