<?php

/*
 * ZB Mill settings that differ per server (docs/environments.md). Business settings
 * live in the database, never here (docs/environments.md §4).
 */
return [
    // T7.6: the folder where the server backup scripts write their JSON status files
    // (infra/bin/zb-backup-db, zb-backup-files). Unset in local development.
    'backup_status_dir' => env('ZB_BACKUP_STATUS_DIR'),

    // Card T10.1 / conventions.md §14a: the protection triggers that must exist, because
    // "the database itself" is what enforces the ledger and the delete/change rules
    // (ADR-0014, ADR-0016). `zb:db-check` and `zb:verify` refuse to pass when one is
    // missing. Every protected table has one trigger per refused event (MariaDB has no
    // multi-event trigger): <table>_prevent_update and <table>_prevent_delete where both
    // are refused, <table>_prevent_delete where only deletion is, plus the seal-row
    // trigger that balances each journal entry (T10.2, BR-LED-11).
    'required_triggers' => [
        'adjustments_prevent_delete',
        'attachments_prevent_delete',
        'audit_logs_prevent_delete',
        'audit_logs_prevent_update',
        'bank_reconciliations_prevent_delete',
        'cash_counts_prevent_delete',
        'companies_prevent_delete',
        'expense_categories_prevent_delete',
        'expenses_prevent_delete',
        'items_prevent_delete',
        'journal_entries_prevent_delete',
        'journal_entries_prevent_update',
        'journal_lines_prevent_delete',
        'journal_lines_prevent_update',
        'journal_seals_before_insert',
        'journal_seals_prevent_delete',
        'journal_seals_prevent_update',
        'ledger_accounts_prevent_delete',
        'money_accounts_prevent_delete',
        'opening_balances_prevent_delete',
        'parties_prevent_delete',
        'partner_transactions_prevent_delete',
        'payments_prevent_delete',
        'period_events_prevent_delete',
        'period_events_prevent_update',
        'purchases_prevent_delete',
        'rate_changes_prevent_delete',
        'rate_changes_prevent_update',
        'rate_corrections_prevent_delete',
        'sales_prevent_delete',
        'settings_prevent_delete',
        'transfers_prevent_delete',
        'users_prevent_delete',
        'voucher_sequences_prevent_delete',
    ],
];
