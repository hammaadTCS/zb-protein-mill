<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Actions;

use App\Domain\Attachments\Enums\AttachmentKind;
use App\Domain\Attachments\Models\Attachment;
use App\Models\User;
use GdImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BR-SEC-06: the only way a file enters the system (conventions §12). The type is
 * read from the file content, not its name; images are decoded and re-encoded with
 * GD, which drops location and other metadata; PDFs are kept as received. The file
 * gets a random name on the private `attachments` disk. Nothing is ever deleted;
 * a replacement sets `replaced_by_id` on the old row.
 */
final class StoreAttachment
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** content type => stored file extension */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function handle(User $by, Model $attachable, UploadedFile $file, AttachmentKind $kind, string $field = 'file', ?Attachment $replaces = null): Attachment
    {
        $realPath = (string) $file->getRealPath();

        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => __('The file must be 5 MB or smaller.')]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        $extension = self::TYPES[$mime] ?? throw ValidationException::withMessages([$field => __('Only JPG, PNG, WEBP or PDF files can be added.')]);
        $contents = $mime === 'application/pdf' ? (string) file_get_contents($realPath) : self::reencode($realPath, $mime, $field);

        $path = Str::uuid()->toString().'.'.$extension;
        Storage::disk('attachments')->put($path, $contents);

        $attachment = Attachment::query()->create([
            'company_id' => $by->company_id,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'kind' => $kind,
            'disk' => 'attachments',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mime,
            'size_bytes' => strlen($contents),
            'uploaded_by' => $by->id,
            'created_by' => $by->id,
        ]);

        $replaces?->update(['replaced_by_id' => $attachment->id, 'updated_by' => $by->id]);

        return $attachment;
    }

    /** Decodes the image and encodes it again: JPEG at quality 85, PNG and WEBP as they came. */
    private static function reencode(string $path, string $mime, string $field): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => __('Only JPG, PNG, WEBP or PDF files can be added.')]);
        }

        ob_start();

        match ($mime) {
            'image/png' => self::png($image),
            'image/webp' => imagewebp($image),
            default => imagejpeg($image, null, 85),
        };

        return (string) ob_get_clean();
    }

    private static function png(GdImage $image): bool
    {
        imagesavealpha($image, true);

        return imagepng($image);
    }
}
