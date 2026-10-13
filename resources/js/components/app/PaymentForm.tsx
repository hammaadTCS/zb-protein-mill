import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
import { useFormats } from '@/hooks/use-formats';
import { formatMoney } from '@/lib/format';
import { prepareImage } from '@/lib/image';
import { cn } from '@/lib/utils';
import { newIdempotencyKey } from '@/lib/uuid';
import { balancePreview, store, update } from '@/routes/payments';

type Direction = 'paid' | 'received';
type AccountOption = { id: number; name: string; type: 'cash' | 'bank' };
type Words = { words: string; amount: string };

export type EditedPayment = {
    id: number;
    voucher_no: string;
    version: string;
    date: string;
    direction: Direction;
    party_id: number;
    amount: string;
    money_account_id: number;
    reference_no: string | null;
    handled_by_party_id: number | null;
    note: string | null;
};

export type PaymentFormProps = {
    today: string;
    money_accounts: AccountOption[];
    party: PartyOption | null;
    handled_by?: PartyOption | null;
    payment?: EditedPayment;
};

type FormData = {
    idempotency_key: string;
    direction: Direction;
    date: string;
    party_id: number | null;
    amount: string;
    money_account_id: string;
    reference_no: string;
    handled_by_party_id: number | null;
    photo: File | null;
    note: string;
};

/**
 * Page 10 and its edit page (spec §11.8), mobile-first: Paid / Received, date, party
 * (any type except partner), balance now and after (BR-PAY-05), amount, account,
 * reference for bank accounts, handled by, photo, note.
 */
export function PaymentForm({
    today,
    money_accounts,
    party,
    handled_by,
    payment,
}: PaymentFormProps) {
    const formats = useFormats();
    const [picked, setPicked] = useState<PartyOption | null>(party);
    const [handledBy, setHandledBy] = useState<PartyOption | null>(
        handled_by ?? null,
    );
    const [preview, setPreview] = useState<{
        current: Words;
        after: Words;
        info: string | null;
    } | null>(null);
    const [conflict, setConflict] = useState<string | null>(null);

    const form = useForm<FormData>({
        idempotency_key: newIdempotencyKey(), // BR-GEN-11
        direction: payment?.direction ?? 'paid',
        date: payment?.date ?? today,
        party_id: payment?.party_id ?? party?.id ?? null,
        amount: payment?.amount ?? '',
        money_account_id: payment
            ? String(payment.money_account_id)
            : money_accounts.length === 1
              ? String(money_accounts[0].id)
              : '',
        reference_no: payment?.reference_no ?? '',
        handled_by_party_id: payment?.handled_by_party_id ?? null,
        photo: null,
        note: payment?.note ?? '',
    });
    const { data, setData, errors } = form;
    const account = money_accounts.find(
        (a) => String(a.id) === data.money_account_id,
    );
    const money = (value: string) =>
        formatMoney(value, {
            numberFormat: formats.numberFormat,
            amountRounding: formats.amountRounding,
        });

    // BR-PAY-05: the party's balance now and after this entry, from the server.
    useEffect(() => {
        if (!data.party_id) {
            return;
        }

        const amount = /^\d+(\.\d{1,2})?$/.test(data.amount)
            ? data.amount
            : '0';
        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(
                balancePreview.url({
                    query: {
                        party_id: data.party_id ?? '',
                        direction: data.direction,
                        amount,
                        ...(payment ? { payment_id: payment.id } : {}),
                    },
                }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then((response) => (response.ok ? response.json() : null))
                .then((body) => setPreview(body))
                .catch(() => undefined);
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [data.party_id, data.direction, data.amount, payment]);

    const warnings = useWarnings((confirmed, onHttpException) => {
        if (payment) {
            form.transform((fields) => {
                const {
                    idempotency_key: _key,
                    photo: _photo,
                    ...rest
                } = fields;

                return {
                    ...rest,
                    version: payment.version,
                    confirmed_warnings: confirmed,
                };
            });
            form.put(update.url(payment.id), {
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

        form.transform((fields) => {
            const { photo, ...rest } = fields;

            return {
                ...rest,
                ...(photo ? { photo } : {}),
                confirmed_warnings: confirmed,
            };
        });
        form.post(store.url(), { onHttpException });
    });

    const title = payment ? `Edit ${payment.voucher_no}` : 'New payment';

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
                    aria-label="Direction"
                    className="grid grid-cols-2 gap-3"
                >
                    {(['paid', 'received'] as const).map((direction) => (
                        <button
                            key={direction}
                            type="button"
                            role="radio"
                            aria-checked={data.direction === direction}
                            onClick={() => setData('direction', direction)}
                            className={cn(
                                'h-14 rounded-md border text-base font-semibold',
                                data.direction === direction
                                    ? direction === 'paid'
                                        ? 'border-red-700 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200'
                                        : 'border-green-700 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {direction === 'paid' ? 'Paid' : 'Received'}
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

                <FormField id="party_id" label="Party" error={errors.party_id}>
                    <PartyPicker
                        id="party_id"
                        types={['supplier', 'buyer', 'employee', 'other']}
                        value={picked}
                        placeholder="Select party"
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

                {preview && data.party_id && (
                    <div className="grid gap-1 rounded-md bg-muted px-4 py-3 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Balance now
                            </span>
                            <span className="tabular-nums">
                                {preview.current.words}
                                {preview.current.words !== 'Settled' &&
                                    ` ${money(preview.current.amount)}`}
                            </span>
                        </div>
                        <div className="flex justify-between font-medium">
                            <span className="text-muted-foreground">
                                Balance after
                            </span>
                            <span className="tabular-nums">
                                {preview.after.words}
                                {preview.after.words !== 'Settled' &&
                                    ` ${money(preview.after.amount)}`}
                            </span>
                        </div>
                        {preview.info && (
                            <p className="text-blue-800 dark:text-blue-300">
                                {preview.info}
                            </p>
                        )}
                    </div>
                )}

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
                                    {option.name} (
                                    {option.type === 'bank'
                                        ? 'Bank transfer'
                                        : 'Cash'}
                                    )
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                {account?.type === 'bank' && (
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
                )}

                <FormField
                    id="handled_by_party_id"
                    label="Handled by"
                    error={errors.handled_by_party_id}
                >
                    <PartyPicker
                        id="handled_by_party_id"
                        types={['employee']}
                        value={handledBy}
                        placeholder="Select employee"
                        onChange={(option) => {
                            setHandledBy(option);
                            setData('handled_by_party_id', option?.id ?? null);
                        }}
                    />
                </FormField>

                {!payment && (
                    <FormField
                        id="photo"
                        label="Photo"
                        error={
                            (errors as Record<string, string | undefined>).photo
                        }
                    >
                        <Input
                            id="photo"
                            type="file"
                            accept="image/*,application/pdf"
                            className="h-11"
                            onChange={async (event) => {
                                const file = event.target.files?.[0];
                                setData(
                                    'photo',
                                    file ? await prepareImage(file) : null,
                                );
                            }}
                        />
                    </FormField>
                )}

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
