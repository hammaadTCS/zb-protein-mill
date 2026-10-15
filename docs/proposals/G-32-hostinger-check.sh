#!/usr/bin/env bash
# G-32: one-off check of the Hostinger "Unlimited" (shared) hosting account
# before the system is converted to run on it. Proposal only: nothing in the
# app uses this file.
#
# Run it over SSH on the hosting account, not on your laptop:
#   bash G-32-hostinger-check.sh
#
# Before you run it, create an EMPTY test database in hPanel (Databases).
# The script asks for its name, user and password. The password is not shown,
# not saved and not printed.
#
# It changes nothing except one temporary table in that test database and one
# temporary folder in your home folder; both are removed at the end.
# Paste the whole output into the Claude session. Do not commit the output:
# it contains account and folder names.
set -u

ok()   { printf '  OK    %s\n' "$*"; }
bad()  { printf '  FAIL  %s\n' "$*"; }
info() { printf '  INFO  %s\n' "$*"; }

echo "== 1. PHP (command line, as the scheduled jobs will use it)"
PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ]; then
  bad "php not found"
  exit 1
fi
info "php binary: $PHP_BIN"
info "$("$PHP_BIN" -r 'echo "version ", PHP_VERSION;')"
if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
  ok "PHP 8.3 or newer"
else
  bad "PHP older than 8.3 (pick 8.3 or newer in hPanel, then run again)"
fi

for ext in ctype dom fileinfo filter gd hash iconv json libxml mbstring openssl pcre pdo_mysql session simplexml tokenizer xml xmlwriter; do
  if "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
    ok "extension $ext"
  else
    bad "extension $ext missing (required)"
  fi
done
for ext in bcmath curl sodium zip; do
  if "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
    ok "extension $ext"
  else
    info "extension $ext missing (wanted, not required)"
  fi
done

echo "== 2. PHP limits and blocked functions"
"$PHP_BIN" -r 'foreach (["memory_limit", "max_execution_time", "upload_max_filesize", "post_max_size"] as $k) { echo "  INFO  $k = ", ini_get($k), "\n"; }'
DISABLED="$("$PHP_BIN" -r 'echo str_replace(" ", "", (string) ini_get("disable_functions"));')"
for fn in proc_open proc_get_status symlink; do
  case ",$DISABLED," in
    *",$fn,"*) bad "PHP function $fn is blocked (the scheduler or deploy needs it)" ;;
    *) ok "PHP function $fn allowed" ;;
  esac
done

echo "== 3. Folders"
info "home folder: $HOME"
df -h "$HOME" 2>/dev/null | tail -1 | sed 's/^/  INFO  disk: /'
for d in "$HOME"/domains/*/public_html "$HOME"/public_html; do
  [ -e "$d" ] || continue
  if [ -L "$d" ]; then
    info "site folder (already a link): $d"
  else
    info "site folder: $d"
  fi
done
TMPD="$(mktemp -d "$HOME/zbcheck.XXXXXX")"
mkdir "$TMPD/target"
if ln -s "$TMPD/target" "$TMPD/link" 2>/dev/null && [ -L "$TMPD/link" ]; then
  ok "can create folder links (needed to switch releases)"
else
  bad "cannot create folder links"
fi
rm -rf "$TMPD"

echo "== 4. Tools"
for t in git composer mysqldump mysql unzip tar gzip curl crontab; do
  if command -v "$t" >/dev/null 2>&1; then
    ok "$t: $(command -v "$t")"
  else
    info "$t: not found"
  fi
done
if curl -sS -o /dev/null --max-time 10 https://github.com 2>/dev/null; then
  ok "outgoing HTTPS works"
else
  info "outgoing HTTPS blocked or curl missing"
fi

echo "== 5. Database (the EMPTY test database you created in hPanel)"
read -r -p "  Database name: " DB_NAME
read -r -p "  Database user: " DB_USER
read -r -s -p "  Database password (not shown, not saved): " DB_PASS
echo
DB_HOST="${DB_HOST:-localhost}"
export DB_NAME DB_USER DB_PASS DB_HOST

"$PHP_BIN" <<'PHP'
<?php
function ok(string $m): void { echo "  OK    $m\n"; }
function bad(string $m): void { echo "  FAIL  $m\n"; }
function info(string $m): void { echo "  INFO  $m\n"; }
function reason(PDOException $e): string { return ($e->errorInfo[1] ?? $e->getCode()) . ' ' . ($e->errorInfo[2] ?? ''); }

try {
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
        (string) getenv('DB_USER'),
        (string) getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
} catch (PDOException $e) {
    bad('cannot connect: ' . reason($e));
    exit(1);
}
ok('connected');
info('server version: ' . $pdo->query('SELECT VERSION()')->fetchColumn());
info('sql_mode: ' . $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn());
info('default engine: ' . $pdo->query('SELECT @@default_storage_engine')->fetchColumn());
info('time zone: ' . $pdo->query('SELECT @@time_zone')->fetchColumn());
foreach ($pdo->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
    info('rights: ' . preg_replace('/ IDENTIFIED BY .*/i', '', (string) $grant));
}

$t = 'zb_check_' . bin2hex(random_bytes(3));
try {
    $pdo->exec("CREATE TABLE $t (id INT PRIMARY KEY, amount DECIMAL(20,2) NOT NULL, CONSTRAINT {$t}_positive CHECK (amount > 0)) ENGINE=InnoDB");
    ok('create table');

    try {
        $pdo->exec("INSERT INTO $t VALUES (1, -5)");
        bad('CHECK rules are not enforced');
        $pdo->exec("DELETE FROM $t");
    } catch (PDOException $e) {
        ok('CHECK rules enforced');
    }

    $pdo->exec("INSERT INTO $t VALUES (1, 10.50)");
    $v = $pdo->query("SELECT amount FROM $t WHERE id = 1")->fetchColumn();
    $v === '10.50' ? ok('money comes back as exact text (10.50)') : info('money comes back as ' . var_export($v, true));

    try {
        $pdo->exec("CREATE TRIGGER {$t}_no_update BEFORE UPDATE ON $t FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'blocked'");
        ok('can create triggers');
        try {
            $pdo->exec("UPDATE $t SET amount = 11 WHERE id = 1");
            bad('trigger was created but did not block the change');
        } catch (PDOException $e) {
            ok('trigger blocks changes');
        }
    } catch (PDOException $e) {
        bad('cannot create triggers: ' . reason($e));
    }

    $pdo->beginTransaction();
    $pdo->query("SELECT * FROM $t WHERE id = 1 FOR UPDATE")->fetchAll();
    $pdo->rollBack();
    ok('transactions and row locks');

    $got = $pdo->query("SELECT GET_LOCK('zb_check', 2)")->fetchColumn();
    $pdo->query("SELECT RELEASE_LOCK('zb_check')")->fetchAll();
    (string) $got === '1' ? ok('named locks (GET_LOCK)') : bad('named locks not available');

    $pdo->query("WITH x AS (SELECT amount FROM $t) SELECT SUM(amount) OVER () FROM x")->fetchAll();
    ok('WITH queries and window functions');
} catch (PDOException $e) {
    bad('database test stopped: ' . reason($e));
} finally {
    try {
        $pdo->exec("DROP TABLE IF EXISTS $t");
        info('test table removed');
    } catch (PDOException $e) {
        bad("could not remove test table $t: remove it in phpMyAdmin");
    }
}
PHP

echo "== Done. Also answer the hPanel questions in the proposal, then paste everything into the session."
