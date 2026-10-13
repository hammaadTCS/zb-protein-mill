<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Enums;

/** Spec §9.5: what an attachment is. */
enum AttachmentKind: string
{
    case SlipPhoto = 'slip_photo';
    case ReceiptPhoto = 'receipt_photo';
    case Other = 'other';
}
