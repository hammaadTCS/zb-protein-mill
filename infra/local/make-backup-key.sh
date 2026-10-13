#!/usr/bin/env bash
# Run on YOUR computer (never on a server): creates the key pair that protects the backups.
#   bash infra/local/make-backup-key.sh
#
#   PUBLIC key  (age1...)          -> goes on the server in /etc/zb-mill/backup-recipients.txt
#   PRIVATE key (AGE-SECRET-KEY-…) -> NEVER on a server, never in git, never in chat.
#                                     Two copies, kept by two different people (O-37):
#                                     1. printed on paper, sealed envelope, Owner's safe/locker;
#                                     2. in your password manager (as a secure note).
# Losing every copy of the private key = losing every backup. Test a restore before go-live.
set -euo pipefail
command -v age-keygen >/dev/null || { echo "Install age first: https://github.com/FiloSottile/age#installation"; exit 1; }
OUT="${1:-zb-mill-backup-key-$(date +%Y%m%d).txt}"
[ -e "$OUT" ] && { echo "$OUT already exists"; exit 1; }
umask 077
age-keygen -o "$OUT" 2>/dev/null
echo "Private key written to: $OUT (only you can read it)"
echo
echo "PUBLIC key (safe to copy to the server):"
age-keygen -y "$OUT"
echo
echo "Now: print $OUT, put a copy in your password manager, seal the paper copy for the Owner,"
echo "then delete the file from this computer:  shred -u '$OUT'  (or move it to an encrypted USB)."
