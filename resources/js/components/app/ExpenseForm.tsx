import { Head, useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useRef, useState } from 'react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { PageHeader } from '@/components/app/PageHeader';
import type { PartyOption, PartyType } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { useWarnings } from '@/components/app/WarningDialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { prepareImage } from '@/lib/image';
import { compareDecimals } from '@/lib/money';
import { cn } from '@/lib/utils';
import { newIdempotencyKey } from '@/lib/uuid';
import { linkLookup, store, update } from '@/routes/expenses';

type Mode = 'paid' | 'unpaid' | 'paid_by_employee';
type AccountOption = { id: number; name: string; type: 'cash' | 'bank' };
type Category = { id: number; name: string; is_quick: boolean };
export type LinkedEntry = {
    type: 'purchase' | 'sale';
    id: number;
    voucher_no: string;
    label: string;
};

export type EditedExpense = {
    id: number;
    voucher_no: string;
    version: string;
    date: string;
    category_id: number;
    amount: string;
    payment_mode: Mode;
    money_account_id: number | null;
    party_id: number | null;
    handled_by_party_id: number | null;
    linked_purchase_id: number | null;
    linked_sale_id: number | null;
    note: string;
};

export type ExpenseFormProps = {
    today: string;
    money_accounts: AccountOption[];
    categories: Category[];
    party_required_limit: string | null;
    defaults?: {
        date: string;
        payment_mode: Mode;
        money_account_id: number | null;
    };
    party?: PartyOption | null;
    handled_by?: PartyOption | null;
    link?: LinkedEntry | null;
    expense?: EditedExpense;
};

type FormData = {
    idempotency_key: string;
    date: string;
    category_id: number | null;
    amount: string;
    note: string;
    payment_mode: Mode;
    money_account_id: string;
    party_id: number | null;
    handled_by_party_id: number | null;
    linked_purchase_id: number | null;
    linked_sale_id: number | null;
    photo: File | null;
};

const MODES: { value: Mode; label: string }[] = [
    { value: 'paid', label: 'Paid' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'paid_by_employee', label: 'Paid by employee' },
];

/** BR-EXP-05, BR-EXP-06: the party types each payment mode accepts. */
const PARTY_TYPES: Record<Mode, PartyType[]> = {
    paid: ['supplier', 'other', 'employee'],
    unpaid: ['supplier', 'other', 'employee'],
    paid_by_employee: ['employee'],
};

/**
 * Page 13, Quick expense, and its edit page (spec §11.9), mobile-first: quick
 * category buttons, amount, note, payment mode and account; party, link, handled by
 * and receipt photo under "More". Opens with today, "Paid" and the default cash
 * account (BR-EXP-09).
 */
