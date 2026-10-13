#!/usr/bin/env bash
# Step 20: your admin login, the three service users, SSH hardening (BR-SEC-10, BR-SEC-12).
# Sourced by setup.sh.

if ! id "$ADMIN_USER" >/dev/null 2>&1; then
    info "creating admin user $ADMIN_USER"
    adduser --disabled-password --gecos "" "$ADMIN_USER" >/dev/null
fi
usermod -aG sudo "$ADMIN_USER"
install -d -m 0700 -o "$ADMIN_USER" -g "$ADMIN_USER" "/home/$ADMIN_USER/.ssh"
AK="/home/$ADMIN_USER/.ssh/authorized_keys"
touch "$AK"
grep -qxF "$ADMIN_SSH_PUBLIC_KEY" "$AK" || echo "$ADMIN_SSH_PUBLIC_KEY" >> "$AK"
chown "$ADMIN_USER:$ADMIN_USER" "$AK"; chmod 0600 "$AK"
if ! passwd -S "$ADMIN_USER" | awk '{print $2}' | grep -q '^P'; then
    warn "$ADMIN_USER has no password, so sudo will not work. Set one now: sudo passwd $ADMIN_USER"
fi

for u in "$APP_USER" "$DEPLOY_USER" "$BACKUP_USER"; do
    if ! id "$u" >/dev/null 2>&1; then
        useradd --system --user-group --home-dir /nonexistent --no-create-home --shell /usr/sbin/nologin "$u"
        info "service user $u created"
    fi
done
# Deploy and backup users may read/write the app's shared files through the zbmill group.
usermod -aG "$APP_USER" "$DEPLOY_USER"
usermod -aG "$APP_USER" "$BACKUP_USER"

[ -s "$AK" ] || die "no SSH key for $ADMIN_USER: refusing to switch off password login"
render "$FILES/ssh/10-zb-mill.conf" /etc/ssh/sshd_config.d/10-zb-mill.conf 0644
sshd -t || die "SSH configuration test failed; nothing was reloaded"
systemctl reload ssh
warn "SSH now accepts only $ADMIN_USER with a key. KEEP THIS SESSION OPEN and test a new login from your computer: ssh $ADMIN_USER@$DOMAIN"
