-- TEST FIXTURE ONLY. Not the application schema.
-- A small stand-in for what the real migrations create (spec 9.7), so the infrastructure
-- tests can prove that roles, grants, triggers and backups behave as designed.
-- Run as zb_migrator (OS user zbdeploy).

\set ON_ERROR_STOP on

CREATE TABLE migrations (id serial PRIMARY KEY, migration varchar(255) NOT NULL, batch int NOT NULL);
CREATE TABLE sessions (id varchar(255) PRIMARY KEY, user_id bigint, payload text NOT NULL, last_activity int NOT NULL);
CREATE TABLE cache (key varchar(255) PRIMARY KEY, value text NOT NULL, expiration int NOT NULL);

CREATE TABLE purchases (
    id bigserial PRIMARY KEY,
    company_id bigint NOT NULL,
    voucher_no varchar(20) NOT NULL,
    kg numeric(14,3) NOT NULL,
    rate numeric(14,4),
    amount numeric(16,2),
    idempotency_key uuid,
    UNIQUE (company_id, idempotency_key)
);

CREATE TABLE journal_entries (
    id bigserial PRIMARY KEY,
    entry_no varchar(20) NOT NULL,
    posting_date date NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE journal_lines (
    id bigserial PRIMARY KEY,
    journal_entry_id bigint NOT NULL REFERENCES journal_entries (id),
    line_no int NOT NULL,
    debit numeric(16,2) NOT NULL DEFAULT 0 CHECK (debit >= 0),
    credit numeric(16,2) NOT NULL DEFAULT 0 CHECK (credit >= 0),
    CHECK ((debit > 0) <> (credit > 0))
);

CREATE FUNCTION zb_block_change() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION '% on % is not allowed', TG_OP, TG_TABLE_NAME;
END
$$;

CREATE TRIGGER journal_entries_immutable BEFORE UPDATE OR DELETE ON journal_entries
    FOR EACH ROW EXECUTE FUNCTION zb_block_change();
CREATE TRIGGER journal_lines_immutable BEFORE UPDATE OR DELETE ON journal_lines
    FOR EACH ROW EXECUTE FUNCTION zb_block_change();
CREATE TRIGGER purchases_no_delete BEFORE DELETE ON purchases
    FOR EACH ROW EXECUTE FUNCTION zb_block_change();

CREATE FUNCTION zb_journal_balance_check() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    d numeric; c numeric;
BEGIN
    SELECT coalesce(sum(debit), 0), coalesce(sum(credit), 0) INTO d, c
    FROM journal_lines WHERE journal_entry_id = NEW.journal_entry_id;
    IF d <> c THEN
        RAISE EXCEPTION 'journal % is not balanced: debit % credit %', NEW.journal_entry_id, d, c;
    END IF;
    RETURN NULL;
END
$$;

CREATE CONSTRAINT TRIGGER journal_balance_check AFTER INSERT ON journal_lines
    DEFERRABLE INITIALLY DEFERRED
    FOR EACH ROW EXECUTE FUNCTION zb_journal_balance_check();

INSERT INTO migrations (migration, batch) VALUES ('fixture', 1);