export function ExpenseForm({
    today,
    money_accounts,
    categories,
    party_required_limit,
    defaults,
    party,
    handled_by,
    link,
    expense,
}: ExpenseFormProps) {
    const defaultAccount = defaults?.money_account_id
        ? String(defaults.money_account_id)
        : '';
    const blank = (): FormData => ({
        idempotency_key: newIdempotencyKey(), // BR-GEN-11
        date: defaults?.date ?? today,
        category_id: null,
        amount: '',
        note: '',
        payment_mode: defaults?.payment_mode ?? 'paid',
        money_account_id: defaultAccount,
        party_id: null,
        handled_by_party_id: null,
        linked_purchase_id: null,
        linked_sale_id: null,
        photo: null,
    });

    const form = useForm<FormData>(
        expense
            ? {
                  idempotency_key: '',
                  date: expense.date,
                  category_id: expense.category_id,
                  amount: expense.amount,
                  note: expense.note,
                  payment_mode: expense.payment_mode,
                  money_account_id: expense.money_account_id
                      ? String(expense.money_account_id)
                      : '',
                  party_id: expense.party_id,
                  handled_by_party_id: expense.handled_by_party_id,
                  linked_purchase_id: expense.linked_purchase_id,
                  linked_sale_id: expense.linked_sale_id,
                  photo: null,
              }
            : blank(),
    );
    const { data, setData, errors } = form;
    const [picked, setPicked] = useState<PartyOption | null>(party ?? null);
    const [handledBy, setHandledBy] = useState<PartyOption | null>(
        handled_by ?? null,
    );
    const [linked, setLinked] = useState<LinkedEntry | null>(link ?? null);
    const [voucher, setVoucher] = useState(link?.voucher_no ?? '');
    const [linkError, setLinkError] = useState<string | null>(null);
    const [more, setMore] = useState(
        !!(party || handled_by || link) && !!expense,
    );
    const [photoKey, setPhotoKey] = useState(0);
    const [conflict, setConflict] = useState<string | null>(null);
    const addAnother = useRef(false);

    const quick = categories.filter((c) => c.is_quick);
    const atLimit =
        party_required_limit !== null &&
        (compareDecimals(data.amount, party_required_limit) ?? -1) >= 0;
    const partyRequired = data.payment_mode !== 'paid' || atLimit; // BR-EXP-03, 05, 06

    const pickParty = (option: PartyOption | null) => {
        setPicked(option);
        setData('party_id', option?.id ?? null);
    };

    const setMode = (mode: Mode) => {
        form.setData((current) => ({
            ...current,
            payment_mode: mode,
            money_account_id:
                mode === 'paid'
                    ? current.money_account_id || defaultAccount
                    : '',
        }));

        if (picked && !PARTY_TYPES[mode].includes(picked.type)) {
            pickParty(null);
        }
    };

    const findLink = async () => {
        const value = voucher.trim();
        setLinkError(null);

        if (value === '') {
            setLinked(null);
            form.setData((current) => ({
                ...current,
                linked_purchase_id: null,
                linked_sale_id: null,
            }));

            return;
        }

        const response = await fetch(
            linkLookup.url({ query: { voucher_no: value } }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );
        const found = response.ok
            ? ((await response.json()) as LinkedEntry | null)
            : null;

        setLinked(found);
        form.setData((current) => ({
            ...current,
            linked_purchase_id: found?.type === 'purchase' ? found.id : null,
            linked_sale_id: found?.type === 'sale' ? found.id : null,
        }));

        if (!found) {
            setLinkError('No active purchase or sale has this voucher number.');
        }
    };

    const warnings = useWarnings((confirmed, onHttpException) => {
        if (expense) {
            form.transform((fields) => {
                const {
                    idempotency_key: _key,
                    photo: _photo,
                    ...rest
                } = fields;

                return {
                    ...rest,
                    money_account_id: rest.money_account_id || null,
                    version: expense.version,
                    confirmed_warnings: confirmed,
                };
            });
            form.put(update.url(expense.id), {
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
                money_account_id: rest.money_account_id || null,
                ...(photo ? { photo } : {}),
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
                    setPicked(null);
                    setHandledBy(null);
                    setLinked(null);
                    setVoucher('');
                    setMore(false);
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

    const partyField = (
        <FormField
            id="party_id"
            label={partyRequired ? 'Party' : 'Party (optional)'}
            error={errors.party_id}
        >
            <PartyPicker
                id="party_id"
                types={PARTY_TYPES[data.payment_mode]}
                value={picked}
                placeholder="Select party"
                invalid={!!errors.party_id}
                onChange={pickParty}
            />
        </FormField>
    );

    const title = expense ? `Edit ${expense.voucher_no}` : 'Quick expense';

    return (
        <>
            <Head title={title} />
            <form
                className="mx-auto flex w-full max-w-xl flex-col gap-5 p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    save(false);
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

                <FormField
                    id="category_id"
                    label="Category"
                    error={errors.category_id}
                >
                    {quick.length > 0 && (
                        <div
                            role="radiogroup"
                            aria-label="Quick category"
                            className="grid grid-cols-3 gap-2"
                        >
                            {quick.map((category) => (
                                <button
                                    key={category.id}
                                    type="button"
                                    role="radio"
                                    aria-checked={
                                        data.category_id === category.id
                                    }
                                    onClick={() =>
                                        setData('category_id', category.id)
                                    }
                                    className={cn(
                                        'h-12 rounded-md border px-2 text-sm font-semibold',
                                        data.category_id === category.id
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'text-foreground',
                                    )}
                                >
                                    {category.name}
                                </button>
                            ))}
                        </div>
                    )}
                    <Select
                        value={data.category_id ? String(data.category_id) : ''}
                        onValueChange={(value) =>
                            setData('category_id', Number(value))
                        }
                    >
                        <SelectTrigger id="category_id" className="h-11 w-full">
                            <SelectValue placeholder="Select category" />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={String(category.id)}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField id="amount" label="Amount" error={errors.amount}>
                    <DecimalInput
                        id="amount"
                        value={data.amount}
                        onChange={(value) => setData('amount', value)}
                        required
                    />
                </FormField>

                <FormField id="note" label="Note" error={errors.note}>
                    <Textarea
                        id="note"
                        rows={2}
                        maxLength={2000}
                        value={data.note}
                        onChange={(e) => setData('note', e.target.value)}
                        required
                    />
                </FormField>

                <div
                    role="radiogroup"
                    aria-label="Payment mode"
                    className="grid grid-cols-3 gap-2"
                >
                    {MODES.map((mode) => (
                        <button
                            key={mode.value}
                            type="button"
                            role="radio"
                            aria-checked={data.payment_mode === mode.value}
                            onClick={() => setMode(mode.value)}
                            className={cn(
                                'h-12 rounded-md border px-2 text-sm font-semibold',
                                data.payment_mode === mode.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {mode.label}
                        </button>
                    ))}
                </div>
                {errors.payment_mode && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {errors.payment_mode}
                    </p>
                )}

                {data.payment_mode === 'paid' && (
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
                )}
                {data.payment_mode !== 'paid' && errors.money_account_id && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {errors.money_account_id}
                    </p>
                )}

                {partyRequired && partyField}

                <Collapsible open={more} onOpenChange={setMore}>
                    <CollapsibleTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            className="w-full justify-between"
                        >
                            More
                            <ChevronDown
                                className={cn(
                                    'size-4 transition-transform',
                                    more && 'rotate-180',
                                )}
                            />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="flex flex-col gap-5 pt-3">
                        {!partyRequired && partyField}

                        <FormField
                            id="link_voucher"
                            label="Link to purchase or sale"
                            error={
                                linkError ??
                                errors.linked_purchase_id ??
                                errors.linked_sale_id
                            }
                        >
                            <div className="flex gap-2">
                                <Input
                                    id="link_voucher"
                                    className="h-11"
                                    placeholder="PUR-000001 or SAL-000001"
                                    maxLength={20}
                                    value={voucher}
                                    onChange={(e) => setVoucher(e.target.value)}
                                    onBlur={findLink}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-11"
                                    onClick={findLink}
                                >
                                    Find
                                </Button>
                            </div>
                            {linked && (
                                <p className="text-sm text-muted-foreground">
                                    {linked.label}
                                </p>
                            )}
                        </FormField>

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
                                    setData(
                                        'handled_by_party_id',
                                        option?.id ?? null,
                                    );
                                }}
                            />
                        </FormField>

                        {!expense && (
                            <FormField
                                id="photo"
                                label="Receipt photo"
                                error={
                                    (
                                        errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).photo
                                }
                            >
                                <Input
                                    key={photoKey}
                                    id="photo"
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    className="h-11"
                                    onChange={async (event) => {
                                        const file = event.target.files?.[0];
                                        setData(
                                            'photo',
                                            file
                                                ? await prepareImage(file)
                                                : null,
                                        );
                                    }}
                                />
                            </FormField>
                        )}
                    </CollapsibleContent>
                </Collapsible>

                <div className="grid gap-3 sm:grid-cols-2">
                    <Button
                        type="submit"
                        size="lg"
                        className="h-12"
                        disabled={form.processing}
                    >
                        Save
                    </Button>
                    {!expense && (
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
