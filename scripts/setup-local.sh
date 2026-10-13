#!/usr/bin/env bash
# One-time setup of your laptop: everything needed to pull the code from GitHub, run the app and
# run the tests. Claude builds in the cloud, so Claude Code on the laptop is optional.
# Made for Linux Mint 22.x (Ubuntu 24.04 base) and Linux Mint 23 (Ubuntu 26.04 base).
# Safe to run again: it only adds what is missing.
#
#   bash scripts/setup-local.sh           # check, install what is missing, create the databases
#   bash scripts/setup-local.sh --check   # only report; changes nothing
#
# Installs (with sudo, from the normal Ubuntu/Mint package sources): Git, GitHub CLI (gh), curl,
# unzip, Python 3, PHP with the extensions the app needs, Composer, PostgreSQL. Node.js 22 comes
# from NodeSource when your Node is missing or older than 22.
# Creates the TEST login zb_test (password local_test_only) and two local databases:
#   zb_mill_test  used by the automated tests (wiped on every test run)
#   zb_mill_dev   used when you click around in the app
# These are throwaway test values. Never put real client data or a real password on this laptop.
set -euo pipefail

CHECK_ONLY=0
[ "${1:-}" = "--check" ] && CHECK_ONLY=1

say()  { printf '%s\n' "$*"; }
good() { printf '  OK       %s\n' "$*"; }
bad()  { printf '  MISSING  %s\n' "$*"; MISSING=1; }
MISSING=0

# shellcheck disable=SC1091
. /etc/os-release
BASE="${UBUNTU_CODENAME:-${VERSION_CODENAME:-unknown}}"
say "System: ${PRETTY_NAME:-unknown} (Ubuntu base: $BASE)"
case "$BASE" in
  noble|resolute) ;;
  *)
    say "This script supports Linux Mint 22.x (Ubuntu 24.04 'noble') and Linux Mint 23 (Ubuntu 26.04 'resolute')."
    say "On older versions the packaged PHP is too old. Upgrade Linux Mint first, or install by hand:"
    say "PHP 8.3+, Composer 2, PostgreSQL 16+, Node.js 22, Python 3 (see docs/stack-baseline.md)."
    exit 1 ;;
esac

php_ok() {
  command -v php >/dev/null 2>&1 || return 1
  php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'
}
php_ext_missing() {
  local e out=""
  for e in pdo_pgsql pgsql mbstring xml dom curl zip intl bcmath gd gmp; do
    php -m 2>/dev/null | grep -qix "$e" || out="$out $e"
  done
  printf '%s' "${out# }"
}
node_ok() {
  command -v node >/dev/null 2>&1 || return 1
  [ "$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)" -ge 22 ]
}
db_ok() {
  PGPASSWORD=local_test_only psql -h 127.0.0.1 -U zb_test -d "$1" -tAc 'select 1' >/dev/null 2>&1
}

report() {
  MISSING=0
  say ""
  say "What is installed:"
  if command -v git >/dev/null 2>&1; then good "git $(git --version | awk '{print $3}')"; else bad "git"; fi
  if command -v gh >/dev/null 2>&1; then good "GitHub CLI (gh)"; else bad "GitHub CLI (gh)"; fi
  if command -v python3 >/dev/null 2>&1; then good "python3 (needed by the protection hook)"; else bad "python3"; fi
  if php_ok; then
    good "PHP $(php -r 'echo PHP_VERSION;')"
    local m; m="$(php_ext_missing)"
    if [ -z "$m" ]; then good "PHP extensions"; else bad "PHP extensions: $m"; fi
  else
    bad "PHP 8.3 or newer"
  fi
  if command -v composer >/dev/null 2>&1; then good "Composer"; else bad "Composer"; fi
  if node_ok; then good "Node.js $(node -v)"; else bad "Node.js 22 or newer"; fi
  if command -v psql >/dev/null 2>&1; then good "PostgreSQL client $(psql --version | awk '{print $3}')"; else bad "PostgreSQL"; fi
  if db_ok zb_mill_test; then good "test database zb_mill_test (login zb_test)"; else bad "test database zb_mill_test"; fi
  if db_ok zb_mill_dev; then good "dev database zb_mill_dev (login zb_test)"; else bad "dev database zb_mill_dev"; fi
  if command -v claude >/dev/null 2>&1 || [ -x "$HOME/.local/bin/claude" ]; then good "Claude Code (optional)"; else say "  optional Claude Code on this laptop (not needed: Claude builds in the cloud)"; fi
}

report
if [ "$CHECK_ONLY" = 1 ]; then
  [ "$MISSING" = 0 ] && say "" && say "Everything is ready."
  exit "$MISSING"
fi
if [ "$MISSING" = 0 ]; then
  say ""
  say "Everything is ready. Nothing to install."
  exit 0
fi

say ""
say "Installing what is missing. You will be asked for your password (sudo)."
sudo apt-get update -q
sudo apt-get install -y -q git gh curl unzip ca-certificates python3 \
  php-cli php-pgsql php-mbstring php-xml php-curl php-zip php-intl php-bcmath php-gd php-gmp \
  composer postgresql postgresql-client

if ! node_ok; then
  say "Installing Node.js 22 from NodeSource (the Node in the Mint/Ubuntu sources is too old)..."
  tmp="$(mktemp)"
  curl -fsSL https://deb.nodesource.com/setup_22.x -o "$tmp"
  sudo -E bash "$tmp"
  rm -f "$tmp"
  sudo apt-get install -y -q nodejs
fi

say "Starting PostgreSQL and creating the test login and databases..."
sudo systemctl enable --now postgresql
for _ in $(seq 1 15); do pg_isready -q -h 127.0.0.1 && break; sleep 1; done
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'zb_test'" | grep -q 1; then
  sudo -u postgres psql -q -c "CREATE ROLE zb_test LOGIN PASSWORD 'local_test_only' CREATEDB"
fi
for db in zb_mill_test zb_mill_dev; do
  if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname = '$db'" | grep -q 1; then
    sudo -u postgres createdb -O zb_test "$db"
  fi
done


report
say ""
if [ "$MISSING" = 0 ]; then
  say "Setup finished. Close this terminal, open a new one, and continue with START-HERE step 3."
else
  say "Some items are still missing (above). Read the messages, fix them, and run this script again."
  exit 1
fi
