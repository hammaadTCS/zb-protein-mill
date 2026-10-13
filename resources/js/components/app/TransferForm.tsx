import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { PageHeader } from '@/components/app/PageHeader';
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
import { newIdempotencyKey } from '@/lib/uuid';
import { store, update } from '@/routes/transfers';

type AccountOption = { id: number; name: string; type: 'cash' | 'bank' };

export type EditedTransfer = {
    id: number;
    voucher_no: string;
    version: string;
    date: string;
    from_account_id: number;
    to_account_id: number;
    amount: string;
    reference_no: string | null;
    note: string | null;
};

export type TransferFormProps = {
    today: string;
    money_accounts: AccountOption[];
    transfer?: EditedTransfer;
};

type FormData = {
    idempotency_key: string;
    date: string;
    from_account_id: string;
    to_account_id: string;
    amount: string;
    reference_no: string;
    note: string;
};

/**
 * Page 16, New transfer, and its edit page (spec §11.10): date, from account, to
 * account, amount, reference, note. Never income or spending (BR-TRF-03).
 */
export function TransferForm({
    today,
    money_accounts,
    transfer,
}: TransferFormProps) {
    const [conflict, setConflict] = useState<string | null>(null);
    const form = useForm<FormData>({
        idempotency_key: newIdempotencyKey(), // BR-GEN-11
        date: transfer?.date ?? today,
        from_account_id: transfer ? String(transfer.from_account_id) : '',
        to_account_id: transfer ? String(transfer.to_account_id) : '',
        amount: transfer?.amount ?? '',
        reference_no: transfer?.reference_no ?? '',
        note: transfer?.note ?? '',
    });
    const { data, setData, errors } = form;

    const warnings = useWarnings((confirmed, onHttpException) => {
        if (transfer) {
            form.transform(({ idempotency_key: _key, ...rest }) => ({
                ...rest,
                version: transfer.version,
                confirmed_warnings: confirmed,
            }));
            form.put(update.url(transfer.id), {
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

    const accountSelect = (
        field: 'from_account_id' | 'to_account_id',
        label: string,
    ) => (
        <FormField id={field} label={label} error={errors[field]}>
            <Select
                value={data[field]}
                onValueChange={(value) => setData(field, value)}
            >
                <SelectTrigger id={field} className="h-11 w-full">
                    <SelectValue placeholder="Select account" />
                </SelectTrigger>
                <SelectContent>
                    {money_accounts.map((option) => (
                        <SelectItem key={option.id} value={String(option.id)}>
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FormField>
    );

    const title = transfer ? `Edit ${transfer.voucher_no}` : 'New transfer';

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

                {accountSelect('from_account_id', 'From account')}
                {accountSelect('to_account_id', 'To account')}

                <FormField id="amount" label="Amount" error={errors.amount}>
                    <DecimalInput
                        id="amount"
                        value={data.amount}
                        onChange={(value) => setData('amount', value)}
                        required
                    />
                </FormField>

                <FormField
                    id="reference_no"
                    label="Reference number"
                    error={errors.reference_no}
                >
                    <Input
                        id="reference_no"
                        className="h-11"
                        maxLength={100}
                        value={data.reference_no}
                        onChange={(e) =>
                            setData('reference_no', e.target.value)
                        }
                    />
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
