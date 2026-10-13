#!/usr/bin/env bash
# Tests the provisioning helpers that can run without a real server: settings validation,
# template rendering (no placeholder left behind), APP_KEY format, backup key file checks.
source "$(dirname "$0")/lib.sh"
WORK="$(mktemp -d /tmp/zb-provtest.XXXXXX)"
trap 'rm -rf "$WORK"' EXIT
P="$INFRA_DIR/provision"

good_conf() {
    cat > "$WORK/s.conf" <<CONF
ENV_NAME=production
DOMAIN=accounts.zb-test.invalid
LETSENCRYPT_EMAIL=admin@zb-test.invalid
ADMIN_USER=zbadmin
ADMIN_SSH_PUBLIC_KEY="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAItest admin@laptop"
PHP_VERSION=8.5
PG_VERSION=18
RCLONE_REMOTE=offsite:zb-bucket
CONF
}
in_lib() { bash -c "source '$P/lib.sh'; $1" ; }

echo "Provisioning helpers"
good_conf
expect_ok "valid settings file is accepted" in_lib "load_server_conf '$WORK/s.conf'"
sed -i 's/^DOMAIN=.*/DOMAIN=accounts.example.com/' "$WORK/s.conf"
expect_fail_with "example domain is refused" "example value" in_lib "load_server_conf '$WORK/s.conf'"
good_conf; sed -i 's/^ADMIN_SSH_PUBLIC_KEY=.*/ADMIN_SSH_PUBLIC_KEY="-----BEGIN OPENSSH PRIVATE KEY-----"/' "$WORK/s.conf"
expect_fail_with "a private key instead of a public key is refused" "does not look like a public key" in_lib "load_server_conf '$WORK/s.conf'"
good_conf; sed -i 's/^ENV_NAME=.*/ENV_NAME=live/' "$WORK/s.conf"
expect_fail_with "unknown environment name is refused" "production or staging" in_lib "load_server_conf '$WORK/s.conf'"
good_conf; sed -i 's/^RCLONE_REMOTE=.*/RCLONE_REMOTE=/' "$WORK/s.conf"
expect_fail_with "empty required setting is refused" "RCLONE_REMOTE is empty" in_lib "load_server_conf '$WORK/s.conf'"

good_conf
for t in "files/nginx/zb-mill.conf.template" "files/ssh/10-zb-mill.conf" "../.env.production.example"; do
    out="$WORK/rendered-$(basename "$t")"
    in_lib "load_server_conf '$WORK/s.conf'; render '$INFRA_DIR/$t' '$out' 0644 $(id -un):$(id -gn)"
    expect_fail "no __PLACEHOLDER__ left in $(basename "$t")" grep -qE '__[A-Z_]+__' "$out"
done
expect_ok "nginx site names the domain" grep -q "server_name accounts.zb-test.invalid;" "$WORK/rendered-zb-mill.conf.template"
expect_ok "SSH allows only the admin user" grep -qx "AllowUsers zbadmin" "$WORK/rendered-10-zb-mill.conf"
expect_ok ".env has the HTTPS URL" grep -qx "APP_URL=https://accounts.zb-test.invalid" "$WORK/rendered-.env.production.example"
expect_ok ".env template has an empty APP_KEY (filled on the server only)" grep -qx "APP_KEY=" "$INFRA_DIR/../.env.production.example"

KEY="base64:$(openssl rand -base64 32)"
expect_eq "generated APP_KEY decodes to 32 bytes (AES-256)" "32" bash -c "echo '${KEY#base64:}' | base64 -d | wc -c"

age-keygen -o "$WORK/k.txt" 2>/dev/null; age-keygen -y "$WORK/k.txt" > "$WORK/pub.txt"
expect_ok "a public key file is accepted" in_lib "check_recipients_file '$WORK/pub.txt'"
expect_fail_with "a private key file is refused" "PRIVATE key" in_lib "check_recipients_file '$WORK/k.txt'"
printf '# comment\nnot-a-key\n' > "$WORK/bad.txt"
expect_fail_with "a wrong line is refused" "not an age public key" in_lib "check_recipients_file '$WORK/bad.txt'"
: > "$WORK/empty.txt"
expect_fail_with "an empty file is refused" "PUBLIC key" in_lib "check_recipients_file '$WORK/empty.txt'"

summary "Provisioning helpers"
