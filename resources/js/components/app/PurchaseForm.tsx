import { Head, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import type { PartyOption } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { useWarnings } from '@/components/app/WarningDialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Textarea } from '@/components/ui/textarea';
import { useFormats } from '@/hooks/use-formats';
import { prepareImage } from '@/lib/image';
import { previewAmount } from '@/lib/money';
import { newIdempotencyKey } from '@/lib/uuid';
import { store, update } from '@/routes/purchases';

type Option = { id: number; name: string };
type AccountOption = Option & { type: 'cash' | 'bank' };

type PurchaseForm = {
    idempotency_key: string;
    date: string;
    party_id: number | null;
    item_id: string;
    kg: string;
    rate: string;
    slip_no: string;
    slip_photo: File | null;
    our_weight_kg: string;
    note: string;
    paid_now: {
        amount: string;
        money_account_id: string;
        reference_no: string;
        handled_by_party_id: number | null;
    };
};

/** The saved values of a purchase being edited (BR-PUR-11). */
export type EditedPurchase = {
    id: number;
    voucher_no: string;
    version: string;
    date: string;
    party_id: number;
    item_id: number;
    kg: string;
    rate: string | null;
    slip_no: string | null;
    our_weight_kg: string | null;
    note: string | null;
};

export type PurchaseFormProps = {
    today: string;
    party: PartyOption | null;
    items: Option[];
    money_accounts: AccountOption[];
    plant_has_scale: boolean;
    purchase?: EditedPurchase;
};

/**
 * Pages 4 and its edit page (spec §11.4), mobile-first: fields in the order of the
 * spec. Editing has no "Paid now", no photo and no "Save and add another"; it sends
 * `version` so a second editor is refused (BR-GEN-09).
 */
export function PurchaseForm({
    today,
    party,
    items,
    money_accounts,
    plant_has_scale,
    purchase,
}: PurchaseFormProps) {
    const formats = useFormats();
    const [supplier, setSupplier] = useState<PartyOption | null>(party);
    const [handledBy, setHandledBy] = useState<PartyOption | null>(null);
    const [paidNow, setPaidNow] = useState(false);
    const [photoKey, setPhotoKey] = useState(0);
    const addAnother = useRef(false);
    const [conflict, setConflict] = useState<string | null>(null);
    const editing = purchase !== undefined;

    const blank = (): PurchaseForm => ({
        idempotency_key: newIdempotencyKey(), // BR-GEN-11: one key per opened form
        date: purchase?.date ?? today,
        party_id: purchase?.party_id ?? party?.id ?? null,
        item_id: purchase
            ? String(purchase.item_id)
            : items.length === 1
              ? String(items[0].id)
              : '',
        kg: purchase?.kg ?? '',
        rate: purchase?.rate ?? '',
        slip_no: purchase?.slip_no ?? '',
        slip_photo: null,
        our_weight_kg: purchase?.our_weight_kg ?? '',
        note: purchase?.note ?? '',
        paid_now: {
            amount: '',
            money_account_id:
                money_accounts.length === 1 ? String(money_accounts[0].id) : '',
            reference_no: '',
            handled_by_party_id: null,
        },
    });

    const form = useForm<PurchaseForm>(blank());
    const { data, setData } = form;
    const errors = form.errors as Record<string, string | undefined>;
    const total = previewAmount(data.kg, data.rate, formats.amountRounding);

    const warnings = useWarnings((confirmed, onHttpException) => {
        if (purchase) {
            form.transform((fields) => {
                const {
                    paid_now: _paid,
                    slip_photo: _photo,
                    idempotency_key: _key,
                    ...rest
                } = fields;

                return {
                    ...rest,
                    version: purchase.version,
                    confirmed_warnings: confirmed,
                };
            });
            form.put(update.url(purchase.id), {
                onHttpException: (response) => {
                    if (response.status === 409) {
                        // BR-GEN-09: someone else saved this entry first.
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
            const { paid_now, slip_photo, ...rest } = fields;

            return {
                ...rest,
                ...(paidNow ? { paid_now } : {}),
                ...(slip_photo ? { slip_photo } : {}),
                add_another: addAnother.current,
                confirmed_warnings: confirmed,
            };
        });
        form.post(store.url(), {
            onHttpException,
            onSuccess: () => {
                if (addAnother.current) {
                    // "Save and add another": a fresh form with a new key.
                    form.setDefaults(blank());
                    form.reset();
                    setSupplier(null);
                    setHandledBy(null);
                    setPaidNow(false);
                    setPhotoKey((key) => key + 1);
                    warnings.reset();
                }
            },
        });
    });

    const save = (another: boolean) => {
        addAnother.current = another;
        warnings.submit();
    };

    return (
        <>
            <Head
                title={editing ? `Edit ${purchase.voucher_no}` : 'New purchase'}
            />
            <form
                className="mx-auto flex w-full max-w-xl flex-col gap-5 p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    save(false);
                }}
            >
                <PageHeader
                    title={
                        editing ? `Edit ${purchase.voucher_no}` : 'New purchase'
                    }
                />

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
                        value={data.date}
                        max={today}
                        onChange={(event) =>
                            setData('date', event.target.value)
                        }
                        required
                    />
                </FormField>

                <FormField
                    id="party_id"
                    label="Supplier"
                    error={errors.party_id}
                >
                    <PartyPicker
                        id="party_id"
                        types={['supplier']}
                        value={supplier}
                        placeholder="Select supplier"
                        invalid={!!errors.party_id}
                        onChange={(party) => {
                            setSupplier(party);
                            setData('party_id', party?.id ?? null);
                        }}
                    />
                </FormField>

                <FormField id="item_id" label="Item" error={errors.item_id}>
                    <Select
                        value={data.item_id}
                        onValueChange={(value) => setData('item_id', value)}
                    >
                        <SelectTrigger id="item_id" className="h-11 w-full">
                            <SelectValue placeholder="Select item" />
                        </SelectTrigger>
                        <SelectContent>
                            {items.map((item) => (
                                <SelectItem
                                    key={item.id}
                                    value={String(item.id)}
                                >
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <div className="grid grid-cols-2 gap-4">
                    <FormField id="kg" label="Kg" error={errors.kg}>
                        <DecimalInput
                            id="kg"
                            value={data.kg}
                            onChange={(value) => setData('kg', value)}
                            required
                        />
                    </FormField>
                    <FormField id="rate" label="Rate" error={errors.rate}>
                        <DecimalInput
                            id="rate"
                            value={data.rate}
                            onChange={(value) => setData('rate', value)}
                        />
                    </FormField>
                </div>

                <div className="flex items-center justify-between rounded-md bg-muted px-4 py-3">
                    <span className="text-sm text-muted-foreground">Total</span>
                    {total !== null ? (
                        <MoneyText
                            value={total}
                            className="text-lg font-semibold"
                        />
                    ) : (
                        <span className="font-medium">
                            {data.rate === '' ? 'Rate pending' : '–'}
                        </span>
                    )}
                </div>

                <FormField
                    id="slip_no"
                    label="Weight slip number"
                    error={errors.slip_no}
                >
                    <Input
                        id="slip_no"
                        className="h-11"
                        value={data.slip_no}
                        maxLength={50}
                        onChange={(event) =>
                            setData('slip_no', event.target.value)
                        }
                    />
                </FormField>

                {!editing && (
                    <FormField
                        id="slip_photo"
                        label="Slip photo"
                        error={errors.slip_photo}
                    >
                        <Input
                            key={photoKey}
                            id="slip_photo"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="h-11"
                            onChange={async (event) => {
                                const file = event.target.files?.[0];
                                setData(
                                    'slip_photo',
                                    file ? await prepareImage(file) : null,
                                );
                            }}
                        />
                    </FormField>
                )}

                {plant_has_scale && (
                    <FormField
                        id="our_weight_kg"
                        label="Our weight (kg)"
                        error={errors.our_weight_kg}
                    >
                        <DecimalInput
                            id="our_weight_kg"
                            value={data.our_weight_kg}
                            onChange={(value) =>
                                setData('our_weight_kg', value)
                            }
                        />
                    </FormField>
                )}

                <FormField id="note" label="Note" error={errors.note}>
                    <Textarea
                        id="note"
                        value={data.note}
                        maxLength={2000}
                        onChange={(event) =>
                            setData('note', event.target.value)
                        }
                    />
                </FormField>

                {!editing && (
                    <div className="flex items-center gap-3 rounded-md border px-4 py-3">
                        <Checkbox
                            id="paid_now_on"
                            checked={paidNow}
                            onCheckedChange={(checked) =>
                                setPaidNow(checked === true)
                            }
                            className="size-5"
                        />
                        <Label htmlFor="paid_now_on" className="text-base">
                            Paid now
                        </Label>
                    </div>
                )}

                {!editing && paidNow && (
                    <div className="grid gap-4 rounded-md border p-4">
                        <FormField
                            id="paid_amount"
                            label="Amount"
                            error={errors['paid_now.amount']}
                        >
                            <DecimalInput
                                id="paid_amount"
                                value={data.paid_now.amount}
                                onChange={(value) =>
                                    setData('paid_now', {
                                        ...data.paid_now,
                                        amount: value,
                                    })
                                }
                            />
                        </FormField>
                        <FormField
                            id="paid_account"
                            label="Account"
                            error={errors['paid_now.money_account_id']}
                        >
                            <Select
                                value={data.paid_now.money_account_id}
                                onValueChange={(value) =>
                                    setData('paid_now', {
                                        ...data.paid_now,
                                        money_account_id: value,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="paid_account"
                                    className="h-11 w-full"
                                >
                                    <SelectValue placeholder="Select account" />
                                </SelectTrigger>
                                <SelectContent>
                                    {money_accounts.map((account) => (
                                        <SelectItem
                                            key={account.id}
                                            value={String(account.id)}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="paid_reference"
                            label="Reference number"
                            error={errors['paid_now.reference_no']}
                        >
                            <Input
                                id="paid_reference"
                                className="h-11"
                                maxLength={100}
                                value={data.paid_now.reference_no}
                                onChange={(event) =>
                                    setData('paid_now', {
                                        ...data.paid_now,
                                        reference_no: event.target.value,
                                    })
                                }
                            />
                        </FormField>
                        <FormField
                            id="paid_handled_by"
                            label="Handled by"
                            error={errors['paid_now.handled_by_party_id']}
                        >
                            <PartyPicker
                                id="paid_handled_by"
                                types={['employee']}
                                value={handledBy}
                                placeholder="Select employee"
                                onChange={(party) => {
                                    setHandledBy(party);
                                    setData('paid_now', {
                                        ...data.paid_now,
                                        handled_by_party_id: party?.id ?? null,
                                    });
                                }}
                            />
                        </FormField>
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2">
                    <Button
                        type="submit"
                        size="lg"
                        className="h-12"
                        disabled={form.processing}
                    >
                        Save
                    </Button>
                    {!editing && (
                        <Button
                            type="button"
                            size="lg"
                            variant="outline"
                            className="h-12"
                            disabled={form.processing}
                            onClick={() => save(true)}
                        >
                            Save and add another
                        </Button>
                    )}
                </div>
            </form>
            {warnings.dialog}
        </>
    );
}
