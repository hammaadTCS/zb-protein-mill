#!/usr/bin/env bash
# Backup and restore are application commands now (zb:backup / zb:restore, ADR-0015), not the
# retired shell scripts: there is no root, no rclone and no age on shared hosting. This test is a
# STRUCTURAL check of that retirement -- it proves the old scripts are gone from the live paths,
# kept under infra/history/ and replaced by the app command. Their behaviour (encryption, upload,
# restore, delete-protection) is covered by the application tests, which need a real database.
source "$(dirname "$0")/lib.sh"

echo "Backup and restore (retired VPS scripts; behaviour is app-side now)"

# ---------- the retired scripts survive, but only under infra/history/
for f in zb-backup-db zb-backup-files zb-backup-verify-offsite zb-restore; do
    expect_ok "retired $f is kept under infra/history/bin/" test -f "$INFRA_DIR/history/bin/$f"
done
expect_ok "infra/history/README.md explains what replaced them" test -f "$INFRA_DIR/history/README.md"
expect_ok "the history README names the app command zb:backup" grep -q 'zb:backup' "$INFRA_DIR/history/README.md"
expect_ok "the history README names the app command zb:restore" grep -q 'zb:restore' "$INFRA_DIR/history/README.md"
expect_ok "the history README names ADR-0015" grep -q 'ADR-0015' "$INFRA_DIR/history/README.md"

# ---------- no live copy is left behind
expect_fail "the old infra/bin/ folder is gone" test -e "$INFRA_DIR/bin"
expect_eq "infra/hostinger/ ships no backup or restore shell script" "" \
    bash -c "find '$INFRA_DIR/hostinger' -maxdepth 1 -type f \\( -name '*backup*' -o -name '*restore*' \\)"
expect_eq "infra/hostinger/ ships no age or rclone helper" "" \
    bash -c "find '$INFRA_DIR/hostinger' -maxdepth 1 -type f \\( -name '*age*' -o -name '*rclone*' \\)"

# ---------- the live scripts do not carry the retired tools
for tool in rclone pg_dump pg_restore age-keygen postgresql psql; do
    expect_eq "no $tool in the live hostinger scripts" "" bash -c "grep -rn --exclude=README.md -e '$tool' '$INFRA_DIR/hostinger' || true"
done
expect_eq "no systemd or service reload in the live hostinger scripts" "" \
    bash -c "grep -rn --exclude=README.md -E 'systemctl|/etc/systemd|service nginx|service php' '$INFRA_DIR/hostinger' || true"

# ---------- deploy takes the backup through the app, not a shell script
expect_ok "zb-deploy-release takes a backup with the app command" \
    grep -q 'zb:backup' "$INFRA_DIR/hostinger/zb-deploy-release"
expect_ok "zb-deploy-release names the backup step in its log" \
    grep -q 'backing up the database first' "$INFRA_DIR/hostinger/zb-deploy-release"

echo "  note  this file is a STRUCTURAL check only: the backup's real behaviour (dump, encryption,"
echo "        upload, restore and delete protection) is the application's, proven by tests/Feature."

summary "Backup and restore"
