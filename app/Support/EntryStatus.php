<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Spec §9.1: every transaction is active or cancelled. Nothing is deleted
 * (BR-GEN-03); a cancelled entry stays visible with its reason (BR-GEN-04).
 */
enum EntryStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
