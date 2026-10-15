#!/usr/bin/env bash
# Run on YOUR computer (never on a server): creates the sodium key pair that protects the backups.
#   bash infra/hostinger/make-backup-key.sh
#
#   PUBLIC key  (base64) -> goes on the host in .env (ZB_BACKUP_PUBLIC_KEY), read by `zb:backup`.
#   SECRET key  (base64) -> NEVER on a server, never in git, never in chat.
#                           Two copies, kept by two different people (O-37):
#                           1. printed on paper, sealed envelope, Owner's safe/locker;
#                           2. in your password manager (as a secure note).
# Losing every copy of the secret key = losing every backup. Test a restore before go-live (BR-OPS-02).
#
# The app encrypts each backup with sodium_crypto_box_seal() to the PUBLIC key, so the host only ever
# holds the public half and can neither read a backup nor make one it can read (BR-SEC-08, ADR-0015).
set -euo pipefail
command -v php >/dev/null || { echo "PHP 8.3+ with the sodium extension is required."; exit 1; }
php -r 'extension_loaded("sodium") or fwrite(STDERR, "The sodium extension is missing.\n") or exit(1);'
OUT="${1:-zb-mill-backup-key-$(date +%Y%m%d).txt}"
[ -e "$OUT" ] && { echo "$OUT already exists; refusing to overwrite."; exit 1; }
(umask 077; : > "$OUT")
php -r '
    $kp  = sodium_crypto_box_keypair();
    $pub = sodium_crypto_box_publickey($kp);
    $sec = sodium_crypto_box_secretkey($kp);
    printf("ZB_BACKUP_PUBLIC_KEY=%s\n", base64_encode($pub));
    printf("ZB_BACKUP_SECRET_KEY=%s\n", base64_encode($sec));
' > "$OUT"
chmod 600 "$OUT"
echo "Wrote $OUT (mode 600)."
echo
echo "PUBLIC key  -> put it on the host in .env as ZB_BACKUP_PUBLIC_KEY (it is not a secret)."
echo "SECRET key  -> print it and seal it (Owner) + save it in your password manager."
echo "               Then destroy the file on your computer:  shred -u $OUT"
