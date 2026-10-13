<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * BR-GEN-09: two users edited the same entry; the second save is refused with the
 * glossary "Concurrent edit" text (HTTP 409). Edit forms send `version`, the
 * record's updated_at as an ISO string from when the form was opened.
 */
final class ConcurrentEdit extends RuntimeException
{
    public const MESSAGE = 'This entry was changed by another user. Reload.';

    public function __construct()
    {
        parent::__construct(__(self::MESSAGE));
    }

    /** Throws when the record changed since the form was opened. */
    public static function check(?CarbonInterface $updatedAt, ?string $version): void
    {
        if ($version === null || $updatedAt === null || $updatedAt->toIso8601String() !== $version) {
            throw new self;
        }
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 409);
    }
}
