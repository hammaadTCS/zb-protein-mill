#!/usr/bin/env bash
# Shared functions for the zb-* server commands. Sourced, never run directly.
# Installed to /usr/local/lib/zb-mill/zb-lib.sh by infra/provision/steps/60-app-layout.sh.

ZB_CONF="${ZB_CONF:-/etc/zb-mill/zb-mill.conf}"

zb_log()  { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
zb_die()  { zb_log "ERROR: $*"; exit 1; }

# Load /etc/zb-mill/zb-mill.conf (KEY=value lines, owned by root) and check required keys.
zb_load_config() {
    [ -r "$ZB_CONF" ] || zb_die "config file $ZB_CONF not found or not readable"
    # shellcheck disable=SC1090
    source "$ZB_CONF"
    local k
    for k in "$@"; do
        [ -n "${!k:-}" ] || zb_die "$k is not set in $ZB_CONF"
    done
}

zb_require_root() { [ "$(id -u)" -eq 0 ] || zb_die "run with sudo"; }

zb_json_escape() {
    local s="$1"
    s="${s//\\/\\\\}"; s="${s//\"/\\\"}"; s="${s//$'\n'/ }"; s="${s//$'\r'/ }"; s="${s//$'\t'/ }"
    printf '%s' "$s"
}

# zb_write_status <name> <json-body>   -> $STATUS_DIR/<name>.json, atomically, mode 0640
zb_write_status() {
    local name="$1" body="$2" tmp
    if [ -z "${STATUS_DIR:-}" ] || [ ! -d "$STATUS_DIR" ]; then
        zb_log "status dir missing, status not written"
        return 0
    fi
    tmp="$(mktemp "$STATUS_DIR/.$name.XXXXXX")"
    printf '%s\n' "$body" > "$tmp"
    # Same group as the folder (zbmill), so the web app can read it; nobody else can.
    chgrp "$(stat -c %G "$STATUS_DIR")" "$tmp" 2>/dev/null || true
    chmod 0640 "$tmp"
    mv -f "$tmp" "$STATUS_DIR/$name.json"
}

# zb_hc_ping <check-name> [start|fail|log] [message]
# Pings a dead-man monitor (healthchecks.io style URL kept in $HC_DIR/<check-name>.url).
# A missing URL file only logs a warning: monitoring is configured at go-live (O-33).
zb_hc_ping() {
    local name="$1" kind="${2:-}" msg="${3:-}" url_file url
    url_file="${HC_DIR:-/etc/zb-mill/secrets/healthchecks}/$name.url"
    if [ ! -r "$url_file" ]; then zb_log "no monitor URL for $name (create $url_file)"; return 0; fi
    url="$(tr -d '[:space:]' < "$url_file")"
    [ -n "$kind" ] && url="$url/$kind"
    curl -fsS -m 10 --retry 3 -o /dev/null --data-raw "${msg:0:9000}" "$url" \
        || zb_log "monitor ping to $name failed (backup itself is not affected)"
}

zb_rclone() {
    # shellcheck disable=SC2086
    "${RCLONE_BIN:-rclone}" --config "$RCLONE_CONFIG_FILE" ${RCLONE_EXTRA_ARGS:-} "$@"
}

# Upload one file. Never overwrites by design: every backup file name contains a timestamp.
zb_upload() {
    local src="$1" dest="$2"
    zb_rclone copyto --no-check-dest --retries 3 --low-level-retries 10 "$src" "$dest"
}

zb_check_age_file() {
    # An age file starts with the line "age-encryption.org/v1".
    [ "$(head -c 21 "$1")" = "age-encryption.org/v1" ] || zb_die "$1 is not an age-encrypted file"
}
