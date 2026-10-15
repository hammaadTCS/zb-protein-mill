#!/bin/bash
# Runs at the start of every Claude Code session (.claude/settings.json, SessionStart hook).
# In CLOUD sessions only (CLAUDE_CODE_REMOTE=true) it prepares the fresh machine:
#   1. installs MariaDB 11.8, starts it and creates the TEST login and both databases;
#   2. once the app exists (task T0.1 onward): installs PHP and JS packages if they are missing,
#      and creates .env with a new APP_KEY if it is missing (.env is never committed).
#      PHP packages: the cloud GitHub proxy refuses GitHub zip downloads, so
#      scripts/cloud-composer-cache.sh first builds every zip from git into Composer's cache.
# On your laptop it only prints a short status; it never changes anything there.
# Test values only. Never put real or production credentials here.
# The test role is called zb_test on purpose: on the host there is ONE database user (zb_app) with
# all rights on its own database only (D-43) and it is never created with a test password.
cd "$(dirname "$0")/.." || exit 0

DB_USER="${DB_USERNAME:-zb_test}"
DB_PASS="${DB_PASSWORD:-local_test_only}"

branch="$(git symbolic-ref --short -q HEAD 2>/dev/null || echo "detached or unknown")"
echo "Project: ZB Poultry Protein Mill accounts system. Read CLAUDE.md first."
echo "Current git branch: $branch"
if [ "$branch" = main ] || [ "$branch" = master ]; then
  echo "You are on main: after the plan is approved, work on a task branch (never commit on main)."
fi

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  # Laptop: report only.
  if mariadb --no-defaults -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASS" zb_mill_test -e 'SELECT 1' >/dev/null 2>&1; then
    echo "MariaDB: ready (test database zb_mill_test)."
  else
    echo "MariaDB test database not reachable. The human runs: bash scripts/setup-local.sh"
  fi
  exit 0
fi

# ---- Cloud session ----
# MariaDB 11.8 is not in the base image, so install it from the MariaDB community repository
# (the same version of record as the host, the laptop and CI). The app enforces its own session
# settings in config/database.php, so nothing server-side is configured here (conventions §14a).
if ! command -v mariadbd >/dev/null 2>&1 && ! command -v mysqld >/dev/null 2>&1; then
  echo "Installing MariaDB 11.8..."
  curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup \
    | bash -s -- --mariadb-server-version="mariadb-11.8" >/dev/null 2>&1 || true
  apt-get update -q >/dev/null 2>&1 || true
  DEBIAN_FRONTEND=noninteractive apt-get install -y -q mariadb-server mariadb-client >/dev/null 2>&1 \
    || echo "WARNING: MariaDB could not be installed; report it and run this script again."
fi

service mariadb start >/dev/null 2>&1 || service mysql start >/dev/null 2>&1 || true
for _ in $(seq 1 30); do
  mariadb --no-defaults -u root -e 'SELECT 1' >/dev/null 2>&1 && break
  sleep 1
done

# One all-rights test user for both databases, matching the ONE-user shape of the host (D-43).
mariadb --no-defaults -u root <<SQL
CREATE DATABASE IF NOT EXISTS zb_mill_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS zb_mill_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`zb_mill_%\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

if mariadb --no-defaults -h 127.0.0.1 -P 3306 -u "$DB_USER" -p"$DB_PASS" zb_mill_test -e 'SELECT 1' >/dev/null 2>&1; then
  echo "MariaDB ready: databases zb_mill_test (tests) and zb_mill_dev, user ${DB_USER}."
else
  echo "WARNING: MariaDB is not ready. Run: service mariadb start, then this script again."
fi

if [ -f artisan ]; then
  if [ ! -f vendor/autoload.php ]; then
    echo "Installing PHP packages (git-built zips, then composer install)..."
    # Root user in a throwaway cloud machine: COMPOSER_ALLOW_SUPERUSER=1 keeps Composer plugins on
    # (Pest needs its plugin), as on the laptop and in CI.
    bash scripts/cloud-composer-cache.sh . || echo "WARNING: some package zips could not be built from git; see above."
    COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 composer install --no-progress --quiet || echo "WARNING: composer install failed; run it yourself and report the error."
  fi
  if [ -f package-lock.json ] && [ ! -d node_modules ]; then
    echo "Installing JS packages (npm ci)..."
    npm ci --no-audit --no-fund --loglevel=error || echo "WARNING: npm ci failed; run it yourself and report the error."
  fi
  if [ ! -f .env ] && [ -f .env.example ] && [ -f vendor/autoload.php ]; then
    cp .env.example .env && php artisan key:generate --no-interaction >/dev/null && echo "Created .env from .env.example with a new test APP_KEY."
  fi
fi
exit 0
