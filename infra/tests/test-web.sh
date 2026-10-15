#!/usr/bin/env bash
# The VPS nginx site and PHP-FPM pool are retired (ADR-0013): shared hosting has no root, no nginx
# and no pool to configure, and the security headers are set by the application (D-8). This is a
# STRUCTURAL check that those files moved to infra/history/ and that nothing live still reaches for
# nginx or PHP-FPM; the header values themselves are the application's, proven by tests/Feature.
source "$(dirname "$0")/lib.sh"
REPO="$(cd "$INFRA_DIR/.." && pwd)"

echo "Web server (retired nginx/PHP-FPM files; headers are app-side now)"

# ---------- retired files are kept, history only
expect_fail "the old infra/files/nginx/ folder is gone" test -e "$INFRA_DIR/files/nginx"
expect_ok "the nginx site template is kept for history" test -f "$INFRA_DIR/history/files/nginx/zb-mill.conf.template"
expect_ok "the security-header snippet is kept for history" test -f "$INFRA_DIR/history/files/nginx/snippets/zb-mill-headers.conf"
expect_ok "the HTTP->HTTPS snippet is kept for history" test -f "$INFRA_DIR/history/files/nginx/zz-zb-mill-http.conf"
expect_ok "the PHP-FPM pool is kept for history" test -f "$INFRA_DIR/history/files/php/fpm-pool-zb-mill.conf"

# ---------- nothing live still configures nginx or PHP-FPM
for marker in '/etc/nginx' 'nginx -s' 'php-fpm' 'systemctl' '/etc/php'; do
    expect_eq "no '$marker' in the live hostinger scripts" "" \
        bash -c "grep -rn --exclude=README.md -F -- '$marker' '$INFRA_DIR/hostinger' || true"
done

# ---------- the subdomain document root is current/public
expect_ok "the hostinger README points the document root at current/public" \
    grep -q 'current/public' "$INFRA_DIR/hostinger/README.md"
expect_ok "the app's front controller still sits in public/" test -f "$REPO/public/index.php"
expect_ok "the app's public/.htaccess is still there" test -f "$REPO/public/.htaccess"

# ---------- the security headers moved into the application
expect_ok "the application sets the security headers itself" \
    test -f "$REPO/app/Http/Middleware/ContentSecurityPolicy.php"

echo "  note  this file is a STRUCTURAL check only: header values, /up, .env and upload behaviour"
echo "        are the application's and the host's, proven by the application tests and manually."

summary "Web server"
