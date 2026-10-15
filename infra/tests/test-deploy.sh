#!/usr/bin/env bash
# Tests infra/hostinger/zb-deploy-release with stand-in release archives: a tiny PHP "artisan" that
# records what it was asked to do, and a fake curl standing in for the /up smoke test. The layout
# mirrors shared hosting (releases/ + current link + shared/.env + shared/storage), run as one user,
# so there is no second web user, no systemctl and no database to drive.
# Run on a throwaway machine or CI runner:  bash infra/tests/test-deploy.sh
source "$(dirname "$0")/lib.sh"
command -v php >/dev/null || { echo "php is required"; exit 2; }
command -v tar >/dev/null || { echo "tar is required"; exit 2; }

WORK="$(mktemp -d /tmp/zb-dptest.XXXXXX)"; chmod 0755 "$WORK"
trap 'rm -rf "$WORK"' EXIT

# A fake curl so the smoke test needs no network: it fails only while the BAD marker file exists.
mkdir -p "$WORK/fakebin"
cat > "$WORK/fakebin/curl" <<'CURL'
#!/usr/bin/env bash
if [ -n "${ZB_TEST_SMOKE_FAIL_MARK:-}" ] && [ -e "${ZB_TEST_SMOKE_FAIL_MARK:-}" ]; then
    echo "curl: (22) The requested URL returned error: 500" >&2
    exit 22
fi
exit 0
CURL
chmod 0755 "$WORK/fakebin/curl"
export PATH="$WORK/fakebin:$PATH"

# --- shared-hosting layout
BASE="$WORK/accounts"
install -d -m 0755 "$BASE" "$BASE/releases" "$BASE/shared" "$BASE/shared/storage/logs" "$BASE/shared/storage/framework"
printf 'APP_KEY=base64:dGVzdC1vbmx5LW5vdC1hLXJlYWwta2V5LTMyYnl0ZXM=\nAPP_ENV=production\n' > "$BASE/shared/.env"
chmod 0640 "$BASE/shared/.env"

cat > "$WORK/zb-mill.conf" <<EOF
APP_BASE=$BASE
DOMAIN=test.invalid
KEEP_RELEASES=3
EOF
export ZB_CONF="$WORK/zb-mill.conf" PHP_BIN=php
export ZB_TEST_SMOKE_FAIL_MARK="$BASE/current/BAD"

# The commands under test, copied beside a zb-lib.sh like on the server.
mkdir -p "$WORK/bin"
cp "$INFRA_DIR/hostinger/zb-lib.sh" "$INFRA_DIR/hostinger/zb-deploy-release" "$INFRA_DIR/hostinger/zb-health-check" "$WORK/bin/"
chmod 0755 "$WORK/bin/zb-deploy-release" "$WORK/bin/zb-health-check"
chmod 0644 "$WORK/bin/zb-lib.sh"

# make_release <version> [FAIL_MIGRATE|FAIL_SEED|BAD]
make_release() {
    local v="$1" flag="${2:-}" src="$WORK/src-$1"
    mkdir -p "$src/public/build" "$src/vendor" "$src/bootstrap/cache" "$src/infra/hostinger"
    echo "$v" > "$src/RELEASE"
    echo '{}' > "$src/public/build/manifest.json"
    echo '<?php' > "$src/vendor/autoload.php"
    echo '<?php exit(0);' > "$src/public/index.php"
    cp "$INFRA_DIR/hostinger/zb-lib.sh" "$INFRA_DIR/hostinger/zb-deploy-release" "$INFRA_DIR/hostinger/zb-health-check" "$src/infra/hostinger/"
    cat > "$src/artisan" <<'PHP'
<?php
// Test stand-in for Laravel's artisan: records each call, touches the same files Laravel would.
$b = __DIR__; $a = array_slice($argv, 1); $cmd = $a[0] ?? '';
file_put_contents("$b/storage/logs/artisan-calls.log", trim(file("$b/RELEASE")[0])." ".implode(' ', $a)."\n", FILE_APPEND);
switch ($cmd) {
    case '--version':
        if (!is_readable("$b/.env")) { fwrite(STDERR, "cannot read .env\n"); exit(1); }
        file_put_contents("$b/bootstrap/cache/packages.php", '<?php return [];');
        echo "stub\n"; exit(0);
    case 'down': @mkdir("$b/storage/framework", 0755, true); touch("$b/storage/framework/down"); exit(0);
    case 'up': @unlink("$b/storage/framework/down"); exit(0);
    case 'migrate':
        if (file_exists("$b/FAIL_MIGRATE")) { fwrite(STDERR, "migration failed\n"); exit(1); }
        exit(0);
    case 'db:seed':
        if (file_exists("$b/FAIL_SEED")) { fwrite(STDERR, "seeding failed\n"); exit(1); }
        exit(0);
    case 'zb:backup':
        if (getenv('ZB_TEST_BACKUP_FAILS')) { fwrite(STDERR, "backup failed\n"); exit(1); }
        file_put_contents("$b/storage/logs/backup-ran.txt", "backup\n", FILE_APPEND);
        exit(0);
    case 'config:cache':
        file_put_contents("$b/bootstrap/cache/config.php", '<?php return '.var_export(['env' => file_get_contents("$b/.env")], true).';');
        exit(0);
    case 'route:cache': file_put_contents("$b/bootstrap/cache/routes-v7.php", '<?php'); exit(0);
    case 'event:cache': file_put_contents("$b/bootstrap/cache/events.php", '<?php return [];'); exit(0);
}
exit(0);
PHP
    [ -n "$flag" ] && touch "$src/$flag"
    tar -czf "$WORK/zb-mill-$v.tar.gz" -C "$src" .
    sha256sum "$WORK/zb-mill-$v.tar.gz" | cut -d' ' -f1
}

