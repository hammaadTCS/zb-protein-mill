<?php

/*
 * ZB Mill settings that differ per server (docs/environments.md). Business settings
 * live in the database, never here (docs/environments.md §4).
 */
return [
    // T7.6: the folder where the server backup scripts write their JSON status files
    // (infra/bin/zb-backup-db, zb-backup-files). Unset in local development.
    'backup_status_dir' => env('ZB_BACKUP_STATUS_DIR'),
];
