import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { PageHeader } from '@/components/app/PageHeader';
import type { PartyOption } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { useWarnings } from '@/components/app/WarningDialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { newIdempotencyKey } from '@/lib/uuid';
import { store, update } from '@/routes/partners';

type EntryType = 'investment' | 'withdrawal';
type AccountOption = { id: number; name: string; type: 'cash' | 'bank' };

export type EditedPartnerEntry = {
    id: number;
    voucher_no: string;
    version: string;
    date: string;
    party_id: number;
    type: EntryType;
    amount: string;
    money_account_id: number;
    note: string | null;
};

export type PartnerFormProps = {
    today: string;
    money_accounts: AccountOption[];
    party: PartyOption | null;
    entry?: EditedPartnerEntry;
};

type FormData = {
    idempotency_key: string;
    date: string;
    party_id: number | null;
    type: EntryType;
    amount: string;
    money_account_id: string;
    note: string;
};

/**
 * Page 22, New partner entry, and its edit page (spec §11.10): date, partner,
 * investment or withdrawal, amount, account, note. Never mixed with sales or
 * expenses (D-21).
 */
export function PartnerForm({
    today,
    money_accounts,
    party,
    entry,
}: PartnerFormProps) {
    const [picked, setPicked] = useState<PartyOption | null>(party);
    const [conflict, setConflict] = useState<string | null>(null);
    const form = useForm<FormData>({
        idempotency_key: newIdempotencyKey(), // BR-GEN-11
        date: entry?.date ?? today,
        party_id: entry?.party_id ?? party?.id ?? null,
        type: entry?.type ?? 'investment',
        amount: entry?.amount ?? '',
        money_account_id: entry
            ? String(entry.money_account_id)
            : money_accounts.length === 1
              ? String(money_accounts[0].id)
              : '',
        note: entry?.note ?? '',
    });
    const { data, setData, errors } = form;

    const warnings = useWarnings((confirmed, onHttpException) => {
        if (entry) {
            form.transform(({ idempotency_key: _key, ...rest }) => ({
                ...rest,
                version: entry.version,
                confirmed_warnings: confirmed,
            }));
            form.put(update.url(entry.id), {
                onHttpException: (response) => {
                    if (response.status === 409) {
                        const body = response.data as { message?: string };
                        setConflict(
                            body.message ??
                                'This entry was changed by another user. Reload.',
                        );

                        return false;
                    }

                    return onHttpException(response);
                },
            });

            return;
        }

        form.transform((fields) => ({
            ...fields,
            confirmed_warnings: confirmed,
        }));
        form.post(store.url(), { onHttpException });
    });

    const title = entry ? `Edit ${entry.voucher_no}` : 'New partner entry';

    return (
        <>
            <Head title={title} />
            <form
                className="mx-auto flex w-full max-w-xl flex-col gap-5 p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    warnings.submit();
                }}
            >
                <PageHeader title={title} />

                {conflict && (
                    <Alert variant="destructive">
                        <AlertDescription>{conflict}</AlertDescription>
                    </Alert>
                )}

                <div
                    role="radiogroup"
                    aria-label="Type"
                    className="grid grid-cols-2 gap-3"
                >
                    {(['investment', 'withdrawal'] as const).map((type) => (
                        <button
                            key={type}
                            type="button"
                            role="radio"
                            aria-checked={data.type === type}
                            onClick={() => setData('type', type)}
                            className={cn(
                                'h-14 rounded-md border text-base font-semibold',
                                data.type === type
                                    ? type === 'investment'
                                        ? 'border-green-700 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200'
                                        : 'border-red-700 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {type === 'investment'
                                ? 'Investment'
                                : 'Withdrawal'}
                        </button>
                    ))}
                </div>

                <FormField id="date" label="Date" error={errors.date}>
                    <Input
                        id="date"
                        type="date"
                        className="h-11"
                        max={today}
                        value={data.date}
                        onChange={(e) => setData('date', e.target.value)}
                        required
                    />
                </FormField>

                <FormField
                    id="party_id"
                    label="Partner"
                    error={errors.party_id}
                >
                    <PartyPicker
                        id="party_id"
                        types={['partner']}
                        value={picked}
                        placeholder="Select partner"
                        invalid={!!errors.party_id}
                        onChange={(option) => {
                            setPicked(option);
                            setData('party_id', option?.id ?? null);
                        }}
                    />
                </FormField>

                <FormField id="amount" label="Amount" error={errors.amount}>
                    <DecimalInput
                        id="amount"
                        value={data.amount}
                        onChange={(value) => setData('amount', value)}
                        required
                    />
                </FormField>

                <FormField
                    id="money_account_id"
                    label="Account"
                    error={errors.money_account_id}
                >
                    <Select
                        value={data.money_account_id}
                        onValueChange={(value) =>
                            setData('money_account_id', value)
                        }
                    >
                        <SelectTrigger
                            id="money_account_id"
                            className="h-11 w-full"
                        >
                            <SelectValue placeholder="Select account" />
                        </SelectTrigger>
                        <SelectContent>
                            {money_accounts.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField id="note" label="Note" error={errors.note}>
                    <Textarea
                        id="note"
                        maxLength={2000}
                        value={data.note}
                        onChange={(e) => setData('note', e.target.value)}
                    />
                </FormField>

                <Button
                    type="submit"
                    size="lg"
                    className="h-12"
                    disabled={form.processing}
                >
                    Save
                </Button>
            </form>
            {warnings.dialog}
        </>
    );
}
