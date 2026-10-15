#!/usr/bin/env bash
# Step 50: nginx, Let's Encrypt certificate, security headers (BR-SEC-01, BR-SEC-14).
# Sourced by setup.sh. DNS for $DOMAIN must already point here.

install -d -m 0755 /var/www/letsencrypt
CERT="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"

put "$FILES/nginx/snippets/zb-mill-headers.conf" /etc/nginx/snippets/zb-mill-headers.conf 0644
put "$FILES/nginx/zz-zb-mill-http.conf" /etc/nginx/conf.d/zz-zb-mill-http.conf 0644
rm -f /etc/nginx/sites-enabled/default

if [ ! -f "$CERT" ]; then
    info "temporary HTTP-only site to obtain the certificate"
    cat > /etc/nginx/sites-available/zb-mill-acme.conf <<ACME
server {
    listen 80;
    server_name $DOMAIN;
    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 404; }
}
ACME
    has_ipv6 && sed -i 's/    listen 80;/    listen 80;\n    listen [::]:80;/' /etc/nginx/sites-available/zb-mill-acme.conf
    ln -sfn /etc/nginx/sites-available/zb-mill-acme.conf /etc/nginx/sites-enabled/zb-mill-acme.conf
    nginx -t && systemctl reload nginx
    certbot certonly --webroot -w /var/www/letsencrypt -d "$DOMAIN" \
        --email "$LETSENCRYPT_EMAIL" --agree-tos --no-eff-email --non-interactive
    rm -f /etc/nginx/sites-enabled/zb-mill-acme.conf /etc/nginx/sites-available/zb-mill-acme.conf
fi
put "$FILES/certbot/reload-nginx" /etc/letsencrypt/renewal-hooks/deploy/reload-nginx 0755

render "$FILES/nginx/zb-mill.conf.template" /etc/nginx/sites-available/zb-mill.conf 0644
if ! has_ipv6; then
    sed -i '/listen \[::\]/d' /etc/nginx/sites-available/zb-mill.conf
    info "no global IPv6 address: IPv6 listeners left out"
fi
ln -sfn /etc/nginx/sites-available/zb-mill.conf /etc/nginx/sites-enabled/zb-mill.conf
nginx -t || die "nginx configuration test failed"
systemctl reload nginx
systemctl is-enabled --quiet certbot.timer || warn "certbot.timer is not enabled: certificates will not renew"
info "https://$DOMAIN is configured (the app answers after the first deploy)"
