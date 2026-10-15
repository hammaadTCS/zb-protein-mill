-- TEST FIXTURE ONLY. Not the application schema.
-- A small MariaDB stand-in for what the real migrations create (spec 9.7, ADR-0016), so the
-- infrastructure tests can prove that the database rules behave as designed on MariaDB:
--   - the journal is append-only (UPDATE and DELETE refused by triggers);
--   - a master/transaction table refuses DELETE (purchases);
--   - the journal_seals seal trigger refuses an entry with fewer than two lines or debits that
--     do not equal credits (BR-LED-11), the MariaDB replacement for the deferred constraint
--     trigger of ADR-0002;
--   - mariadb-dump keeps the triggers.
-- There is one all-rights database user on the host (BR-SEC-12/ADR-0014), so the "rules" are the
-- triggers themselves, not database privileges.
-- Run as the application database user. InnoDB + utf8mb4, like production.

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY';

CREATE TABLE migrations (
    id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    migration varchar(255) NOT NULL,
    batch int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id varchar(255) NOT NULL PRIMARY KEY,
    user_id bigint unsigned NULL,
    payload mediumtext NOT NULL,
    last_activity int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache (
    `key` varchar(255) NOT NULL PRIMARY KEY,
    value mediumtext NOT NULL,
    expiration int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchases (
    id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id bigint unsigned NOT NULL,
    voucher_no varchar(20) NOT NULL,
    kg decimal(14,3) NOT NULL,
    rate decimal(14,4) NULL,
    amount decimal(16,2) NULL,
    status varchar(10) NOT NULL DEFAULT 'active',
    `uuid` char(36) NULL,
    entry_date date NOT NULL,
    UNIQUE KEY purchases_company_voucher_unique (company_id, voucher_no),
    UNIQUE KEY purchases_company_idempotency_unique (company_id, `uuid`),
    CONSTRAINT purchases_kg_check CHECK (kg > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_entries (
    id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id bigint unsigned NOT NULL,
    entry_no varchar(20) NOT NULL,
    posting_date date NOT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY journal_entries_company_entry_unique (company_id, entry_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_lines (
    id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id bigint unsigned NOT NULL,
    journal_entry_id bigint unsigned NOT NULL,
    line_no smallint NOT NULL,
    debit decimal(16,2) NOT NULL DEFAULT 0,
    credit decimal(16,2) NOT NULL DEFAULT 0,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY journal_lines_entry_line_unique (journal_entry_id, line_no),
    CONSTRAINT journal_lines_entry_fk FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id),
    -- BR-LED-02: exactly one side is greater than zero.
    CONSTRAINT journal_lines_one_side CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BR-LED-10/ADR-0016: one seal row per journal entry, written at the end of the posting
-- transaction. The BEFORE INSERT trigger is where the balance guarantee lives.
CREATE TABLE journal_seals (
    id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id bigint unsigned NOT NULL,
    source varchar(20) NOT NULL DEFAULT 'posting',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY journal_seals_entry_unique (journal_entry_id),
    CONSTRAINT journal_seals_entry_fk FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BR-LED-05: journal rows are never updated or deleted. MariaDB triggers are one event each, so
-- every protected operation gets its own trigger with the same refusal message as the PostgreSQL
-- prevent_change()/prevent_delete() functions.
DELIMITER //
CREATE TRIGGER journal_entries_prevent_update BEFORE UPDATE ON journal_entries
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_entries cannot be updated or deleted';
END//
CREATE TRIGGER journal_entries_prevent_delete BEFORE DELETE ON journal_entries
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_entries cannot be updated or deleted';
END//
CREATE TRIGGER journal_lines_prevent_update BEFORE UPDATE ON journal_lines
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_lines cannot be updated or deleted';
END//
CREATE TRIGGER journal_lines_prevent_delete BEFORE DELETE ON journal_lines
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_lines cannot be updated or deleted';
END//
-- BR-GEN-03: DELETE is refused on master and transaction tables.
CREATE TRIGGER purchases_prevent_delete BEFORE DELETE ON purchases
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in purchases cannot be deleted';
END//
-- Seal rows are never updated or deleted.
CREATE TRIGGER journal_seals_prevent_update BEFORE UPDATE ON journal_seals
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_seals cannot be updated or deleted';
END//
CREATE TRIGGER journal_seals_prevent_delete BEFORE DELETE ON journal_seals
FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_seals cannot be updated or deleted';
END//
-- BR-LED-11: at the end of the posting transaction the seal row is written; this trigger sums the
-- entry's lines and refuses the whole save when the entry has fewer than two lines or its debit
-- total does not equal its credit total.
CREATE TRIGGER journal_seals_before_insert BEFORE INSERT ON journal_seals
FOR EACH ROW BEGIN
    DECLARE total_debit decimal(18,2);
    DECLARE total_credit decimal(18,2);
    DECLARE line_count int;
    SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0), COUNT(*)
        INTO total_debit, total_credit, line_count
        FROM journal_lines WHERE journal_entry_id = NEW.journal_entry_id;
    IF line_count < 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal entry has fewer than 2 lines';
    END IF;
    IF total_debit <> total_credit THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'journal entry is not balanced';
    END IF;
END//
DELIMITER ;

INSERT INTO migrations (migration, batch) VALUES ('fixture', 1);