deploy() { "$WORK/bin/zb-deploy-release" "$@"; }
live_version() { head -1 "$BASE/current/RELEASE"; }
LOG="$BASE/shared/storage/logs/artisan-calls.log"

# order <pattern-before> <pattern-after>  -> the first match of each, by line number, in order
at()   { grep -n "$1" "$LOG" 2>/dev/null | head -1 | cut -d: -f1; }
order() {
    local a b; a="$(at "$1")"; b="$(at "$2")"
    [ -n "$a" ] && [ -n "$b" ] && [ "$a" -lt "$b" ]
}

echo "Deploy and rollback"
S1="$(make_release v1.0.0)"
expect_fail_with "a wrong checksum is refused" "checksum does not match" \
    deploy "$WORK/zb-mill-v1.0.0.tar.gz" --sha256 "$(printf '0%.0s' {1..64})"
expect_eq "nothing was unpacked after the refusal" "0" \
    bash -c "find '$BASE/releases' -mindepth 1 -maxdepth 1 | wc -l"

expect_ok "first deploy (v1.0.0)" deploy "$WORK/zb-mill-v1.0.0.tar.gz" --sha256 "$S1"
expect_eq "current points to v1.0.0" "v1.0.0" live_version
expect_ok ".env is the shared file" test "$(readlink "$BASE/current/.env")" = "$BASE/shared/.env"
expect_ok "storage is the shared folder" test "$(readlink "$BASE/current/storage")" = "$BASE/shared/storage"
expect_ok "the config cache was rebuilt on the server (holds APP_KEY)" grep -q "APP_KEY" "$BASE/current/bootstrap/cache/config.php"
expect_ok "the zb-* commands were installed into bin/" test -x "$BASE/bin/zb-deploy-release"
expect_eq "zb-lib.sh is installed readable, not executable (0644)" "644" stat -c '%a' "$BASE/bin/zb-lib.sh"
expect_fail "no backup was needed on the very first deploy" grep -q "zb:backup" "$LOG"
expect_fail "no maintenance mode on the very first deploy" test -e "$BASE/shared/storage/framework/down"
expect_ok "the deploy log records the release and checksum" grep -q "deployed.*v1.0.0.*sha256=$S1" "$BASE/deploy.log"

sleep 1
S2="$(make_release v1.1.0)"
expect_ok "second deploy (v1.1.0)" deploy "$WORK/zb-mill-v1.1.0.tar.gz" --sha256 "$S2"
expect_eq "current points to v1.1.0" "v1.1.0" live_version

