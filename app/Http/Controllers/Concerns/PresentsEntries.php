<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Attachments\Models\Attachment;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use Illuminate\Database\Eloquent\Model;

/** Props shared by entry forms and detail pages. */
trait PresentsEntries
{
    /**
     * Active cash and bank accounts for a form (BR-GEN-08).
     *
     * @return list<array{id: int, public_id: string, name: string, type: string}>
     */
    protected function moneyAccountOptions(): array
    {
        return array_values(MoneyAccount::query()->active()->orderBy('type')->orderBy('name')->get()
            ->map(fn (MoneyAccount $account): array => ['id' => $account->id, 'public_id' => $account->public_id, 'name' => $account->name, 'type' => $account->type->value])
            ->all());
    }

    /**
     * The current (not replaced) attachments of an entry.
     *
     * @return list<array{id: int, kind: string, name: string, mime_type: string, url: string}>
     */
    protected function attachmentsOf(Model $entry): array
    {
        return array_values(Attachment::query()
            ->where('attachable_type', $entry->getMorphClass())
            ->where('attachable_id', $entry->getKey())
            ->current()
            ->orderBy('id')
            ->get()
            ->map(fn (Attachment $a): array => [
                'id' => $a->id,
                'kind' => $a->kind->value,
                'name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'url' => route('attachments.show', $a),
            ])
            ->all());
    }
}
