<?php

declare(strict_types=1);

namespace App\Support\Warnings;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Conventions §5: a warning the user has not confirmed yet. Rendered as HTTP 422
 * {"warning": {code, message, details}}; the page opens WarningDialog with
 * "Go back" and "Save anyway", which re-sends with the code in confirmed_warnings.
 */
final class WarningRequired extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $warningCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'warning' => [
                'code' => $this->warningCode,
                'message' => $this->getMessage(),
                'details' => $this->details,
            ],
        ], 422);
    }
}
