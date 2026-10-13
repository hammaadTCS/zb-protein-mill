<?php

namespace App\Http\Controllers;

use App\Domain\Attachments\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** BR-SEC-06: files go out only through this permission-checked route. */
class AttachmentController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->attachable);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
