#!/usr/bin/env bash
# Shared functions for the zb-* shared-hosting commands. Sourced, never run directly.
#
# On Hostinger there is no root and no /etc, so the settings file lives in the application
# folder (outside public_html): by default ~/accounts/zb-mill.conf. Set ZB_CONF to override.
# The account user owns everything; there is no sudo and no service to reload.

ZB_CONF="${ZB_CONF:-${HOME:-$PWD}/accounts/zb-mill.conf}"

zb_log()  { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
zb_die()  { zb_log "ERROR: $*"; exit 1; }

# Load the settings file (KEY=value lines) and check that every named key is set.
zb_load_config() {
    [ -r "$ZB_CONF" ] || zb_die "config file $ZB_CONF not found or not readable"
    # shellcheck disable=SC1090
    source "$ZB_CONF"
    local k
    for k in "$@"; do
        [ -n "${!k:-}" ] || zb_die "$k is not set in $ZB_CONF"
    done
}

zb_json_escape() {
    local s="$1"
    s="${s//\\/\\\\}"; s="${s//\"/\\\"}"; s="${s//$'\n'/ }"; s="${s//$'\r'/ }"; s="${s//$'\t'/ }"
    printf '%s' "$s"
}

# zb_hc_ping <check-name> [start|fail] [message]
# Pings a dead-man monitor (healthchecks.io style URL). The URL is read from
# $HC_DIR/<check-name>.url, or from $MONITOR_URL for the "health" check. A missing URL only
# logs a warning: monitoring is configured at go-live (O-33).
zb_hc_ping() {
    local name="$1" kind="${2:-}" msg="${3:-}" url_file url
    url_file="${HC_DIR:-${HOME:-$PWD}/accounts/secrets/monitors}/$name.url"
    if [ -n "${MONITOR_URL:-}" ] && [ "$name" = health ]; then
        url="$MONITOR_URL"
    elif [ -r "$url_file" ]; then
        url="$(tr -d '[:space:]' < "$url_file")"
    else
        zb_log "no monitor URL for $name (create $url_file)"; return 0
    fi
    [ -n "$kind" ] && url="$url/$kind"
    curl -fsS -m 10 --retry 3 -o /dev/null --data-raw "${msg:0:9000}" "$url" \
        || zb_log "monitor ping to $name failed (the check itself is not affected)"
}

# age_minutes <file>  -> minutes since the file was last written (999999 when missing).
age_minutes() {
    local f="$1"
    [ -r "$f" ] || { echo 999999; return; }
    echo $(( ( $(date +%s) - $(stat -c %Y "$f") ) / 60 ))
}
