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
    // (ADR-0002). `zb:db-check` and `zb:verify` refuse to pass when one is missing. The
    // names are the table's prevent_delete/prevent_change trigger plus the seal-row
    // trigger (T10.2 rebuilds them in MariaDB syntax; BR-LED-11, ADR-0016).
    'required_triggers' => [
        'adjustments_prevent_delete',
        'attachments_prevent_delete',
        'audit_logs_prevent_change',
        'bank_reconciliations_prevent_delete',
        'cash_counts_prevent_delete',
        'expense_categories_prevent_delete',
        'expenses_prevent_delete',
        'items_prevent_delete',
        'journal_entries_prevent_change',
        'journal_lines_prevent_change',
        'journal_seals_before_insert',
        'money_accounts_prevent_delete',
        'opening_balances_prevent_delete',
        'parties_prevent_delete',
        'partner_transactions_prevent_delete',
        'payments_prevent_delete',
        'period_events_prevent_change',
        'purchases_prevent_delete',
        'rate_changes_prevent_change',
        'rate_corrections_prevent_delete',
        'sales_prevent_delete',
        'transfers_prevent_delete',
    ],
];
