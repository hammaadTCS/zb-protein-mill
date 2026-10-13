#!/usr/bin/env bash
# Tests zb-deploy-release with stand-in release archives (a tiny PHP "artisan" that records what
# it was asked to do) and a real PostgreSQL for the grants step.
# Run as root on a throwaway machine:  sudo bash infra/tests/test-deploy.sh
source "$(dirname "$0")/lib.sh"
require_root
command -v php >/dev/null || { echo "php is required"; exit 2; }

WORK="$(mktemp -d /tmp/zb-dptest.XXXXXX)"; chmod 0755 "$WORK"
cleanup() { pg_test_cluster_stop "$WORK"; rm -rf "$WORK"; }
trap cleanup EXIT

for u in zbmill zbdeploy zbbackup; do ensure_system_user "$u"; done
usermod -aG zbmill zbdeploy
id nobody >/dev/null 2>&1 || useradd --system nobody

mkdir -p "$WORK/bin" "$WORK/sql"
cp "$INFRA_DIR"/bin/* "$WORK/bin/"; chmod 0755 "$WORK"/bin/zb-*; chmod 0644 "$WORK/bin/zb-lib.sh"
cp "$INFRA_DIR"/files/postgres/*.sql "$INFRA_DIR/tests/fixtures/schema.sql" "$WORK/sql/"; chmod 0644 "$WORK"/sql/*

echo "Deploy and rollback"
pg_test_cluster_start "$WORK"
psql_file_as postgres postgres postgres "$WORK/sql/10-roles.sql" -v db_name=zb_mill >/dev/null
psql_file_as postgres postgres zb_mill "$WORK/sql/20-schema.sql" >/dev/null
psql_file_as zbdeploy zb_migrator zb_mill "$WORK/sql/schema.sql" >/dev/null
# Deliberately no grants yet: the deploy must apply them.

BASE="$WORK/srv"
install -d -o root -g root -m 0755 "$BASE"
install -d -o zbdeploy -g zbdeploy -m 0755 "$BASE/releases"
install -d -o root -g root -m 0755 "$BASE/shared"
for d in storage storage/app storage/app/private storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs; do
    install -d -o zbmill -g zbmill -m 2770 "$BASE/shared/$d"
done
printf 'APP_KEY=base64:dGVzdC1vbmx5LW5vdC1hLXJlYWwta2V5LTMyYnl0ZXM=\nAPP_ENV=production\n' > "$BASE/shared/.env"
chown zbdeploy:zbmill "$BASE/shared/.env"; chmod 0640 "$BASE/shared/.env"

cat > "$WORK/systemctl-stub" <<EOF
#!/usr/bin/env bash
echo "\$*" >> "$WORK/systemctl.log"
[ "\$1 \$2" = "start zb-mill-backup-db.service" ] && [ -e "$WORK/BACKUP_FAILS" ] && exit 1
exit 0
EOF
chmod 0755 "$WORK/systemctl-stub"

cat > "$WORK/zb-mill.conf" <<EOF
APP_BASE=$BASE
APP_USER=zbmill
DEPLOY_USER=zbdeploy
DB_NAME=zb_mill
PHP_VERSION=8.3
DOMAIN=test.invalid
KEEP_RELEASES=3
EOF
export ZB_CONF="$WORK/zb-mill.conf" SYSTEMCTL="$WORK/systemctl-stub" GRANTS_SQL="$WORK/sql/30-app-grants.sql"
export SMOKE_CMD="php $BASE/current/public/index.php"

# make_release <version> [FAIL_MIGRATE|FAIL_SEED|BAD]
make_release() {
    local v="$1" flag="${2:-}" src="$WORK/src-$1"
    mkdir -p "$src/public/build" "$src/vendor" "$src/bootstrap/cache" "$src/storage/logs"
    echo "$v" > "$src/RELEASE"
    echo '{}' > "$src/public/build/manifest.json"
    echo '<?php' > "$src/vendor/autoload.php"
    echo '<?php return ["shipped" => true];' > "$src/bootstrap/cache/config.php"
    echo '<?php exit(file_exists(__DIR__."/../BAD") ? 1 : 0);' > "$src/public/index.php"
    cat > "$src/artisan" <<'PHP'
<?php
// Test stand-in for Laravel's artisan: records each call, touches the same files Laravel would.
$b = __DIR__; $a = array_slice($argv, 1); $cmd = $a[0] ?? '';
$me = posix_getpwuid(posix_geteuid())['name'];
file_put_contents("$b/storage/logs/artisan-calls.log", trim(file("$b/RELEASE")[0])." $me ".implode(' ', $a)."\n", FILE_APPEND);
switch ($cmd) {
    case '--version':
        if (!is_readable("$b/.env")) { fwrite(STDERR, "cannot read .env\n"); exit(1); }
        file_put_contents("$b/bootstrap/cache/packages.php", '<?php return [];');
        echo "stub\n"; exit(0);
    case 'down': touch("$b/storage/framework/down"); exit(0);
    case 'up': @unlink("$b/storage/framework/down"); exit(0);
    case 'migrate':
        if (file_exists("$b/FAIL_MIGRATE")) { fwrite(STDERR, "migration failed\n"); exit(1); }
        exit(0);
    case 'db:seed':
        if (file_exists("$b/FAIL_SEED")) { fwrite(STDERR, "seeding failed\n"); exit(1); }
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
live() { basename "$(readlink -f "$BASE/current")"; }
live_version() { head -1 "$BASE/current/RELEASE"; }

S1="$(make_release v1.0.0)"
expect_fail_with "a wrong checksum is refused" "checksum does not match" deploy "$WORK/zb-mill-v1.0.0.tar.gz" --sha256 "$(printf '0%.0s' {1..64})"
expect_eq "nothing was unpacked after the refusal" "0" bash -c "find '$BASE/releases' -mindepth 1 -maxdepth 1 | wc -l"

expect_ok "first deploy (v1.0.0)" deploy "$WORK/zb-mill-v1.0.0.tar.gz" --sha256 "$S1"
expect_eq "current points to v1.0.0" "v1.0.0" live_version
expect_ok ".env is the shared file" test "$(readlink "$BASE/current/.env")" = "$BASE/shared/.env"
expect_ok "storage is the shared folder" test "$(readlink "$BASE/current/storage")" = "$BASE/shared/storage"
expect_eq "config cache (holds APP_KEY) is 0640 zbdeploy:zbmill" "640 zbdeploy zbmill" stat -c '%a %U %G' "$BASE/current/bootstrap/cache/config.php"
expect_ok "the shipped config cache was replaced by one built on the server" grep -q "APP_KEY" "$BASE/current/bootstrap/cache/config.php"
expect_ok "web user (zbmill) can read the config cache" runuser -u zbmill -- cat "$BASE/current/bootstrap/cache/config.php"
expect_fail "other users cannot read the config cache" runuser -u nobody -- cat "$BASE/current/bootstrap/cache/config.php"
expect_fail "web user cannot change release code" runuser -u zbmill -- bash -c "echo x >> '$BASE/current/public/index.php'"
expect_fail "web user cannot write the cache folder" runuser -u zbmill -- touch "$BASE/current/bootstrap/cache/x.php"
expect_ok "backup ran before migrating" grep -q "start zb-mill-backup-db.service" "$WORK/systemctl.log"
expect_ok "PHP-FPM was reloaded" grep -q "reload php8.3-fpm" "$WORK/systemctl.log"
expect_ok "migrations ran as zbdeploy with the migrator connection" grep -q "v1.0.0 zbdeploy migrate --force --database=pgsql_migrator" "$BASE/shared/storage/logs/artisan-calls.log"
expect_ok "system data was added as zbdeploy with the migrator connection (BR-OPS-11)" grep -q "v1.0.0 zbdeploy db:seed --class=SystemDataSeeder --force --database=pgsql_migrator" "$BASE/shared/storage/logs/artisan-calls.log"
expect_ok "system data ran after the migrations and before the config cache" bash -c "grep '^v1.0.0 ' '$BASE/shared/storage/logs/artisan-calls.log' | awk '{print \$3}' | grep -E '^(migrate|db:seed|config:cache)\$' | tr '\n' ' ' | grep -q '^migrate db:seed config:cache '"
expect_ok "grants were applied (app can read purchases)" psql_as zbmill zb_app zb_mill "select count(*) from purchases"
expect_ok "deploy log records the release and checksum" grep -q "deployed.*v1.0.0.*sha256=$S1" "$BASE/deploy.log"

sleep 1
S2="$(make_release v1.1.0)"
expect_ok "second deploy (v1.1.0)" deploy "$WORK/zb-mill-v1.1.0.tar.gz" --sha256 "$S2"
expect_eq "current points to v1.1.0" "v1.1.0" live_version
expect_ok "maintenance mode was used" grep -q "v1.0.0 zbdeploy down --retry=15" "$BASE/shared/storage/logs/artisan-calls.log"
expect_fail "site is not left in maintenance mode" test -e "$BASE/shared/storage/framework/down"
V2_ID="$(live)"

sleep 1
S3="$(make_release v1.2.0 FAIL_MIGRATE)"
expect_fail_with "a failing migration stops the deploy" "DEPLOY FAILED" deploy "$WORK/zb-mill-v1.2.0.tar.gz" --sha256 "$S3"
expect_eq "live release unchanged after failed migration" "$V2_ID" live
expect_fail "site is up again after the failed deploy" test -e "$BASE/shared/storage/framework/down"
expect_eq "the failed release is kept for inspection, marked .failed" "1" bash -c "find '$BASE/releases' -maxdepth 1 -name '*.failed' | wc -l"

sleep 1
S6="$(make_release v1.2.1 FAIL_SEED)"
expect_fail_with "a failing system-data step stops the deploy (BR-OPS-11)" "DEPLOY FAILED" deploy "$WORK/zb-mill-v1.2.1.tar.gz" --sha256 "$S6"
expect_eq "live release unchanged after failed system data" "$V2_ID" live
expect_fail "site is up again after the failed system-data step" test -e "$BASE/shared/storage/framework/down"

sleep 1
S4="$(make_release v1.3.0 BAD)"
expect_fail_with "a release that fails the smoke test is rolled back automatically" "rolled back to" deploy "$WORK/zb-mill-v1.3.0.tar.gz" --sha256 "$S4"
expect_eq "live release is v1.1.0 again" "v1.1.0" live_version

sleep 1
touch "$WORK/BACKUP_FAILS"
S5="$(make_release v1.4.0)"
expect_fail "deploy stops when the pre-deploy backup fails" deploy "$WORK/zb-mill-v1.4.0.tar.gz" --sha256 "$S5"
expect_eq "live release unchanged when backup failed" "v1.1.0" live_version
expect_fail "site was never put into maintenance when backup failed" test -e "$BASE/shared/storage/framework/down"
rm -f "$WORK/BACKUP_FAILS"

expect_ok "rollback command" deploy --rollback
expect_eq "rollback goes to the previous good release (v1.0.0)" "v1.0.0" live_version
expect_ok "list shows the live release" bash -c "'$WORK/bin/zb-deploy-release' --list | grep -q '(live)'"

mkdir -p "$WORK/evil/x"; echo hi > "$WORK/evil/x/a"
tar -czf "$WORK/evil.tar.gz" -C "$WORK/evil" ./x/../../../escape 2>/dev/null || tar -czf "$WORK/evil.tar.gz" --transform 's|^|../|' -C "$WORK/evil" x
SE="$(sha256sum "$WORK/evil.tar.gz" | cut -d' ' -f1)"
expect_fail_with "an archive with ../ paths is refused" "unsafe paths" deploy "$WORK/evil.tar.gz" --sha256 "$SE"

exec 8> /run/lock/zb-deploy.lock; flock 8
expect_fail_with "a second deploy at the same time is refused" "another deploy is running" deploy --list
flock -u 8; exec 8>&-

for v in v2.0.0 v2.1.0 v2.2.0 v2.3.0; do
    sleep 1; s="$(make_release "$v")"; deploy "$WORK/zb-mill-$v.tar.gz" --sha256 "$s" >/dev/null 2>&1 || fail "deploy $v"
done
expect_eq "old releases are cleaned up (KEEP_RELEASES=3)" "3" bash -c "find '$BASE/releases' -mindepth 1 -maxdepth 1 -type d ! -name '*.failed' | wc -l"
expect_eq "newest release is live" "v2.3.0" live_version

summary "Deploy"
