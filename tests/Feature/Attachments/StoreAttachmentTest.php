<?php

use App\Domain\Attachments\Actions\StoreAttachment;
use App\Domain\Attachments\Enums\AttachmentKind;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    freezeToday('2026-01-31');
    Storage::fake('attachments');
});

function attachmentPurchase(User $owner): Purchase
{
    $world = acceptanceWorld(['parties' => [['key' => 'aslam', 'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']]], $owner);

    return app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50',
    ]);
}

/** A real JPEG made with GD, with an APP1 "Exif" segment put in after the start marker. */
function jpegWithExif(): string
{
    $image = imagecreatetruecolor(40, 30);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 120, 40));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();

    $payload = "Exif\0\0".str_repeat('GPS-LOCATION-DATA ', 4);
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
}

function store(User $by, Purchase $purchase, UploadedFile $file): Attachment
{
    return app(StoreAttachment::class)->handle($by, $purchase, $file, AttachmentKind::SlipPhoto);
}

it('BR-SEC-06 a 6 MB file is refused', function () {
    $owner = owner();

    expect(fn () => store($owner, attachmentPurchase($owner), UploadedFile::fake()->create('slip.jpg', 6 * 1024, 'image/jpeg')))
        ->toThrow(ValidationException::class, 'The file must be 5 MB or smaller.');
});

it('BR-SEC-06 an .exe is refused', function () {
    $owner = owner();
    $exe = UploadedFile::fake()->createWithContent('setup.exe', "MZ\x90\x00".str_repeat("\x00", 200));

    expect(fn () => store($owner, attachmentPurchase($owner), $exe))
        ->toThrow(ValidationException::class, 'Only JPG, PNG, WEBP or PDF files can be added.');
});

it('BR-SEC-06 an .exe renamed to .jpg is refused', function () {
    $owner = owner();
    $fake = UploadedFile::fake()->createWithContent('slip.jpg', "MZ\x90\x00".str_repeat("\x00", 200));

    expect(fn () => store($owner, attachmentPurchase($owner), $fake))
        ->toThrow(ValidationException::class, 'Only JPG, PNG, WEBP or PDF files can be added.');
    expect(Attachment::query()->count())->toBe(0);
});

it('BR-SEC-06 a JPEG with an Exif block is stored without it', function () {
    $owner = owner();
    $original = jpegWithExif();
    expect($original)->toContain('Exif');

    $attachment = store($owner, attachmentPurchase($owner), UploadedFile::fake()->createWithContent('IMG_2041.jpg', $original));
    $stored = Storage::disk('attachments')->get($attachment->path);

    expect($stored)->not->toContain('Exif')
        ->and($stored)->not->toContain('GPS-LOCATION')
        ->and(str_starts_with((string) $stored, "\xFF\xD8"))->toBeTrue()
        ->and($attachment->mime_type)->toBe('image/jpeg')
        ->and($attachment->original_name)->toBe('IMG_2041.jpg')
        ->and($attachment->path)->toMatch('/^[0-9a-f-]{36}\.jpg$/')
        ->and($attachment->attachable_type)->toBe('purchase');
});

it('BR-SEC-06 a PDF is stored as received', function () {
    $owner = owner();
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    $attachment = store($owner, attachmentPurchase($owner), UploadedFile::fake()->createWithContent('slip.pdf', $pdf));

    expect(Storage::disk('attachments')->get($attachment->path))->toBe($pdf)
        ->and($attachment->mime_type)->toBe('application/pdf');
});

it('attachment download requires purchase.view', function () {
    $owner = owner();
    $attachment = store($owner, attachmentPurchase($owner), UploadedFile::fake()->createWithContent('slip.jpg', jpegWithExif()));

    $this->actingAs(User::factory()->create())->get(route('attachments.show', $attachment))->assertForbidden();

    $this->actingAs(accountant())->get(route('attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'inline; filename=slip.jpg');
});

it('BR-PUR-07 a slip photo sent with a new purchase is attached to it', function () {
    $owner = owner();
    $world = acceptanceWorld(['parties' => [['key' => 'aslam', 'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']]], $owner);

    $this->actingAs($owner)->post(route('purchases.store'), [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50',
        'slip_photo' => UploadedFile::fake()->createWithContent('slip.jpg', jpegWithExif()),
    ])->assertRedirect(route('purchases.show', Purchase::query()->sole()));

    expect(Attachment::query()->sole()->attachable_id)->toBe(Purchase::query()->sole()->id);
});
