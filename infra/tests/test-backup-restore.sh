#!/usr/bin/env bash
# End-to-end test of zb-backup-db, zb-backup-files, zb-restore and zb-backup-verify-offsite
# with a real PostgreSQL, real age encryption and rclone (local folder standing in for the
# off-site storage). Run as root on a throwaway machine:  sudo bash infra/tests/test-backup-restore.sh
source "$(dirname "$0")/lib.sh"
require_root
for t in age age-keygen rclone pg_dump pg_restore tar; do command -v "$t" >/dev/null || { echo "missing $t"; exit 2; }; done

WORK="$(mktemp -d /tmp/zb-bktest.XXXXXX)"; chmod 0755 "$WORK"
KEYCOPY=""
cleanup() { pg_test_cluster_stop "$WORK"; rm -rf "$WORK"; [ -n "$KEYCOPY" ] && rm -f "$KEYCOPY"; }
trap cleanup EXIT

for u in zbmill zbdeploy zbbackup; do ensure_system_user "$u"; done
usermod -aG zbmill zbbackup
usermod -aG zbmill zbdeploy

mkdir -p "$WORK/bin" "$WORK/sql"
cp "$INFRA_DIR"/bin/* "$WORK/bin/"; chmod 0755 "$WORK"/bin/zb-*; chmod 0644 "$WORK/bin/zb-lib.sh"
cp "$INFRA_DIR"/files/postgres/*.sql "$INFRA_DIR/tests/fixtures/schema.sql" "$WORK/sql/"; chmod 0644 "$WORK"/sql/*
export ZB_SQL_DIR="$WORK/sql"

echo "Backup and restore"
pg_test_cluster_start "$WORK"
psql_file_as postgres postgres postgres "$WORK/sql/10-roles.sql" -v db_name=zb_mill >/dev/null
psql_file_as postgres postgres zb_mill "$WORK/sql/20-schema.sql" >/dev/null
psql_file_as zbdeploy zb_migrator zb_mill "$WORK/sql/schema.sql" >/dev/null
psql_file_as zbdeploy zb_migrator zb_mill "$WORK/sql/30-app-grants.sql" >/dev/null
psql_as zbmill zb_app zb_mill "insert into purchases (company_id, voucher_no, kg, rate, amount) values (1, 'PUR-000001', 800, 50, 40000), (1, 'PUR-000002', 600, 48, 28800);
  begin; insert into journal_entries (entry_no, posting_date) values ('JV-000001', '2026-01-01');
  insert into journal_lines (journal_entry_id, line_no, debit, credit) values (currval('journal_entries_id_seq'), 1, 40000, 0), (currval('journal_entries_id_seq'), 2, 0, 40000); commit;
  insert into sessions values ('secret-session', 1, 'payload', 1);" >/dev/null

# The "offline" key pair. On a real server only the PUBLIC key exists.
age-keygen -o "$WORK/offline-key.txt" 2>/dev/null
age-keygen -y "$WORK/offline-key.txt" > "$WORK/recipients.txt"
age-keygen -o "$WORK/wrong-key.txt" 2>/dev/null
chmod 0644 "$WORK/recipients.txt"

# Server layout stand-in
install -d -o zbbackup -g zbbackup -m 0700 "$WORK/backupwork"
install -d -o zbbackup -g zbmill -m 0750 "$WORK/status"
install -d -o zbbackup -g zbbackup -m 0700 "$WORK/remote"
install -d -o zbmill -g zbmill -m 2770 "$WORK/shared" "$WORK/shared/storage" "$WORK/shared/storage/app" "$WORK/shared/storage/app/private"
install -d -o root -g zbbackup -m 0750 "$WORK/secrets"
printf '[offsite]\ntype = local\n' > "$WORK/secrets/rclone.conf"; chgrp zbbackup "$WORK/secrets/rclone.conf"; chmod 0640 "$WORK/secrets/rclone.conf"
UP="$WORK/shared/storage/app/private"

cat > "$WORK/zb-mill.conf" <<EOF
ENV_NAME=test
DB_NAME=zb_mill
APP_BASE=$WORK/app
APP_USER=zbmill
DEPLOY_USER=zbdeploy
BACKUP_USER=zbbackup
BACKUP_WORK_DIR=$WORK/backupwork
BACKUP_RECIPIENTS_FILE=$WORK/recipients.txt
RCLONE_CONFIG_FILE=$WORK/secrets/rclone.conf
RCLONE_REMOTE=offsite:$WORK/remote
STATUS_DIR=$WORK/status
UPLOADS_DIR=$UP
HC_DIR=$WORK/secrets/hc
BACKUP_DAILY_HOUR_UTC=99
EOF
chmod 0644 "$WORK/zb-mill.conf"
export ZB_CONF="$WORK/zb-mill.conf"

as_backup() { runuser -u zbbackup -- env ZB_CONF="$ZB_CONF" PGHOST="$PGHOST" PGPORT="$PGPORT" "$@"; }

# ---------- database backup
expect_ok "zb-backup-db succeeds as zbbackup" as_backup "$WORK/bin/zb-backup-db"
HOURLY="$(find "$WORK/remote/db/hourly" -name 'test-db-*.dump.age' | head -1)"
expect_ok "hourly copy uploaded" test -s "$HOURLY"
expect_eq "first run of the month also made the monthly copy" "1" bash -c "find '$WORK/remote/db/monthly' -name '*.dump.age' | wc -l"
expect_eq "no daily copy before the daily hour" "0" bash -c "find '$WORK/remote/db' -path '*daily*' -name '*.age' 2>/dev/null | wc -l"
expect_eq "backup file starts with the age header" "age-encryption.org/v1" head -c 21 "$HOURLY"
expect_fail "backup does not contain readable table data" grep -q "PUR-000001" "$HOURLY"
expect_eq "nothing left in the server work folder" "" bash -c "find '$WORK/backupwork' -name '*.age*' -o -name '*.dump'"
expect_ok "status file says success" grep -q '"result":"success"' "$WORK/status/backup-db.json"
expect_eq "status file is readable by the app group only" "640 zbbackup zbmill" stat -c '%a %U %G' "$WORK/status/backup-db.json"
expect_fail "the wrong key cannot decrypt" age --decrypt --identity "$WORK/wrong-key.txt" -o /dev/null "$HOURLY"
expect_fail "zbbackup cannot read the private key location (it is not on the server)" test -e /etc/zb-mill/backup-private-key.txt

sed -i 's/^BACKUP_DAILY_HOUR_UTC=.*/BACKUP_DAILY_HOUR_UTC=0/' "$ZB_CONF"
sleep 1
as_backup "$WORK/bin/zb-backup-db" 2>/dev/null; sleep 1
as_backup "$WORK/bin/zb-backup-db" 2>/dev/null
expect_eq "daily copy made once per day, not on every run" "1" bash -c "find '$WORK/remote/db/daily' -name '*.dump.age' | wc -l"
expect_eq "monthly copy still made only once" "1" bash -c "find '$WORK/remote/db/monthly' -name '*.dump.age' | wc -l"

