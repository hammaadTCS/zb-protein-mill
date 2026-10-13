#!/usr/bin/env bash
# Tests zb-health-check with stand-ins for systemctl, timedatectl and the app check.
source "$(dirname "$0")/lib.sh"
require_root

WORK="$(mktemp -d /tmp/zb-hctest.XXXXXX)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/status" "$WORK/hc"

openssl req -x509 -newkey rsa:2048 -nodes -keyout "$WORK/k.pem" -out "$WORK/good.pem" -days 60 -subj /CN=test.invalid 2>/dev/null
openssl req -x509 -newkey rsa:2048 -nodes -keyout "$WORK/k.pem" -out "$WORK/expiring.pem" -days 3 -subj /CN=test.invalid 2>/dev/null
printf '#!/bin/sh\n[ -e %s/DOWN ] && [ "$3" = "postgresql" ] && exit 3\nexit 0\n' "$WORK" > "$WORK/systemctl"
printf '#!/bin/sh\necho yes\n' > "$WORK/timedatectl"
chmod 0755 "$WORK/systemctl" "$WORK/timedatectl"
echo '{}' > "$WORK/status/backup-db-last-success.json"
echo '{}' > "$WORK/status/backup-files-last-success.json"

cat > "$WORK/zb-mill.conf" <<EOF
DOMAIN=test.invalid
PHP_VERSION=8.5
STATUS_DIR=$WORK/status
HC_DIR=$WORK/hc
EOF
run() {
    env ZB_CONF="$WORK/zb-mill.conf" SYSTEMCTL="$WORK/systemctl" TIMEDATECTL="$WORK/timedatectl" \
        CERT_FILE="${CERT:-$WORK/good.pem}" APP_CHECK_CMD="${APP:-true}" DISK_ALERT_PERCENT="${DISK:-100}" \
        bash "$INFRA_DIR/bin/zb-health-check"
}

echo "Health check"
expect_ok "all good -> exit 0" run
expect_fail_with "certificate expiring within 14 days is reported" "expires within 14 days" env CERT="$WORK/expiring.pem" bash -c "$(declare -f run); WORK='$WORK' INFRA_DIR='$INFRA_DIR' run"
expect_fail_with "a stopped service is reported" "postgresql is not running" bash -c "touch '$WORK/DOWN'; $(declare -f run); WORK='$WORK' INFRA_DIR='$INFRA_DIR' run"
rm -f "$WORK/DOWN"
touch -d '3 hours ago' "$WORK/status/backup-db-last-success.json"
expect_fail_with "an old database backup is reported" "last good database backup was" run
touch "$WORK/status/backup-db-last-success.json"
expect_fail_with "the app not answering /up is reported" "does not answer 200" env APP=false bash -c "$(declare -f run); WORK='$WORK' INFRA_DIR='$INFRA_DIR' run"
expect_fail_with "a full disk is reported" "full" env DISK=1 bash -c "$(declare -f run); WORK='$WORK' INFRA_DIR='$INFRA_DIR' run"
echo '{"result":"ok","finished_at":"x","failed_checks":[]}' > "$WORK/verify.json"
expect_ok "integrity check ok -> no problem" env VERIFY_STATUS_FILE="$WORK/verify.json" bash -c "$(declare -f run); WORK='$WORK' INFRA_DIR='$INFRA_DIR' run"
echo '{"result":"failed","finished_at":"x","failed_checks":["journal_balance"]}' > "$WORK/verify.json"
expect_fail_with "a failed integrity check is reported" "integrity check FAILED" env VERIFY_STATUS_FILE="$WORK/verify.json" bash -c "$(declare -f run); WORK='$WORK' INFRA_DIR='$INFRA_DIR' run"
echo '{"result":"ok"}' > "$WORK/verify.json"; touch -d '2 days ago' "$WORK/verify.json"
expect_fail_with "an old integrity check is reported" "integrity check last ran" env VERIFY_STATUS_FILE="$WORK/verify.json" bash -c "$(declare -f run); WORK='$WORK' INFRA_DIR='$INFRA_DIR' run"

summary "Health check"
