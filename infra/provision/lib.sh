#!/usr/bin/env bash
# Shared helpers for infra/provision/steps/*.sh. Sourced by setup.sh, not run directly.

set -euo pipefail

# Constants used by the step files that setup.sh sources.
# shellcheck disable=SC2034
PROVISION_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INFRA_DIR="$(cd "$PROVISION_DIR/.." && pwd)"
# shellcheck disable=SC2034
REPO_DIR="$(cd "$INFRA_DIR/.." && pwd)"
# shellcheck disable=SC2034
FILES="$INFRA_DIR/files"
# shellcheck disable=SC2034
APP_BASE=/srv/zb-mill
# shellcheck disable=SC2034
APP_USER=zbmill
# shellcheck disable=SC2034
DEPLOY_USER=zbdeploy
# shellcheck disable=SC2034
BACKUP_USER=zbbackup
# shellcheck disable=SC2034
DB_NAME=zb_mill

say()  { printf '\n\033[1m== %s\033[0m\n' "$*"; }
info() { printf '   %s\n' "$*"; }
warn() { printf '   \033[33mWARNING:\033[0m %s\n' "$*"; }
die()  { printf '\n\033[31mSTOPPED:\033[0m %s\n' "$*" >&2; exit 1; }

# put <source> <destination> <mode> [owner:group]   (install with owner and mode)
put() {
    local src="$1" dst="$2" mode="$3" own="${4:-root:root}"
    install -D -m "$mode" -o "${own%%:*}" -g "${own##*:}" "$src" "$dst"
}

# render <template> <destination> <mode> [owner:group]   replaces __KEY__ with $KEY for known keys
render() {
    local src="$1" dst="$2" mode="$3" own="${4:-root:root}" tmp
    tmp="$(mktemp)"
    sed -e "s|__DOMAIN__|${DOMAIN}|g" \
        -e "s|__APP_BASE__|${APP_BASE}|g" \
        -e "s|__PHP_SOCK__|/run/php/zb-mill.sock|g" \
        -e "s|__ADMIN_USER__|${ADMIN_USER}|g" \
        -e "s|__ENV_NAME__|${ENV_NAME}|g" \
        "$src" > "$tmp"
    put "$tmp" "$dst" "$mode" "$own"
    rm -f "$tmp"
}

has_ipv6() { [ -e /proc/net/if_inet6 ] && ip -6 addr show scope global 2>/dev/null | grep -q inet6; }

load_server_conf() {
    local f="$1" k
    [ -r "$f" ] || die "settings file $f not found (copy infra/provision/server.conf.example)"
    # shellcheck disable=SC1090
    source "$f"
    for k in ENV_NAME DOMAIN LETSENCRYPT_EMAIL ADMIN_USER ADMIN_SSH_PUBLIC_KEY PHP_VERSION PG_VERSION RCLONE_REMOTE; do
        [ -n "${!k:-}" ] || die "$k is empty in $f"
    done
    case "$ENV_NAME" in production|staging) ;; *) die "ENV_NAME must be production or staging" ;; esac
    case "$DOMAIN" in *example.com) die "DOMAIN is still the example value" ;; esac
    case "$ADMIN_SSH_PUBLIC_KEY" in
        *replace-me*) die "ADMIN_SSH_PUBLIC_KEY is still the example value" ;;
        ssh-ed25519\ *|ssh-rsa\ *|ecdsa-sha2-*) ;;
        *) die "ADMIN_SSH_PUBLIC_KEY does not look like a public key" ;;
    esac
    [[ "$ADMIN_USER" =~ ^[a-z][a-z0-9_-]{2,31}$ ]] || die "ADMIN_USER is not a valid user name"
    SWAP_SIZE_GB="${SWAP_SIZE_GB:-2}"
    BACKUP_DAILY_HOUR_UTC="${BACKUP_DAILY_HOUR_UTC:-21}"
}

# check_recipients_file <file>: only age PUBLIC keys allowed (BR-OPS-02).
check_recipients_file() {
    local f="$1"
    [ -s "$f" ] || die "put the backup PUBLIC key in $f first (server-setup.md step 6)"
    if grep -q 'AGE-SECRET-KEY' "$f"; then
        die "$f contains a PRIVATE key. Delete it from this server now and use the public key (age1...)."
    fi
    if grep -vE '^(#|$)' "$f" | grep -qvE '^age1[0-9a-z]{58}$'; then
        die "$f has a line that is not an age public key"
    fi
    grep -qE '^age1[0-9a-z]{58}$' "$f" || die "$f has no age public key"
}