cp "$WORK/status/backup-db-last-success.json" "$WORK/last-ok.json"
sed -i 's/^DB_NAME=.*/DB_NAME=does_not_exist/' "$ZB_CONF"
expect_fail "zb-backup-db fails when the database cannot be read" as_backup "$WORK/bin/zb-backup-db"
expect_ok "failure is written to the status file" grep -q '"result":"failure"' "$WORK/status/backup-db.json"
expect_ok "last-success file is untouched by the failure" cmp -s "$WORK/last-ok.json" "$WORK/status/backup-db-last-success.json"
expect_eq "failed run leaves no partial file" "" bash -c "find '$WORK/backupwork' -name '*.partial'"
sed -i 's/^DB_NAME=.*/DB_NAME=zb_mill/' "$ZB_CONF"

# ---------- uploads backup (incremental chain)
install -o zbmill -g zbmill -m 0660 /dev/null "$UP/slip-1.jpg"; echo "photo one" > "$UP/slip-1.jpg"
install -d -o zbmill -g zbmill -m 2770 "$UP/attachments"
install -o zbmill -g zbmill -m 0660 /dev/null "$UP/attachments/slip-2.pdf"; echo "pdf two" > "$UP/attachments/slip-2.pdf"
expect_ok "zb-backup-files first run (full)" as_backup "$WORK/bin/zb-backup-files"
MONTH="$(date -u +%Y-%m)"
expect_eq "full archive created" "1" bash -c "find '$WORK/remote/files/$MONTH' -name '*-full.tar.age' | wc -l"
sleep 1
install -o zbmill -g zbmill -m 0660 /dev/null "$UP/attachments/slip-3.jpg"; echo "photo three" > "$UP/attachments/slip-3.jpg"
expect_ok "zb-backup-files second run (incremental)" as_backup "$WORK/bin/zb-backup-files"
expect_eq "incremental archive created" "1" bash -c "find '$WORK/remote/files/$MONTH' -name '*-incr.tar.age' | wc -l"

# A failed upload must not advance the chain: the new file must be in the next archive.
sleep 1
install -o zbmill -g zbmill -m 0660 /dev/null "$UP/attachments/slip-4.jpg"; echo "photo four" > "$UP/attachments/slip-4.jpg"
sed -i "s|^RCLONE_REMOTE=.*|RCLONE_REMOTE=offsite:/proc/not-writable|" "$ZB_CONF"
expect_fail "zb-backup-files fails when the storage is unreachable" as_backup "$WORK/bin/zb-backup-files"
sed -i "s|^RCLONE_REMOTE=.*|RCLONE_REMOTE=offsite:$WORK/remote|" "$ZB_CONF"
sleep 1
expect_ok "zb-backup-files runs again after the failure" as_backup "$WORK/bin/zb-backup-files"
LAST_INCR="$(find "$WORK/remote/files/$MONTH" -name '*-incr.tar.age' | sort | tail -1)"
expect_ok "the file from the failed run is in the next archive" bash -c \
    "age --decrypt --identity '$WORK/offline-key.txt' '$LAST_INCR' | tar -tf - | grep -q 'slip-4.jpg'"

