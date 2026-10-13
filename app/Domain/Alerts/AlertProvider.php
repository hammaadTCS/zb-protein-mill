<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Models\User;

/**
 * One dashboard alert (spec §11.3): how many items need attention and where they are
 * listed. A provider the user may not see counts 0 for them.
 */
interface AlertProvider
{
    public function key(): string;

    public function count(User $user): int;

    /** Relative URL of the list the alert opens. */
    public function route(): string;

    public function label(): string;

    /** info, warning or danger */
    public function severity(): string;
}