echo "  the deploy order and the one migration connection"
expect_ok "the database was backed up first (php artisan zb:backup)" grep -q "^v1.0.0 zb:backup" "$LOG"
expect_ok "the site went into maintenance mode" grep -q "^v1.0.0 down --retry=15" "$LOG"
expect_ok "migrations ran with --force" grep -q "^v1.1.0 migrate --force" "$LOG"
expect_ok "system data was added (BR-OPS-11)" grep -q "^v1.1.0 db:seed --class=SystemDataSeeder --force" "$LOG"
expect_fail "no second migration connection is used (pgsql_migrator is gone)" grep -q -- "--database=" "$LOG"
expect_ok "backup ran before maintenance mode" order "^v1.0.0 zb:backup" "^v1.0.0 down"
expect_ok "maintenance mode before migrating" order "^v1.0.0 down" "^v1.1.0 migrate"
expect_ok "migrations before the system data" order "^v1.1.0 migrate" "^v1.1.0 db:seed"
expect_ok "system data before the caches" order "^v1.1.0 db:seed" "^v1.1.0 config:cache"
expect_ok "the caches before the switch (up)" order "^v1.1.0 config:cache" "^v1.1.0 up"
expect_fail "the site was not left in maintenance mode" test -e "$BASE/shared/storage/framework/down"

sleep 1
S3="$(make_release v1.2.0 FAIL_MIGRATE)"
expect_fail_with "a failing migration stops the deploy" "DEPLOY FAILED" \
    deploy "$WORK/zb-mill-v1.2.0.tar.gz" --sha256 "$S3"
expect_eq "live release unchanged after the failed migration" "v1.1.0" live_version
expect_fail "the site is up again after the failed deploy" test -e "$BASE/shared/storage/framework/down"
expect_eq "the failed release is kept for inspection, marked .failed" "1" \
    bash -c "find '$BASE/releases' -maxdepth 1 -name '*.failed' | wc -l"

sleep 1
S4="$(make_release v1.2.1 FAIL_SEED)"
expect_fail_with "a failing system-data step stops the deploy (BR-OPS-11)" "DEPLOY FAILED" \
    deploy "$WORK/zb-mill-v1.2.1.tar.gz" --sha256 "$S4"
expect_eq "live release unchanged after the failed system data" "v1.1.0" live_version
expect_fail "the site is up again after the failed system-data step" test -e "$BASE/shared/storage/framework/down"

sleep 1
S5="$(make_release v1.3.0 BAD)"
expect_fail_with "a release that fails the smoke test is rolled back automatically" "rolled back to" \
    deploy "$WORK/zb-mill-v1.3.0.tar.gz" --sha256 "$S5"
expect_eq "live release is v1.1.0 again" "v1.1.0" live_version
expect_fail "the site is not left in maintenance mode" test -e "$BASE/shared/storage/framework/down"

sleep 1
export ZB_TEST_BACKUP_FAILS=1
S6="$(make_release v1.4.0)"
expect_fail "the deploy stops when the pre-deploy backup fails" \
    deploy "$WORK/zb-mill-v1.4.0.tar.gz" --sha256 "$S6"
expect_eq "live release unchanged when the backup failed" "v1.1.0" live_version
expect_fail "the site was never put into maintenance when the backup failed" test -e "$BASE/shared/storage/framework/down"
unset ZB_TEST_BACKUP_FAILS

echo "  rollback, locking and cleanup"
expect_ok "rollback command" deploy --rollback
expect_eq "rollback goes to the previous good release (v1.0.0)" "v1.0.0" live_version
expect_ok "list shows the live release" bash -c "'$WORK/bin/zb-deploy-release' --list | grep -q '(live)'"

mkdir -p "$WORK/evil/x"; echo hi > "$WORK/evil/x/a"
tar -czf "$WORK/evil.tar.gz" -C "$WORK/evil" ./x/../../../escape 2>/dev/null \
    || tar -czf "$WORK/evil.tar.gz" --transform 's|^|../|' -C "$WORK/evil" x
SE="$(sha256sum "$WORK/evil.tar.gz" | cut -d' ' -f1)"
expect_fail_with "an archive with ../ paths is refused" "unsafe paths" deploy "$WORK/evil.tar.gz" --sha256 "$SE"

if command -v flock >/dev/null 2>&1; then
    exec 8> "$BASE/.deploy.lock"; flock 8
    expect_fail_with "a second deploy at the same time is refused" "another deploy is running" deploy --list
    flock -u 8; exec 8>&-
fi

for v in v2.0.0 v2.1.0 v2.2.0 v2.3.0; do
    sleep 1; s="$(make_release "$v")"
    deploy "$WORK/zb-mill-$v.tar.gz" --sha256 "$s" >/dev/null 2>&1 || fail "deploy $v"
done
expect_eq "old releases are cleaned up (KEEP_RELEASES=3)" "3" \
    bash -c "find '$BASE/releases' -mindepth 1 -maxdepth 1 -type d ! -name '*.failed' | wc -l"
expect_eq "the newest release is live" "v2.3.0" live_version

summary "Deploy"
