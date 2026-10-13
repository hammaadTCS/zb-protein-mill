<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

/** BR-PAY-06 to BR-PAY-08: only "pending" payments wait for the Owner and have no journal. */
enum ApprovalStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
