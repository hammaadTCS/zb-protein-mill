#!/usr/bin/env bash
# Step 40: PHP-FPM pool for the app (runs as zbmill), PHP settings, file permissions mask.
# Sourced by setup.sh.

PHPETC="/etc/php/$PHP_VERSION/fpm"
[ -d "$PHPETC" ] || die "PHP $PHP_VERSION FPM not installed"

put "$FILES/php/fpm-pool-zb-mill.conf" "$PHPETC/pool.d/zb-mill.conf" 0644
put "$FILES/php/99-zb-mill.ini" "$PHPETC/conf.d/99-zb-mill.ini" 0644
if [ -f "$PHPETC/pool.d/www.conf" ]; then
    mv "$PHPETC/pool.d/www.conf" "$PHPETC/pool.d/www.conf.disabled"
    info "default www pool disabled"
fi
put "$FILES/systemd/php-fpm-zb-mill.conf" "/etc/systemd/system/php$PHP_VERSION-fpm.service.d/zb-mill.conf" 0644
install -d -m 2770 -o "$APP_USER" -g "$APP_USER" /var/log/zb-mill
put "$FILES/logrotate/zb-mill" /etc/logrotate.d/zb-mill 0644

"php-fpm$PHP_VERSION" -t >/dev/null 2>&1 || die "php-fpm configuration test failed: php-fpm$PHP_VERSION -t"
systemctl daemon-reload
systemctl restart "php$PHP_VERSION-fpm"
info "PHP-FPM pool zb-mill listening on /run/php/zb-mill.sock"