# ---------- restore (staging monthly test)
KEYCOPY="/dev/shm/zb-test-key-$$.txt"; cp "$WORK/offline-key.txt" "$KEYCOPY"; chmod 0600 "$KEYCOPY"
LATEST="$(find "$WORK/remote/db/hourly" -name '*.dump.age' | sort | tail -1)"
OUT="$WORK/restore.log"
if "$WORK/bin/zb-restore" --identity "$KEYCOPY" --db "$LATEST" --files-dir "$WORK/remote/files/$MONTH" > "$OUT" 2>&1; then
    pass "zb-restore (test restore) succeeds"
else
    cat "$OUT"; fail "zb-restore (test restore) succeeds"
fi
expect_ok "restore reports balanced journals" grep -q "every journal balances" "$OUT"
expect_ok "restore reports 4 upload files" grep -q "uploads restored: 4 files" "$OUT"
expect_ok "restore reports triggers present" grep -qE "restored: [0-9]+ tables, [1-9][0-9]* triggers" "$OUT"
expect_fail "temporary database was dropped afterwards" psql_as postgres postgres zb_mill_restorecheck "select 1"
expect_fail "key file was removed from /dev/shm" test -e "$KEYCOPY"

cp "$WORK/wrong-key.txt" "$KEYCOPY"; chmod 0600 "$KEYCOPY"
expect_fail "zb-restore refuses with the wrong key" "$WORK/bin/zb-restore" --identity "$KEYCOPY" --db "$LATEST"

cp "$WORK/offline-key.txt" "$KEYCOPY"; chmod 0600 "$KEYCOPY"
expect_fail_with "zb-restore refuses to overwrite the configured database without the flag" "into-empty-production" \
    "$WORK/bin/zb-restore" --identity "$KEYCOPY" --keep-key --db "$LATEST" --target zb_mill

# ---------- disaster recovery into an empty production database on a "new server"
psql_file_as postgres postgres postgres "$WORK/sql/10-roles.sql" -v db_name=zb_mill_dr >/dev/null
psql_file_as postgres postgres zb_mill_dr "$WORK/sql/20-schema.sql" >/dev/null
sed -i 's/^DB_NAME=.*/DB_NAME=zb_mill_dr/' "$ZB_CONF"
NEWUP="$WORK/newserver/storage/app/private"; mkdir -p "$NEWUP"
sed -i "s|^UPLOADS_DIR=.*|UPLOADS_DIR=$NEWUP|" "$ZB_CONF"
if "$WORK/bin/zb-restore" --identity "$KEYCOPY" --keep-key --db "$LATEST" --files-dir "$WORK/remote/files/$MONTH" --into-empty-production > "$OUT" 2>&1; then
    pass "zb-restore --into-empty-production succeeds"
else
    cat "$OUT"; fail "zb-restore --into-empty-production succeeds"
fi
expect_eq "recovered database has both purchases" "2" q_as zbmill zb_app zb_mill_dr "select count(*) from purchases"
expect_eq "recovered database has no session rows (left out of backups)" "0" q_as zbmill zb_app zb_mill_dr "select count(*) from sessions"
expect_fail_with "recovered database still blocks journal edits" "permission denied" psql_as zbmill zb_app zb_mill_dr "update journal_lines set debit = 1"
expect_eq "recovered uploads are owned by the app user" "zbmill" stat -c '%U' "$NEWUP/attachments/slip-4.jpg"
expect_fail_with "a second recovery into the now-full database is refused" "not empty" \
    "$WORK/bin/zb-restore" --identity "$KEYCOPY" --keep-key --db "$LATEST" --into-empty-production
rm -f "$KEYCOPY"
sed -i 's/^DB_NAME=.*/DB_NAME=zb_mill/' "$ZB_CONF"

# ---------- off-site delete protection check
cat > "$WORK/bin/rclone-nodelete" <<'EOF'
#!/usr/bin/env bash
# Stand-in for a storage key without the delete capability.
for a in "$@"; do [ "$a" = deletefile ] || [ "$a" = delete ] || [ "$a" = purge ] && { echo "403 Forbidden" >&2; exit 1; }; done
exec rclone "$@"
EOF
chmod 0755 "$WORK/bin/rclone-nodelete"
expect_ok "verify-offsite PASSES when the key cannot delete" env RCLONE_BIN="$WORK/bin/rclone-nodelete" "$WORK/bin/zb-backup-verify-offsite"
expect_fail_with "verify-offsite FAILS when the key can delete" "CAN DELETE" "$WORK/bin/zb-backup-verify-offsite"

summary "Backup and restore"
