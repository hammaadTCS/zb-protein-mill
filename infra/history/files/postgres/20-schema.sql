-- ZB Poultry Protein Mill: schema rights (BR-SEC-12).
-- Run as the postgres superuser, connected to the application database:
--   sudo -u postgres psql -v ON_ERROR_STOP=1 -d zb_mill -f 20-schema.sql
-- Safe to run again.

\set ON_ERROR_STOP on

-- Only zb_migrator may create objects. Nobody else may even use the schema unless granted.
ALTER SCHEMA public OWNER TO zb_migrator;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO zb_app, zb_backup;

-- Tables and sequences created later by zb_migrator (migrations) are usable by the app at once.
-- DELETE is never part of the default: it is granted table by table in 30-app-grants.sql.
ALTER DEFAULT PRIVILEGES FOR ROLE zb_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE ON TABLES TO zb_app;
ALTER DEFAULT PRIVILEGES FOR ROLE zb_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO zb_app;

-- Functions created by migrations (trigger functions) must not be callable by everyone.
ALTER DEFAULT PRIVILEGES FOR ROLE zb_migrator IN SCHEMA public
    REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;
