#!/usr/bin/env bash
# Step 70: off-site encrypted backups and monitoring (BR-OPS-01 to BR-OPS-07).
# Sourced by setup.sh. Before this step (docs/operations/server-setup.md, steps 6 and 7):
#   /etc/zb-mill/backup-recipients.txt     the age PUBLIC key(s), one per line (age1...)
#   /etc/zb-mill/secrets/rclone.conf       storage key that can upload but NOT delete
#   /etc/zb-mill/secrets/healthchecks/{backup-db,backup-files,health}.url   monitor ping URLs

R=/etc/zb-mill/backup-recipients.txt
check_recipients_file "$R"
chown root:root "$R"; chmod 0644 "$R"

C=/etc/zb-mill/secrets/rclone.conf
[ -s "$C" ] || die "create $C with the upload-only storage key first (server-setup.md step 7)"
chown root:"$BACKUP_USER" "$C"; chmod 0640 "$C"

for n in backup-db backup-files health; do
    f="/etc/zb-mill/secrets/healthchecks/$n.url"
    if [ -s "$f" ]; then chown root:"$BACKUP_USER" "$f"; chmod 0640 "$f"; else warn "no monitor URL in $f (O-33): failures will not alert anyone"; fi
done

systemctl enable --now zb-mill-backup-db.timer zb-mill-backup-files.timer >/dev/null
info "running the first database backup now"
systemctl start zb-mill-backup-db.service || die "first backup failed: journalctl -u zb-mill-backup-db"
info "checking that this server cannot delete backups"
/usr/local/sbin/zb-backup-verify-offsite || die "off-site storage check failed (see above)"
