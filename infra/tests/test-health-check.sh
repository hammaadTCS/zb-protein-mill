#!/usr/bin/env bash
# Tests infra/hostinger/zb-health-check with stand-ins for the certificate and the app's /up
# answer. No root, no services to restart and nothing to install: on shared hosting the health
# check runs as the account's own user and only reads files and asks the site itself.
#   bash infra/tests/test-health-check.sh
source "$(dirname "$0")/lib.sh"
command -v openssl >/dev/null || { echo "openssl is required"; exit 2; }

WORK="$(mktemp -d /tmp/zb-hctest.XXXXXX)"; chmod 0755 "$WORK"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/app" "$WORK/status" "$WORK/hc"

openssl req -x509 -newkey rsa:2048 -nodes -keyout "$WORK/k.pem" -out "$WORK/good.pem" -days 60 -subj /CN=test.invalid 2>/dev/null
openssl req -x509 -newkey rsa:2048 -nodes -keyout "$WORK/k.pem" -out "$WORK/expiring.pem" -days 3 -subj /CN=test.invalid 2>/dev/null
echo '{}' > "$WORK/status/backup-db-last-success.json"
echo '{}' > "$WORK/status/backup-files-last-success.json"

cat > "$WORK/zb-mill.conf" <<EOF
DOMAIN=test.invalid
APP_BASE=$WORK/app
STATUS_DIR=$WORK/status
HC_DIR=$WORK/hc
EOF
export ZB_CONF="$WORK/zb-mill.conf"

# run <cert-file> <app-check> <disk-alert-percent> <verify-file>   (omitted = the good values)
run() {
    local cert="${1:-$WORK/good.pem}" app="${2:-true}" disk="${3:-100}" verify="${4:-$WORK/verify.json}"
    env CERT_FILE="$cert" APP_CHECK_CMD="$app" DISK_ALERT_PERCENT="$disk" VERIFY_STATUS_FILE="$verify" \
        bash "$INFRA_DIR/hostinger/zb-health-check"
}
fresh() { touch "$WORK/status/backup-db-last-success.json" "$WORK/status/backup-files-last-success.json"; }

echo "Health check"
fresh
expect_ok "everything is well -> exit 0" run
expect_fail_with "a certificate expiring within 14 days is reported" "expires within 14 days" \
    run "$WORK/expiring.pem"
expect_fail_with "the app not answering /up is reported" "does not answer 200" \
    run "$WORK/good.pem" false
expect_fail_with "a full disk is reported" "full" \
    run "$WORK/good.pem" true 1

touch -d '2 days ago' "$WORK/status/backup-db-last-success.json"
expect_fail_with "an old database backup is reported" "last good database backup was" run
fresh

mv "$WORK/status/backup-db-last-success.json" "$WORK/status/backup-db.saved"
expect_fail_with "a missing backup status is reported" "no database backup has been recorded yet" run
mv "$WORK/status/backup-db.saved" "$WORK/status/backup-db-last-success.json"
fresh

echo '{"result":"ok","finished_at":"now"}' > "$WORK/verify.json"
expect_ok "a current, passing ledger check is not a problem" \
    run "$WORK/good.pem" true 100 "$WORK/verify.json"

echo '{"result":"failed","finished_at":"now","failed_checks":["journal_balance"]}' > "$WORK/verify.json"
expect_fail_with "a failed ledger check is reported" "integrity check FAILED" \
    run "$WORK/good.pem" true 100 "$WORK/verify.json"

echo '{"result":"ok","finished_at":"now"}' > "$WORK/verify.json"
touch -d '2 days ago' "$WORK/verify.json"
expect_fail_with "an old ledger check is reported" "integrity check last ran" \
    run "$WORK/good.pem" true 100 "$WORK/verify.json"

summary "Health check"
