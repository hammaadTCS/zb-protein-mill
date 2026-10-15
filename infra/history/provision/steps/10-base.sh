#!/usr/bin/env bash
# Step 10: operating system, packages, time, swap, firewall, automatic security updates (BR-SEC-10).
# Sourced by setup.sh.

. /etc/os-release
if [ "${VERSION_ID:-}" != "26.04" ]; then
    [ "${ZB_ALLOW_OTHER_UBUNTU:-0}" = 1 ] || die "this is Ubuntu ${VERSION_ID:-?}; docs/stack-baseline.md requires 26.04 (set ZB_ALLOW_OTHER_UBUNTU=1 only for a test machine)"
    warn "not Ubuntu 26.04: package versions will differ from docs/stack-baseline.md"
fi

info "time zone UTC (the app shows Pakistan time itself)"
timedatectl set-timezone UTC

export DEBIAN_FRONTEND=noninteractive
info "updating packages"
apt-get update -q
apt-get -y -q -o Dpkg::Options::=--force-confold full-upgrade
apt-get -y -q install \
    nginx "postgresql-$PG_VERSION" postgresql-client-common \
    "php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-pgsql" "php$PHP_VERSION-intl" \
    "php$PHP_VERSION-bcmath" "php$PHP_VERSION-gmp" "php$PHP_VERSION-mbstring" "php$PHP_VERSION-xml" \
    "php$PHP_VERSION-curl" "php$PHP_VERSION-zip" "php$PHP_VERSION-gd" "php$PHP_VERSION-readline" \
    certbot age rclone ufw unattended-upgrades logrotate curl ca-certificates openssl

info "automatic security updates, reboot at 22:30 UTC if needed"
put "$FILES/apt/20auto-upgrades" /etc/apt/apt.conf.d/20auto-upgrades 0644
put "$FILES/apt/52zb-mill-unattended-upgrades" /etc/apt/apt.conf.d/52zb-mill-unattended-upgrades 0644
systemctl enable --now unattended-upgrades.service >/dev/null

if [ "$SWAP_SIZE_GB" -gt 0 ] && ! swapon --show=NAME --noheadings | grep -q .; then
    info "swap file ${SWAP_SIZE_GB} GB"
    fallocate -l "${SWAP_SIZE_GB}G" /swapfile
    chmod 0600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl -q vm.swappiness=10
    echo 'vm.swappiness=10' > /etc/sysctl.d/90-zb-mill.conf
fi

info "firewall: only SSH (rate-limited), HTTP and HTTPS"
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw limit OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
ufw status verbose | sed 's/^/   /'

if [ "$(timedatectl show -p NTPSynchronized --value)" != yes ]; then
    warn "clock is not synchronised yet. Check: timedatectl timesync-status"
fi
