#!/bin/bash
# Runs at the start of every Claude Code session (.claude/settings.json, SessionStart hook).
# In CLOUD sessions only (CLAUDE_CODE_REMOTE=true) it prepares the fresh machine:
#   1. starts PostgreSQL and creates the TEST login and both databases;
#   2. once the app exists (task T0.1 onward): installs PHP and JS packages if they are missing,
#      and creates .env with a new APP_KEY if it is missing (.env is never committed).
# On your laptop it only prints a short status; it never changes anything there.
# Test values only. Never put real or production credentials here.
# The test role is called zb_test on purpose: zb_app, zb_migrator and zb_backup are the production
# roles (infra/files/postgres) and are never created with a password.
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
  if PGPASSWORD="$DB_PASS" psql -h 127.0.0.1 -U "$DB_USER" -d zb_mill_test -tAc 'select 1' >/dev/null 2>&1; then
    echo "PostgreSQL: ready (test database zb_mill_test)."
  else
    echo "PostgreSQL test database not reachable. The human runs: bash scripts/setup-local.sh"
  fi
  exit 0
fi

# ---- Cloud session ----
service postgresql start >/dev/null 2>&1 || true
for _ in $(seq 1 20); do
  su postgres -c "pg_isready -q" && break
  sleep 1
done

su postgres -c "psql -tAc \"SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'\"" | grep -q 1 \
  || su postgres -c "psql -c \"CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASS}' CREATEDB\"" >/dev/null 2>&1 || true
for db in zb_mill_test zb_mill_dev; do
  su postgres -c "psql -tAc \"SELECT 1 FROM pg_database WHERE datname='${db}'\"" | grep -q 1 \
    || su postgres -c "createdb -O ${DB_USER} ${db}" >/dev/null 2>&1 || true
done
if PGPASSWORD="$DB_PASS" psql -h 127.0.0.1 -U "$DB_USER" -d zb_mill_test -tAc 'select 1' >/dev/null 2>&1; then
  echo "PostgreSQL ready: databases zb_mill_test (tests) and zb_mill_dev, user ${DB_USER}."
else
  echo "WARNING: PostgreSQL is not ready. Run: service postgresql start, then this script again."
fi

if [ -f artisan ]; then
  if [ ! -f vendor/autoload.php ]; then
    echo "Installing PHP packages (composer install)..."
    COMPOSER_NO_INTERACTION=1 composer install --no-progress --quiet || echo "WARNING: composer install failed; run it yourself and report the error."
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
