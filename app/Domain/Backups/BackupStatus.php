<?php

declare(strict_types=1);

namespace App\Domain\Backups;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * D-40, BR-SEC-08, BR-OPS-07, ADR-0006: what the server backup scripts last wrote to
 * the status folder (`config('zb.backup_status_dir')`). The app only reads these
 * files; it never makes, downloads or restores a backup. A missing folder or file
 * means backups are not configured on this server (local development), never an error.
 */
final class BackupStatus
{
    /** BR-OPS-07: a database backup is late after 2 hours, an uploads backup after 26. */
    public const LATE_AFTER_HOURS = ['db' => 2, 'files' => 26];

    private const FILES = ['db' => 'backup-db', 'files' => 'backup-files'];

    /**
     * @return array{db: array<string, mixed>, files: array<string, mixed>}
     */
    public static function read(): array
    {
        return ['db' => self::kind('db'), 'files' => self::kind('files')];
    }

    /** The kinds whose last success is too old (dashboard alert). */
    public static function lateCount(): int
    {
        return count(array_filter(self::read(), fn (array $kind): bool => $kind['late']));
    }

    /**
     * @return array{configured: bool, late: bool, last_success: array<string, mixed>|null, last_attempt: array<string, mixed>|null}
     */
    private static function kind(string $kind): array
    {
        $attempt = self::file(self::FILES[$kind].'.json');
        $success = self::file(self::FILES[$kind].'-last-success.json');

        if ($attempt === null && $success === null) {
            return ['configured' => false, 'late' => false, 'last_success' => null, 'last_attempt' => null];
        }

        $finished = is_string($success['finished_at'] ?? null) ? self::time($success['finished_at']) : null;
        $late = $finished === null || $finished->lt(CarbonImmutable::now()->subHours(self::LATE_AFTER_HOURS[$kind]));

        return [
            'configured' => true,
            'late' => $late,
            'last_success' => $success === null ? null : [
                'finished_at' => $finished?->toIso8601String(),
                'size_mb' => is_int($success['bytes'] ?? null) ? Decimal::divide((string) $success['bytes'], '1048576', 1) : null,
                // Database: which copies were made (hourly, daily, monthly); uploads: full or added files.
                'copies' => is_string($success['copies'] ?? null) ? $success['copies'] : (is_string($success['type'] ?? null) ? $success['type'] : null),
            ],
            'last_attempt' => $attempt === null ? null : [
                'result' => ($attempt['result'] ?? null) === 'success' ? 'success' : 'failure',
                'finished_at' => is_string($attempt['finished_at'] ?? null) ? self::time($attempt['finished_at'])?->toIso8601String() : null,
                'error' => is_string($attempt['error'] ?? null) ? $attempt['error'] : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function file(string $name): ?array
    {
        $dir = config('zb.backup_status_dir');

        if (! is_string($dir) || $dir === '' || ! is_file("{$dir}/{$name}") || ! is_readable("{$dir}/{$name}")) {
            return null;
        }

        $data = json_decode((string) file_get_contents("{$dir}/{$name}"), true);

        return is_array($data) ? $data : null;
    }

    private static function time(string $utc): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($utc, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }
}
