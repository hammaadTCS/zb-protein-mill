-- ZB Poultry Protein Mill: database roles and database (BR-SEC-12).
-- Run as the postgres superuser:  sudo -u postgres psql -v ON_ERROR_STOP=1 -v db_name=zb_mill -f 10-roles.sql
-- Safe to run again.
--
-- Roles (no passwords: they log in only through the Unix socket with peer authentication,
-- mapped from operating-system users in pg_ident.conf):
--   zb_migrator  owns the database and every table. Used only by migrations (OS user zbdeploy).
--   zb_app       the web application. Read, insert, update. Delete only on housekeeping tables.
--   zb_backup    read-only, for pg_dump (OS user zbbackup).

\set ON_ERROR_STOP on
SET client_min_messages = warning;

SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE INHERIT NOREPLICATION NOBYPASSRLS', r)
FROM unnest(ARRAY['zb_migrator', 'zb_app', 'zb_backup']) AS r
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = r)
\gexec

-- No role has a password. Peer authentication only.
ALTER ROLE zb_migrator PASSWORD NULL;
ALTER ROLE zb_app      PASSWORD NULL;
ALTER ROLE zb_backup   PASSWORD NULL;

-- Limits for the web application role.
ALTER ROLE zb_app CONNECTION LIMIT 40;
ALTER ROLE zb_app SET statement_timeout = '30s';
ALTER ROLE zb_app SET lock_timeout = '10s';
ALTER ROLE zb_app SET idle_in_transaction_session_timeout = '60s';

-- Backups may take longer, but must never hang forever.
ALTER ROLE zb_backup SET statement_timeout = '0';
ALTER ROLE zb_backup SET idle_in_transaction_session_timeout = '10min';

-- Read every table and sequence, change nothing (PostgreSQL 14+ predefined role).
GRANT pg_read_all_data TO zb_backup;

SELECT format('CREATE DATABASE %I OWNER zb_migrator ENCODING ''UTF8'' TEMPLATE template0', :'db_name')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'db_name')
\gexec

SELECT format('ALTER DATABASE %I OWNER TO zb_migrator', :'db_name') \gexec
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', :'db_name') \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO zb_migrator, zb_app, zb_backup', :'db_name') \gexec
SELECT format('ALTER DATABASE %I SET timezone TO ''UTC''', :'db_name') \gexec
