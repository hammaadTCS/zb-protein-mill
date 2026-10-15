#!/usr/bin/env bash
# Tests the shared-hosting settings loader and the retirement of the VPS provisioning tree. There
# is no provision step any more -- hPanel builds the account and the scripts under
# infra/hostinger/ are copied in by hand (infra/hostinger/README.md). Anything that needs a real
# server is not tested here.
source "$(dirname "$0")/lib.sh"
WORK="$(mktemp -d /tmp/zb-provtest.XXXXXX)"
trap 'rm -rf "$WORK"' EXIT
LIB="$INFRA_DIR/hostinger/zb-lib.sh"

echo "Shared-hosting provisioning (config loader + retired VPS tree)"

# ---------- nothing provisions a server any more
expect_fail "the old infra/provision/ tree is gone" test -e "$INFRA_DIR/provision"
expect_ok "it is kept for history" test -f "$INFRA_DIR/history/provision/lib.sh"
expect_ok "the history README says hPanel replaced provisioning" grep -q 'hPanel' "$INFRA_DIR/history/README.md"
expect_ok "the hostinger README says hPanel does first-time setup" grep -q 'hPanel' "$INFRA_DIR/hostinger/README.md"
expect_ok "the hostinger README points at ~/accounts" grep -q 'accounts' "$INFRA_DIR/hostinger/README.md"
expect_ok "the hostinger README has the one cron line (schedule:run)" grep -q 'schedule:run' "$INFRA_DIR/hostinger/README.md"

# ---------- the live settings loader (runs as the account user, no root)
load() { # load <conf> [key...] -> zb_load_config in a subshell, stderr+stdout captured
    local conf="$1"; shift
    env ZB_CONF="$conf" bash -c "source '$LIB'; zb_load_config $*" 2>&1
}
cat > "$WORK/good.conf" <<CONF
DOMAIN=accounts.example.com
APP_BASE=/home/acct/accounts
PHP_BIN=php
CONF
expect_ok "a valid settings file is accepted" load "$WORK/good.conf" DOMAIN APP_BASE
expect_fail_with "a missing settings file is refused" "not found" load "$WORK/nope.conf" DOMAIN
expect_fail_with "a required key that is absent is refused" "MISSING_KEY is not set" \
    load "$WORK/good.conf" DOMAIN MISSING_KEY
printf 'DOMAIN=accounts.example.com\nAPP_BASE=\n' > "$WORK/empty.conf"
expect_fail_with "a required key left empty is refused" "APP_BASE is not set" load "$WORK/empty.conf" DOMAIN APP_BASE

expect_eq "age_minutes of a missing file is the 'absent' sentinel" "999999" \
    bash -c "source '$LIB'; age_minutes '$WORK/missing'"

echo "  note  this file only checks the loader and the retirement; account creation, hPanel cron and"
echo "        the first deploy are done in hPanel and over SSH, not here."

summary "Provisioning"
