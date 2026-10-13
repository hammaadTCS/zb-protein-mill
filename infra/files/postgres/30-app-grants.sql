-- ZB Poultry Protein Mill: table rights for the web application role (BR-SEC-12).
-- Run as zb_migrator (the table owner) after EVERY migration. zb-deploy-release does this.
--   psql -v ON_ERROR_STOP=1 -d zb_mill -f 30-app-grants.sql        (as OS user zbdeploy)
-- Safe to run again. Works whether or not a table exists yet.

\set ON_ERROR_STOP on

GRANT USAGE ON SCHEMA public TO zb_app;

-- 1. Baseline for every table: read, add, change. Nothing else.
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO zb_app;
REVOKE DELETE, TRUNCATE, REFERENCES, TRIGGER ON ALL TABLES IN SCHEMA public FROM zb_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO zb_app;

-- 2. Housekeeping tables: the framework deletes rows here (logout, expired cache, role changes).
--    No business record lives in these tables. Add a table here only through a [spec-change] PR.
DO $$
DECLARE
    t text;
    housekeeping text[] := ARRAY[
        'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens',
        'model_has_roles', 'model_has_permissions', 'role_has_permissions'
    ];
BEGIN
    FOREACH t IN ARRAY housekeeping LOOP
        IF to_regclass(format('public.%I', t)) IS NOT NULL THEN
            EXECUTE format('GRANT DELETE ON public.%I TO zb_app', t);
        END IF;
    END LOOP;
END
$$;

-- 3. Append-only tables: rows are written once and never changed (spec 9.7, BR-AUD-02).
--    Database triggers also block UPDATE and DELETE; this is the second lock on the same door.
DO $$
DECLARE
    t text;
    append_only text[] := ARRAY[
        'journal_entries', 'journal_lines', 'audit_logs', 'rate_changes', 'period_events'
    ];
BEGIN
    FOREACH t IN ARRAY append_only LOOP
        IF to_regclass(format('public.%I', t)) IS NOT NULL THEN
            EXECUTE format('REVOKE UPDATE, DELETE, TRUNCATE ON public.%I FROM zb_app', t);
        END IF;
    END LOOP;
END
$$;

-- 4. The app may read the migrations table but never change it.
DO $$
BEGIN
    IF to_regclass('public.migrations') IS NOT NULL THEN
        REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON public.migrations FROM zb_app;
    END IF;
END
$$;
