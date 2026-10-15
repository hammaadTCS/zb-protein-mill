#!/usr/bin/env bash
# ZB Mill server provisioning. Run on a NEW Ubuntu 26.04 LTS server as root (or with sudo):
#
#   sudo bash infra/provision/setup.sh /root/zb-server.conf            # all steps
#   sudo bash infra/provision/setup.sh /root/zb-server.conf 50 60      # only some steps
#
# Every step is safe to run again. Read docs/operations/server-setup.md first: some steps need
# you to do something in between (DNS, storage key, backup public key, monitor URLs).
# shellcheck source=lib.sh
source "$(dirname "$0")/lib.sh"

[ "$(id -u)" -eq 0 ] || die "run with sudo"
[ $# -ge 1 ] || die "usage: setup.sh <settings file> [step numbers...]"
load_server_conf "$1"; shift
export ENV_NAME DOMAIN LETSENCRYPT_EMAIL ADMIN_USER ADMIN_SSH_PUBLIC_KEY PHP_VERSION PG_VERSION \
    SWAP_SIZE_GB RCLONE_REMOTE BACKUP_DAILY_HOUR_UTC

STEPS=("$@")
[ ${#STEPS[@]} -gt 0 ] || STEPS=(10 20 30 40 50 60 70)

for n in "${STEPS[@]}"; do
    f="$(find "$PROVISION_DIR/steps" -maxdepth 1 -name "$n-*.sh" | sort | head -1)"
    [ -n "$f" ] || die "no step $n"
    say "Step $(basename "$f" .sh)"
    # shellcheck source=/dev/null
    source "$f"
done
say "Done: steps ${STEPS[*]}"
