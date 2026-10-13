#!/usr/bin/env bash
# Tests the nginx site, security headers and the PHP-FPM pool with a stand-in front controller.
# Run as root on a THROWAWAY machine (it replaces the nginx sites and adds a PHP-FPM pool):
#   sudo bash infra/tests/test-web.sh
# Differences from production, on purpose: self-signed certificate instead of Let's Encrypt;
# on nginx older than 1.25.1 the "http2 on;" line is removed (Ubuntu 26.04 ships 1.28).
source "$(dirname "$0")/lib.sh"
require_root
command -v nginx >/dev/null || { echo "nginx is required"; exit 2; }
FPM="$(ls /usr/sbin/php-fpm* 2>/dev/null | sort -V | tail -1)"
[ -n "$FPM" ] || { echo "php-fpm is required"; exit 2; }
PHPV="$(basename "$FPM" | sed 's/php-fpm//')"

DOMAIN=zb-test.invalid
WORK="$(mktemp -d /tmp/zb-webtest.XXXXXX)"; chmod 0755 "$WORK"
BASE="$WORK/srv"
SOCK=/run/php/zb-mill.sock
FPM_PID="$WORK/fpm.pid"

cleanup() {
    nginx -s stop 2>/dev/null || true
    [ -f "$FPM_PID" ] && kill "$(cat "$FPM_PID")" 2>/dev/null || true
    rm -f /etc/nginx/sites-enabled/zb-mill.conf /etc/nginx/snippets/zb-mill-headers.conf /etc/nginx/conf.d/zz-zb-mill-http.conf
    rm -rf "/etc/letsencrypt/live/$DOMAIN" "$WORK"
    [ -f "$WORK.default-site" ] && mv "$WORK.default-site" /etc/nginx/sites-enabled/default
}
trap cleanup EXIT
[ "${KEEP_RUNNING:-0}" = 1 ] && trap - EXIT

ensure_system_user zbmill
id www-data >/dev/null 2>&1 || useradd --system www-data

# --- stand-in app
R="$BASE/releases/r1"
mkdir -p "$R/public/build/assets"
ln -sfn "$R" "$BASE/current"
cat > "$R/public/index.php" <<'PHP'
<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-test'");
$u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($u === '/up') { echo 'up'; exit; }
if ($u === '/whoami') { echo posix_getpwuid(posix_geteuid())['name']; exit; }
if ($u === '/ini') {
    echo json_encode([
        'exec' => function_exists('exec'), 'proc_open' => function_exists('proc_open'),
        'allow_url_fopen' => ini_get('allow_url_fopen'), 'expose_php' => ini_get('expose_php'),
        'upload_max_filesize' => ini_get('upload_max_filesize'), 'opcache' => ini_get('opcache.validate_timestamps'),
        'script' => $_SERVER['SCRIPT_FILENAME'],
    ]);
    exit;
}
if ($u === '/upload') { echo 'received ' . strlen(file_get_contents('php://input')); exit; }
http_response_code(404); echo 'not found';
PHP
echo '<?php echo "this must never run";' > "$R/public/other.php"
echo 'APP_KEY=secret-should-never-be-served' > "$R/public/.env"
head -c 20000 /dev/zero | tr '\0' 'a' | sed 's/^/console.log("/; s/$/");/' > "$R/public/build/assets/app-3f2a9c.js"
echo 'User-agent: *' > "$R/public/robots.txt"; echo 'Disallow: /' >> "$R/public/robots.txt"
chmod -R a+rX "$BASE"

# --- certificate stand-in
mkdir -p "/etc/letsencrypt/live/$DOMAIN"
openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj "/CN=$DOMAIN" \
    -keyout "/etc/letsencrypt/live/$DOMAIN/privkey.pem" -out "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" 2>/dev/null

