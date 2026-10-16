<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * BR-SEC-19: links and address-bar filters name a record by its random `public_id`
 * (UUID v4), never by its internal number, so URLs neither show nor invite guessing
 * how many entries exist. Permission checks and the company scope stay the protection;
 * this keeps the internal number out of sight.
 *
 * The database also fills the column (UUID()), so rows saved by a release
 * without this trait still get one.
 *
 * @mixin Model
 */
trait HasPublicId
{
    /** What a route parameter must look like before the database is asked (UUID). */
    public const PUBLIC_ID_PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if (! is_string($model->getAttribute('public_id')) || $model->getAttribute('public_id') === '') {
                $model->setAttribute('public_id', (string) Str::uuid());
            }
        });
    }

    /** Route model binding finds the record by its public id, inside the company scope. */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * The internal id of the record a filter names by public id (`?party_id=<uuid>`), in
     * the company scope; null when no filter was given. Aborts with 404 for an unknown or
     * malformed value, as a record URL does.
     */
    public static function idFromPublic(?string $publicId): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        $id = preg_match('/^'.self::PUBLIC_ID_PATTERN.'$/', $publicId) === 1
            ? static::query()->where('public_id', $publicId)->value('id')
            : null;

        abort_if($id === null, 404);

        return (int) $id;
    }
}
