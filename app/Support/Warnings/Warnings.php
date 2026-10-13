<?php

declare(strict_types=1);

namespace App\Support\Warnings;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Conventions §5: the warnings of one save. An Action calls raise() for each warning
 * it finds; an unconfirmed one stops the save. After a successful save, audit()
 * writes one warning_confirmed row per confirmed warning.
 */
final class Warnings
{
    /** The only warning codes (conventions §5). */
    public const CODES = ['unusual_rate', 'duplicate_party', 'duplicate_entry', 'credit_limit', 'negative_cash', 'missing_reference', 'overpayment_info'];

    /** @var list<array{code: string, details: array<string, mixed>}> */
    private array $confirmedRaised = [];

    /**
     * @param  list<string>  $confirmed  the codes the user chose "Save anyway" for
     */
    public function __construct(private readonly array $confirmed = []) {}

    /**
     * @param  array<string, mixed>  $details
     *
     * @throws WarningRequired when the user has not confirmed this code
     */
    public function raise(string $code, string $message, array $details = []): void
    {
        if (! in_array($code, self::CODES, true)) {
            throw new InvalidArgumentException("Unknown warning code {$code}.");
        }

        if (! in_array($code, $this->confirmed, true)) {
            throw new WarningRequired($code, $message, $details);
        }

        $this->confirmedRaised[] = ['code' => $code, 'details' => $details];
    }

    /** Writes the warning_confirmed rows for the saved record. */
    public function audit(Model $record, User $by): void
    {
        foreach ($this->confirmedRaised as $warning) {
            AuditLogger::log(AuditActions::WARNING_CONFIRMED, $record, [], $warning, by: $by);
        }
    }
}