# --- PHP-FPM with the project pool only
mkdir -p "$WORK/fpm/pool.d" /run/php /var/log/zb-mill
chown zbmill:zbmill /var/log/zb-mill
cp "$INFRA_DIR/files/php/fpm-pool-zb-mill.conf" "$WORK/fpm/pool.d/zb-mill.conf"
cat > "$WORK/fpm/php-fpm.conf" <<EOF
[global]
pid = $FPM_PID
error_log = $WORK/fpm/error.log
daemonize = yes
include = $WORK/fpm/pool.d/*.conf
EOF
"$FPM" -t -y "$WORK/fpm/php-fpm.conf" -d "include_path=." >/dev/null 2>&1 && pass "php-fpm accepts the pool file" || fail "php-fpm accepts the pool file"
PHP_INI_SCAN_DIR="/etc/php/$PHPV/fpm/conf.d:$INFRA_DIR/files/php" "$FPM" -y "$WORK/fpm/php-fpm.conf"
sleep 1

# --- nginx with the project site (stop any nginx the package started)
systemctl stop nginx >/dev/null 2>&1 || nginx -s quit >/dev/null 2>&1 || true
sleep 1
[ -e /etc/nginx/sites-enabled/default ] && mv /etc/nginx/sites-enabled/default "$WORK.default-site"
sed -e "s|__DOMAIN__|$DOMAIN|g" -e "s|__APP_BASE__|$BASE|g" -e "s|__PHP_SOCK__|$SOCK|g" \
    "$INFRA_DIR/files/nginx/zb-mill.conf.template" > /etc/nginx/sites-enabled/zb-mill.conf
NGV="$(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')"
if [ "$(printf '%s\n1.25.1\n' "$NGV" | sort -V | head -1)" != 1.25.1 ]; then
    sed -i '/http2 on;/d' /etc/nginx/sites-enabled/zb-mill.conf
    echo "  note  nginx $NGV: 'http2 on;' removed for this test only"
fi
if [ ! -e /proc/net/if_inet6 ]; then
    sed -i '/listen \[::\]/d' /etc/nginx/sites-enabled/zb-mill.conf
    echo "  note  no IPv6 here: [::] listen lines removed (the provisioning script does the same)"
fi
cp "$INFRA_DIR/files/nginx/snippets/zb-mill-headers.conf" /etc/nginx/snippets/
cp "$INFRA_DIR/files/nginx/zz-zb-mill-http.conf" /etc/nginx/conf.d/
mkdir -p /var/www/letsencrypt
expect_ok "nginx -t accepts the site" nginx -t
nginx
sleep 1

echo "Web server"
C=(curl -s --noproxy "*" --max-time 10 --resolve "$DOMAIN:443:127.0.0.1" --resolve "$DOMAIN:80:127.0.0.1" -k)
hdr() { "${C[@]}" -o /dev/null -D - "$@" | tr -d '\r'; }

expect_eq "HTTP redirects to HTTPS" "301 https://$DOMAIN/up" bash -c "$(printf '%q ' "${C[@]}") -o /dev/null -w '%{http_code} %{redirect_url}' http://$DOMAIN/up"
expect_eq "/up answers 200 through PHP-FPM" "up" "${C[@]}" "https://$DOMAIN/up"
expect_eq "PHP runs as zbmill" "zbmill" "${C[@]}" "https://$DOMAIN/whoami"
H="$(hdr "https://$DOMAIN/up")"
for h in "strict-transport-security: max-age=31536000" "x-content-type-options: nosniff" "x-frame-options: DENY" \
         "referrer-policy: same-origin" "x-robots-tag: noindex, nofollow" "permissions-policy: camera=()" \
         "content-security-policy: default-src 'self'"; do
    expect_ok "header: $h" grep -qiF "$h" <<<"$H"
done
expect_fail "no X-Powered-By header" grep -qi "^x-powered-by" <<<"$H"
expect_fail "no nginx version in Server header" grep -qiE "^server: nginx/[0-9]" <<<"$H"

INI="$("${C[@]}" "https://$DOMAIN/ini")"
expect_ok "exec() disabled in web requests" grep -q '"exec":false' <<<"$INI"
expect_ok "proc_open() disabled in web requests" grep -q '"proc_open":false' <<<"$INI"
expect_ok "allow_url_fopen off" grep -qE '"allow_url_fopen":"(0|)"' <<<"$INI"
expect_ok "upload limit 6M" grep -q '"upload_max_filesize":"6M"' <<<"$INI"
expect_ok "PHP sees the real release path (opcache-safe switching)" grep -q "releases\\\\/r1\\\\/public\\\\/index.php" <<<"$INI"

BH="$(hdr -H 'Accept-Encoding: gzip' "https://$DOMAIN/build/assets/app-3f2a9c.js")"
expect_ok "built assets cached for a year, immutable" grep -qi "cache-control: public, max-age=31536000, immutable" <<<"$BH"
expect_ok "built assets keep the security headers" grep -qi "x-content-type-options: nosniff" <<<"$BH"
expect_ok "built assets are gzip-compressed" grep -qi "content-encoding: gzip" <<<"$BH"

code() { "${C[@]}" -o /dev/null -w '%{http_code}' "$@"; }
expect_eq ".env in public/ is not served" "404" code "https://$DOMAIN/.env"
expect_eq "other .php files are not executed" "404" code "https://$DOMAIN/other.php"
expect_fail "other.php source is not leaked" grep -q "this must never run" <<<"$("${C[@]}" "https://$DOMAIN/other.php")"
expect_eq "unknown path goes to the app (404 from app)" "404" code "https://$DOMAIN/nothing-here"
expect_eq "robots.txt is served" "200" code "https://$DOMAIN/robots.txt"

head -c 9000000 /dev/zero > "$WORK/big.bin"
expect_eq "uploads over 8 MB are refused by nginx" "413" code -X POST --data-binary @"$WORK/big.bin" "https://$DOMAIN/upload"
head -c 4000000 /dev/zero > "$WORK/ok.bin"
expect_eq "a 4 MB upload reaches PHP" "received 4000000" "${C[@]}" -X POST -H 'Content-Type: application/octet-stream' --data-binary @"$WORK/ok.bin" "https://$DOMAIN/upload"

expect_fail "unknown host name over HTTPS: handshake refused" curl -s --noproxy "*" --max-time 5 -k --resolve "other.invalid:443:127.0.0.1" "https://other.invalid/"
expect_eq "unknown host name over HTTP: connection dropped" "000" bash -c "curl -s --noproxy \"*\" -o /dev/null -w '%{http_code}' --max-time 5 --resolve 'other.invalid:80:127.0.0.1' http://other.invalid/ || true"

"${C[@]}" -o /dev/null "https://$DOMAIN/up?party=Secret%20Name"
sleep 0.5
expect_fail "access log has no query strings" grep -q "Secret" /var/log/nginx/zb-mill.access.log

summary "Web server"
