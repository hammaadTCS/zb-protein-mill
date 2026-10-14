<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Models;

use App\Domain\Attachments\Enums\AttachmentKind;
use App\Support\HasPublicId;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A slip or receipt photo, or a PDF, linked to an entry (spec §9.5, BR-SEC-06).
 * Stored on the private `attachments` disk under a random name.
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property AttachmentKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $uploaded_by
 * @property int|null $replaced_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $attachable
 */
#[Fillable(['company_id', 'attachable_type', 'attachable_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by', 'replaced_by_id', 'created_by', 'updated_by'])]
class Attachment extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use NeverDeleted;

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Attachments still in use (not replaced by a newer file).
     *
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('replaced_by_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'size_bytes' => 'integer',
        ];
    }
}
