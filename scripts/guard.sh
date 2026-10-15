#!/bin/bash
# Project guard: fails when code breaks a rule in docs/conventions.md §13 (forbidden)
# or §14 (SQL and the database).
# Runs in CI (from task T0.3) and locally: bash scripts/guard.sh
# A line may bypass one check only with the comment "guard:allow <reason>"; every such line is reviewed by the human.

set -u
FAIL=0

check() {
  # $1 = rule text, $2 = extended regex, $3.. = paths (missing paths are skipped)
  local rule="$1" pattern="$2"; shift 2
  local paths=()
  for p in "$@"; do [ -e "$p" ] && paths+=("$p"); done
  [ ${#paths[@]} -eq 0 ] && return 0
  local hits
  hits=$(grep -rnE --include='*.php' --include='*.ts' --include='*.tsx' --include='*.blade.php' -e "$pattern" "${paths[@]}" 2>/dev/null \
    | grep -v 'guard:allow' \
    | grep -vE 'database/migrations/0001_01_01_|_create_permission_tables\.php' || true)
  if [ -n "$hits" ]; then
    echo "GUARD FAIL: $rule"
    echo "$hits" | sed 's/^/    /'
    FAIL=1
  fi
}

# 1. No floats for money, kg or rate (conventions §3)
check "float/double column in a migration" '->(unsigned)?([Ff]loat|[Dd]ouble)\(' database/migrations
check "float cast or floatval in PHP" '\((float|double)\)|floatval\(|=>[[:space:]]*'"'"'(float|double|real)'"'" app database
check "parseFloat in frontend code (use lib/money.ts and lib/format.ts)" 'parseFloat\(' resources/js

# 2. Nothing is deleted (CLAUDE.md rule 3)
check "delete/forceDelete/destroy/truncate in app code" '->(delete|forceDelete|truncate)\(\)|::destroy\(' app
check "SoftDeletes used" 'SoftDeletes' app
check "cascading delete in a migration" 'cascadeOnDelete\(\)|onDelete\(['"'"'"]cascade|nullOnDelete\(\)|restrictOnDelete\(\)' database/migrations

# 3. Only PostingService writes journals (CLAUDE.md rule 2)
JOURNAL_HITS=$( [ -d app ] && grep -rnE 'JournalEntry::(create|insert|query\(\)->insert)|JournalLine::(create|insert)|DB::table\(['"'"'"]journal_(entries|lines)' app 2>/dev/null \
  | grep -v '^app/Domain/Ledger/' | grep -v 'guard:allow' || true)
if [ -n "$JOURNAL_HITS" ]; then echo "GUARD FAIL: journal rows written outside app/Domain/Ledger"; echo "$JOURNAL_HITS" | sed 's/^/    /'; FAIL=1; fi

# 4. Interface words (docs/glossary.md §5)
check "forbidden interface word (glossary §5)" '["'"'"'>][^"'"'"'<]*\b(Delete|Profit|Loss|Invoice|Customer|Vendor|Cheque|Inventory|Accounts payable|Accounts receivable)\b' resources/js/pages resources/js/components/app resources/views/pdf

# 5. Logo only on login and PDFs (docs/brand.md)
LOGO_HITS=$( [ -d resources ] && grep -rnE 'zb-logo' resources 2>/dev/null \
  | grep -vE 'resources/js/pages/auth/login\.tsx|resources/views/pdf/' | grep -v 'guard:allow' || true)
if [ -n "$LOGO_HITS" ]; then echo "GUARD FAIL: logo used outside the login page and PDFs"; echo "$LOGO_HITS" | sed 's/^/    /'; FAIL=1; fi

# 6. Tests must run on MariaDB (CLAUDE.md, Tests)
if [ -f phpunit.xml ] && grep -qiE 'sqlite|:memory:' phpunit.xml; then
  echo "GUARD FAIL: phpunit.xml mentions sqlite or :memory: (tests must use MariaDB)"; FAIL=1
fi

# 7. Project rule files must never be git-ignored (the starter kit's .gitignore ignores them)
if [ -f .gitignore ] && grep -qxE '/?(CLAUDE\.md|\.claude/?)' .gitignore; then
  echo "GUARD FAIL: .gitignore ignores CLAUDE.md or .claude/ (remove those lines; see card T0.1 step 7)"; FAIL=1
fi

# 8. No PostgreSQL-only SQL (conventions §14b): every query runs on MariaDB
# The cast list is only the PostgreSQL type names that cannot be a PHP static call here;
# date/time/money/uuid are left out (they appear as Today::date(), Decimal::money(), Str::uuid()).
# "::text" is only a cast when it is NOT followed by "(" — self::text(...) is a PHP helper.
PG_SQL_HITS=$(grep -rniE --include='*.php' \
  -e '::(numeric|decimal|varchar|char|boolean|bool|bigint|smallint|integer|int|double|real|float|timestamp|timestamptz|jsonb|json|interval|bytea|inet|cidr|serial)\b' \
  -e '::text([^a-z0-9_(]|$)' \
  -e '\bilike\b' \
  -e 'FILTER[[:space:]]*\([[:space:]]*WHERE' \
  -e '\bpg_[a-z_]+[[:space:]]*\(' \
  -e '\bto_char[[:space:]]*\(' \
  -e 'ON[[:space:]]+CONFLICT[[:space:]]*(\(|DO\b)' \
  -e 'SET[[:space:]]+CONSTRAINTS[[:space:]]+[a-z0-9_]' \
  app database 2>/dev/null \
  | grep -v 'guard:allow' \
  | grep -vE 'database/migrations/0001_01_01_|_create_permission_tables\.php' || true)
if [ -n "$PG_SQL_HITS" ]; then
  echo "GUARD FAIL: PostgreSQL-only SQL (conventions §14b)"
  echo "$PG_SQL_HITS" | sed 's/^/    /'
  FAIL=1
fi

if [ $FAIL -ne 0 ]; then
  echo ""
  echo "Guard failed. Fix the code. Do not weaken this script (it is protected)."
  exit 1
fi
echo "Guard passed."
