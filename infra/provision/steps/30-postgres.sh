#!/usr/bin/env bash
# Step 30: PostgreSQL settings, peer authentication, roles, database, schema rights (BR-SEC-12).
# Sourced by setup.sh.

PGETC="/etc/postgresql/$PG_VERSION/main"
[ -d "$PGETC" ] || die "PostgreSQL $PG_VERSION cluster not found in $PGETC"

put "$FILES/postgres/zb-mill.conf" "$PGETC/conf.d/zb-mill.conf" 0644 postgres:postgres
put "$FILES/postgres/pg_hba.conf"  "$PGETC/pg_hba.conf" 0640 postgres:postgres
put "$FILES/postgres/pg_ident.conf" "$PGETC/pg_ident.conf" 0640 postgres:postgres
systemctl restart "postgresql@$PG_VERSION-main"

install -d -m 0755 /usr/local/lib/zb-mill/postgres
for f in 10-roles.sql 20-schema.sql 30-app-grants.sql; do
    put "$FILES/postgres/$f" "/usr/local/lib/zb-mill/postgres/$f" 0644
done

runuser -u postgres -- psql -X -q -v ON_ERROR_STOP=1 -v db_name="$DB_NAME" -d postgres -f /usr/local/lib/zb-mill/postgres/10-roles.sql
runuser -u postgres -- psql -X -q -v ON_ERROR_STOP=1 -d "$DB_NAME" -f /usr/local/lib/zb-mill/postgres/20-schema.sql
info "database $DB_NAME owned by zb_migrator; roles zb_app and zb_backup ready; no passwords"

if runuser -u "$APP_USER" -- psql -X -q -U zb_app -d "$DB_NAME" -c 'select 1' >/dev/null 2>&1; then
    info "check: $APP_USER can log in as zb_app"
else
    die "$APP_USER cannot log in as zb_app (check pg_ident.conf)"
fi
if runuser -u "$APP_USER" -- psql -X -q -U zb_migrator -d "$DB_NAME" -c 'select 1' >/dev/null 2>&1; then
    die "$APP_USER CAN log in as zb_migrator: peer mapping is wrong"
fi
