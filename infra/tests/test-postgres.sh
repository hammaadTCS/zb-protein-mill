#!/usr/bin/env bash
# Proves BR-SEC-12 and spec 9.7 with a real PostgreSQL server:
#   - peer authentication maps each OS user to exactly one database role;
#   - the web role cannot change structure, disable triggers, delete business rows or edit journals;
#   - the backup role can dump but not write;
#   - triggers and the deferred balance check survive pg_dump / pg_restore.
# Run as root on a throwaway machine:  sudo bash infra/tests/test-postgres.sh
source "$(dirname "$0")/lib.sh"
require_root

WORK="$(mktemp -d /tmp/zb-pgtest.XXXXXX)"
chmod 0755 "$WORK"
cleanup() { pg_test_cluster_stop "$WORK"; rm -rf "$WORK"; }
trap cleanup EXIT

for u in zbmill zbdeploy zbbackup; do ensure_system_user "$u"; done

cp "$INFRA_DIR"/files/postgres/*.sql "$INFRA_DIR"/tests/fixtures/schema.sql "$WORK"/
chmod 0644 "$WORK"/*.sql

echo "PostgreSQL roles and grants ($(basename "$(dirname "$(pg_bin_dir)")"))"
pg_test_cluster_start "$WORK"

DB=zb_mill
psql_file_as postgres postgres postgres "$WORK/10-roles.sql" -v db_name="$DB" >/dev/null
psql_file_as postgres postgres postgres "$WORK/10-roles.sql" -v db_name="$DB" >/dev/null
pass "10-roles.sql runs twice without error"
psql_file_as postgres postgres "$DB" "$WORK/20-schema.sql" >/dev/null
pass "20-schema.sql runs"

psql_file_as zbdeploy zb_migrator "$DB" "$WORK/schema.sql" >/dev/null
pass "migrator creates tables, triggers and functions"
psql_file_as zbdeploy zb_migrator "$DB" "$WORK/30-app-grants.sql" >/dev/null
psql_file_as zbdeploy zb_migrator "$DB" "$WORK/30-app-grants.sql" >/dev/null
pass "30-app-grants.sql runs twice as migrator"

# --- peer authentication mapping
expect_fail "OS user zbmill cannot log in as zb_migrator" psql_as zbmill zb_migrator "$DB" "select 1"
expect_fail "OS user zbmill cannot log in as postgres" psql_as zbmill postgres "$DB" "select 1"
expect_fail "OS user zbbackup cannot log in as zb_app" psql_as zbbackup zb_app "$DB" "select 1"
expect_fail "OS user zbdeploy cannot log in as zb_app" psql_as zbdeploy zb_app "$DB" "select 1"
expect_ok   "OS user zbmill logs in as zb_app" psql_as zbmill zb_app "$DB" "select 1"
expect_fail "no TCP listener (listen_addresses is empty)" \
    runuser -u zbmill -- psql -X -h 127.0.0.1 -p 5499 -U zb_app -d "$DB" -c "select 1"

app() { psql_as zbmill zb_app "$DB" "$1"; }

# --- what the web application may do
expect_ok "app inserts a purchase" app "insert into purchases (company_id, voucher_no, kg, rate, amount) values (1, 'PUR-000001', 800, 50, 40000)"
expect_ok "app updates a purchase" app "update purchases set rate = 49 where voucher_no = 'PUR-000001'"
expect_ok "app inserts a balanced journal" app "begin; insert into journal_entries (entry_no, posting_date) values ('JV-000001', '2026-01-01'); insert into journal_lines (journal_entry_id, line_no, debit, credit) values (currval('journal_entries_id_seq'), 1, 40000, 0), (currval('journal_entries_id_seq'), 2, 0, 40000); commit;"
expect_ok "app writes and deletes a session row" app "insert into sessions values ('s1', 1, 'x', 1); delete from sessions where id = 's1';"
expect_ok "app reads the migrations table" app "select count(*) from migrations"

# --- what the web application may NOT do
expect_fail_with "app cannot delete a purchase (privilege)" "permission denied" app "delete from purchases"
expect_fail_with "app cannot truncate" "permission denied" app "truncate purchases"
expect_fail_with "app cannot update a journal line" "permission denied" app "update journal_lines set debit = 1"
expect_fail_with "app cannot delete a journal entry" "permission denied" app "delete from journal_entries"
expect_fail_with "app cannot alter a table" "must be owner" app "alter table purchases add column x int"
expect_fail_with "app cannot disable a trigger" "must be owner" app "alter table journal_lines disable trigger journal_balance_check"
expect_fail_with "app cannot drop a trigger" "must be owner" app "drop trigger purchases_no_delete on purchases"
expect_fail_with "app cannot create a table" "permission denied" app "create table x (id int)"
expect_fail_with "app cannot switch off triggers for its session" "permission denied" app "set session_replication_role = replica"
expect_fail_with "app cannot write the migrations table" "permission denied" app "insert into migrations (migration, batch) values ('x', 9)"
expect_fail_with "unbalanced journal is refused at commit" "not balanced" app "begin; insert into journal_entries (entry_no, posting_date) values ('JV-000002', '2026-01-01'); insert into journal_lines (journal_entry_id, line_no, debit, credit) values (currval('journal_entries_id_seq'), 1, 100, 0); commit;"

# --- even the owner role is stopped by triggers
expect_fail_with "migrator cannot update a journal line (trigger)" "not allowed" psql_as zbdeploy zb_migrator "$DB" "update journal_lines set debit = 1"
expect_fail_with "migrator cannot delete a purchase (trigger)" "not allowed" psql_as zbdeploy zb_migrator "$DB" "delete from purchases"

# --- backup role
expect_ok "backup role reads every table" psql_as zbbackup zb_backup "$DB" "select count(*) from journal_lines; select count(*) from purchases;"
expect_fail_with "backup role cannot write" "permission denied" psql_as zbbackup zb_backup "$DB" "insert into cache values ('k', 'v', 1)"
DUMP="$WORK/test.dump"
touch "$DUMP"; chown zbbackup "$DUMP"
expect_ok "pg_dump works as zbbackup" runuser -u zbbackup -- env PGHOST="$PGHOST" PGPORT="$PGPORT" \
    pg_dump -Fc -U zb_backup -d "$DB" --exclude-table-data=sessions --exclude-table-data=cache -f "$DUMP"

# --- restore into a new database and prove the protections came back
RDB=zb_mill_restorecheck
psql_as postgres postgres postgres "create database $RDB owner zb_migrator template template0" >/dev/null
psql_as postgres postgres postgres "revoke all on database $RDB from public; grant connect on database $RDB to zb_migrator, zb_app, zb_backup" >/dev/null
psql_file_as postgres postgres "$RDB" "$WORK/20-schema.sql" >/dev/null
chmod 0644 "$DUMP"
expect_ok "pg_restore as migrator (no owner, no privileges)" runuser -u zbdeploy -- env PGHOST="$PGHOST" PGPORT="$PGPORT" \
    pg_restore --no-owner --no-privileges --exit-on-error --single-transaction -U zb_migrator -d "$RDB" "$DUMP"
psql_file_as zbdeploy zb_migrator "$RDB" "$WORK/30-app-grants.sql" >/dev/null
rapp() { psql_as zbmill zb_app "$RDB" "$1"; }
expect_eq "restored data is complete (1 purchase, 2 journal lines)" "1,2" \
    q_as zbmill zb_app "$RDB" "select (select count(*) from purchases) || ',' || (select count(*) from journal_lines)"
expect_fail_with "restored: journal update still blocked" "permission denied" rapp "update journal_lines set debit = 1"
expect_fail_with "restored: owner journal update blocked by trigger" "not allowed" psql_as zbdeploy zb_migrator "$RDB" "update journal_lines set debit = 1"
expect_fail_with "restored: unbalanced journal still refused" "not balanced" rapp "begin; insert into journal_entries (entry_no, posting_date) values ('JV-000009', '2026-01-02'); insert into journal_lines (journal_entry_id, line_no, debit, credit) values (currval('journal_entries_id_seq'), 1, 5, 0); commit;"
expect_eq "restored: sessions table exists but is empty" "0" q_as zbmill zb_app "$RDB" "select count(*) from sessions"

# --- PHP connects through the socket with peer auth (how Laravel connects: DB_HOST=<socket dir>)
if command -v php >/dev/null && php -m | grep -qi pdo_pgsql; then
    expect_ok "PHP PDO connects as zb_app through the socket, no password" runuser -u zbmill -- php -r \
        "\$p = new PDO('pgsql:host=$PGSOCK;port=5499;dbname=$DB', 'zb_app', ''); exit(\$p->query('select 1')->fetchColumn() == 1 ? 0 : 1);"
    expect_fail "PHP PDO as zbmill cannot open zb_migrator" runuser -u zbmill -- php -r \
        "new PDO('pgsql:host=$PGSOCK;port=5499;dbname=$DB', 'zb_migrator', '');"
else
    echo "  skip  PHP pdo_pgsql not installed"
fi

summary "PostgreSQL"
