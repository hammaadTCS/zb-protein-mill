<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Backups\BackupStatus;
use App\Models\User;

/** BR-OPS-07: the last successful backup is too old (owner only, `backup.view`). */
final class BackupLate implements AlertProvider
{
    public function key(): string
    {
        return 'backup_late';
    }

    public function count(User $user): int
    {
        return $user->can('backup.view') ? BackupStatus::lateCount() : 0;
    }

    public function route(): string
    {
        return route('backups.show', [], false);
    }

    public function label(): string
    {
        return __('Backup late');
    }

    public function severity(): string
    {
        return 'danger';
    }
}
