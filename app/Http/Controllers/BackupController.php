<?php

namespace App\Http\Controllers;

use App\Domain\Backups\BackupStatus;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page 48, Backup status (D-40, BR-OPS-07): read-only. There is no download: backups
 * can only be read with the offline key (ADR-0006, spec §7 `backup.view`).
 */
class BackupController extends Controller
{
    public function show(): Response
    {
        Gate::authorize('backup.view');

        return Inertia::render('backups/show', [
            'status' => BackupStatus::read(),
            'late_after_hours' => BackupStatus::LATE_AFTER_HOURS,
        ]);
    }
}
