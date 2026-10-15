#!/usr/bin/env bash
# Proves the MariaDB database rules of spec 9.7 / ADR-0016 with a real server, using the fixture
# schema that stands in for the migrations:
#   - the journal is append-only: UPDATE and DELETE on journal_entries / journal_lines are refused;
#   - the seal trigger refuses an entry with fewer than two lines or debits that do not equal
#     credits (BR-LED-11), the MariaDB replacement for the deferred constraint trigger;
#   - DELETE on a master/transaction table (purchases) is refused (BR-GEN-03);
#   - the one-side CHECK on journal_lines is enforced (BR-LED-02);
#   - mariadb-dump keeps the triggers, and a restored copy still refuses edits.
# There is ONE all-rights database user on the host (BR-SEC-12), so this is about the triggers,
# not about database privileges. Runs on a throwaway machine or CI runner; root is not required.
#   bash infra/tests/test-mariadb.sh
source "$(dirname "$0")/lib.sh"
[ -n "$(db_client_bin)" ] || { echo "no mariadb/mysql client found"; exit 2; }

WORK="$(mktemp -d /tmp/zb-mytest.XXXXXX)"; chmod 0755 "$WORK"
cleanup() { db_teardown; rm -rf "$WORK"; }
trap cleanup EXIT

DB=zb_mill_fixture
RDB=zb_mill_fixture_restore
DUMP="$WORK/zb_mill_fixture.sql"

# Small wrappers so expect_eq/expect_ok can call them by name.
bad_trigger_bodies()  { db_q "$DB" "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$DB' AND (action_statement NOT LIKE '%SIGNAL%' OR action_statement LIKE '%RAISE%')"; }
non_innodb_tables()   { db_q "$DB" "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB' AND engine <> 'InnoDB'"; }
seal_count()          { db_q "$DB" "SELECT COUNT(*) FROM journal_seals"; }
do_dump()             { db_dump "$DB" > "$DUMP"; }
restored_counts()     { db_q "$RDB" "SELECT CONCAT((SELECT COUNT(*) FROM purchases), ',', (SELECT COUNT(*) FROM journal_lines), ',', (SELECT COUNT(*) FROM journal_seals))"; }

db_setup "$WORK"
db_client -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
db_client -e "DROP DATABASE IF EXISTS \`$RDB\`; CREATE DATABASE \`$RDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

echo "MariaDB database rules ($(db_client -N -B -e 'SELECT VERSION()'))"

expect_ok "the fixture schema loads" db_file "$DB" "$INFRA_DIR/tests/fixtures/schema.sql"
expect_eq "every table is InnoDB" "0" non_innodb_tables
expect_eq "every trigger body uses SIGNAL (a MariaDB rewrite, not the PostgreSQL RAISE)" "0" bad_trigger_bodies

# --- the seal trigger: a balanced entry is accepted, an incomplete one is refused
expect_ok "a balanced journal with its seal row is accepted" db_x "$DB" "
    INSERT INTO journal_entries (company_id, entry_no, posting_date) VALUES (1, 'JV-000001', '2026-01-01');
    INSERT INTO journal_lines (company_id, journal_entry_id, line_no, debit, credit) VALUES
        (1, 1, 1, 40000, 0), (1, 1, 2, 0, 40000);
    INSERT INTO journal_seals (journal_entry_id) VALUES (1);"
expect_eq "the seal row was written" "1" seal_count
expect_fail_with "an entry with fewer than two lines is refused by the seal trigger" "fewer than 2 lines" \
    db_x "$DB" "
    INSERT INTO journal_entries (company_id, entry_no, posting_date) VALUES (1, 'JV-000002', '2026-01-01');
    INSERT INTO journal_lines (company_id, journal_entry_id, line_no, debit, credit) VALUES (1, 2, 1, 100, 0);
    INSERT INTO journal_seals (journal_entry_id) VALUES (2);"
expect_fail_with "an entry whose debits do not equal its credits is refused by the seal trigger" "not balanced" \
    db_x "$DB" "
    INSERT INTO journal_entries (company_id, entry_no, posting_date) VALUES (1, 'JV-000003', '2026-01-01');
    INSERT INTO journal_lines (company_id, journal_entry_id, line_no, debit, credit) VALUES
        (1, 3, 1, 100, 0), (1, 3, 2, 0, 90);
    INSERT INTO journal_seals (journal_entry_id) VALUES (3);"
expect_eq "no seal was written for the refused entries" "1" seal_count
expect_fail "a second seal for the same entry is refused" \
    db_x "$DB" "INSERT INTO journal_seals (journal_entry_id) VALUES (1)"

# --- the append-only journal
expect_fail_with "a journal line cannot be updated" "cannot be updated or deleted" \
    db_x "$DB" "UPDATE journal_lines SET debit = 1 WHERE id = 1"
expect_fail_with "a journal line cannot be deleted" "cannot be updated or deleted" \
    db_x "$DB" "DELETE FROM journal_lines WHERE id = 1"
expect_fail_with "a journal entry cannot be updated" "cannot be updated or deleted" \
    db_x "$DB" "UPDATE journal_entries SET entry_no = 'X' WHERE id = 1"
expect_fail_with "a journal entry cannot be deleted" "cannot be updated or deleted" \
    db_x "$DB" "DELETE FROM journal_entries WHERE id = 1"
expect_fail_with "a seal row cannot be updated" "cannot be updated or deleted" \
    db_x "$DB" "UPDATE journal_seals SET source = 'x' WHERE id = 1"
expect_fail_with "a seal row cannot be deleted" "cannot be updated or deleted" \
    db_x "$DB" "DELETE FROM journal_seals WHERE id = 1"

# --- master/transaction tables refuse DELETE, and the one-side CHECK is enforced
expect_ok "a purchase can be inserted" \
    db_x "$DB" "INSERT INTO purchases (company_id, voucher_no, kg, entry_date) VALUES (1, 'PUR-000001', 800, '2026-01-01')"
expect_fail_with "a purchase cannot be deleted" "cannot be deleted" \
    db_x "$DB" "DELETE FROM purchases WHERE voucher_no = 'PUR-000001'"
expect_fail_with "a line with neither side set is refused (CHECK)" "constraint" \
    db_x "$DB" "INSERT INTO journal_lines (company_id, journal_entry_id, line_no, debit, credit) VALUES (1, 1, 9, 0, 0)"

# --- a dump keeps the triggers, and a restored copy still refuses edits
expect_ok "mariadb-dump runs" do_dump
expect_ok "the dump contains the triggers" grep -q "TRIGGER" "$DUMP"
expect_ok "the dump restores into a new database" db_file "$RDB" "$DUMP"
expect_eq "restored data is complete (1 purchase, 5 journal lines, 1 seal)" "1,5,1" restored_counts
expect_fail_with "the restored database still refuses a journal edit" "cannot be updated or deleted" \
    db_x "$RDB" "UPDATE journal_lines SET debit = 1 WHERE id = 1"
expect_fail_with "the restored database still refuses an unbalanced seal" "not balanced" \
    db_x "$RDB" "
    INSERT INTO journal_entries (company_id, entry_no, posting_date) VALUES (1, 'JV-000010', '2026-01-02');
    SET @e := LAST_INSERT_ID();
    INSERT INTO journal_lines (company_id, journal_entry_id, line_no, debit, credit) VALUES
        (1, @e, 1, 5, 0), (1, @e, 2, 0, 4);
    INSERT INTO journal_seals (journal_entry_id) VALUES (@e);"

summary "MariaDB database rules"
