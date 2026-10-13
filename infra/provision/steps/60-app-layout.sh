#!/usr/bin/env bash
# Step 60: folders, environment file with a fresh APP_KEY, server commands, timers.
# Sourced by setup.sh.

install -d -m 0755 -o root -g root "$APP_BASE" "$APP_BASE/shared"
install -d -m 0755 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$APP_BASE/releases"
for d in storage storage/app storage/app/private storage/app/public storage/framework \
         storage/framework/cache storage/framework/sessions storage/framework/views storage/logs; do
    install -d -m 2770 -o "$APP_USER" -g "$APP_USER" "$APP_BASE/shared/$d"
done
touch "$APP_BASE/deploy.log"; chmod 0640 "$APP_BASE/deploy.log"

ENVF="$APP_BASE/shared/.env"
if [ ! -f "$ENVF" ]; then
    render "$REPO_DIR/.env.production.example" "$ENVF" 0640 "$DEPLOY_USER:$APP_USER"
    # Same format as "php artisan key:generate": base64 of 32 random bytes, made on this server.
    KEY="base64:$(openssl rand -base64 32)"
    sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" "$ENVF"
    unset KEY
    info "created $ENVF with a new APP_KEY"
    warn "copy APP_KEY to the two key holders now (docs/secrets.md): sudo grep ^APP_KEY= $ENVF"
else
    info "$ENVF already exists: left unchanged"
fi

install -d -m 0755 /etc/zb-mill /usr/local/lib/zb-mill
install -d -m 0750 -o root -g "$BACKUP_USER" /etc/zb-mill/secrets /etc/zb-mill/secrets/healthchecks
install -d -m 0700 -o "$BACKUP_USER" -g "$BACKUP_USER" /var/lib/zb-mill/backup
install -d -m 2750 -o "$BACKUP_USER" -g "$APP_USER" /var/lib/zb-mill/status
cat > /etc/zb-mill/zb-mill.conf <<CONF
# Written by infra/provision/steps/60-app-layout.sh. Read by the zb-* commands. No secrets here.
ENV_NAME=$ENV_NAME
DOMAIN=$DOMAIN
APP_BASE=$APP_BASE
APP_USER=$APP_USER
DEPLOY_USER=$DEPLOY_USER
BACKUP_USER=$BACKUP_USER
DB_NAME=$DB_NAME
PHP_VERSION=$PHP_VERSION
KEEP_RELEASES=5
BACKUP_WORK_DIR=/var/lib/zb-mill/backup
STATUS_DIR=/var/lib/zb-mill/status
BACKUP_RECIPIENTS_FILE=/etc/zb-mill/backup-recipients.txt
RCLONE_CONFIG_FILE=/etc/zb-mill/secrets/rclone.conf
RCLONE_REMOTE=$RCLONE_REMOTE
UPLOADS_DIR=$APP_BASE/shared/storage/app/private
HC_DIR=/etc/zb-mill/secrets/healthchecks
BACKUP_DAILY_HOUR_UTC=$BACKUP_DAILY_HOUR_UTC
CONF
chmod 0644 /etc/zb-mill/zb-mill.conf

put "$INFRA_DIR/bin/zb-lib.sh" /usr/local/lib/zb-mill/zb-lib.sh 0644
for c in zb-deploy-release zb-backup-db zb-backup-files zb-restore zb-backup-verify-offsite zb-health-check; do
    put "$INFRA_DIR/bin/$c" "/usr/local/sbin/$c" 0755
done

for u in zb-mill-scheduler zb-mill-backup-db zb-mill-backup-files zb-mill-health; do
    put "$FILES/systemd/$u.service" "/etc/systemd/system/$u.service" 0644
    put "$FILES/systemd/$u.timer" "/etc/systemd/system/$u.timer" 0644
done
systemctl daemon-reload
systemctl enable --now zb-mill-scheduler.timer zb-mill-health.timer >/dev/null
info "scheduler and health timers on; backup timers start in step 70"
