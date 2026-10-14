import { Head, router, useForm } from '@inertiajs/react';
import { FormField } from '@/components/app/FormField';
import type { PartyType } from '@/components/app/PartyPicker';
import { PageHeader } from '@/components/app/PageHeader';
import { useWarnings } from '@/components/app/WarningDialog';
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
import { activate, deactivate, index, store, update } from '@/routes/parties';
import { PARTY_TYPES } from './types';

type PartyFields = {
    type: PartyType;
    name: string;
    name_ur: string;
    business_name: string;
    contact_person: string;
    mobile: string;
    area: string;
    city: string;
    address: string;
    ntn: string;
    bank_name: string;
    bank_account_title: string;
    bank_account_number: string;
    credit_limit: string;
    payment_terms_days: string;
    profit_share_percent: string;
    joining_date: string;
    notes: string;
};

type EditedParty = {
    id: number;
    public_id: string;
    code: string;
    status: 'active' | 'inactive';
    has_entries: boolean;
} & { [K in keyof PartyFields]: PartyFields[K] | number | null };

const EMPTY: PartyFields = {
    type: 'supplier',
    name: '',
    name_ur: '',
    business_name: '',
    contact_person: '',
    mobile: '',
    area: '',
    city: '',
    address: '',
    ntn: '',
    bank_name: '',
    bank_account_title: '',
    bank_account_number: '',
    credit_limit: '',
    payment_terms_days: '',
    profit_share_percent: '',
    joining_date: '',
    notes: '',
};

function initial(party: EditedParty | null): PartyFields {
    if (!party) {
        return EMPTY;
    }

    const fields = { ...EMPTY };

    for (const key of Object.keys(EMPTY) as (keyof PartyFields)[]) {
        const value = party[key];
        (fields as Record<string, string>)[key] =
            value === null || value === undefined ? '' : String(value);
    }

    return fields;
}

/** Page 18: add or edit a party; fields shown by type (spec §11.10). */
export default function PartyForm({ party }: { party: EditedParty | null }) {
    const form = useForm<PartyFields>(initial(party));
    const { data, setData, errors } = form;

    const warnings = useWarnings((confirmed, onHttpException) => {
        form.transform((fields) => ({
            ...fields,
            confirmed_warnings: confirmed,
        }));
        const options = { preserveScroll: true, onHttpException };

        if (party) {
            form.put(update.url(party.public_id), options);
        } else {
            form.post(store.url(), options);
        }
    });

    const text = (
        key: keyof PartyFields,
        label: string,
        props: React.ComponentProps<typeof Input> = {},
    ) => (
        <FormField id={key} label={label} error={errors[key]}>
            <Input
                id={key}
                value={data[key]}
                onChange={(event) => setData(key, event.target.value)}
                {...props}
            />
        </FormField>
    );

    const title = party ? `${party.code} – ${party.name}` : 'New party';

    return (
        <>
            <Head title={party ? 'Edit party' : 'New party'} />
            <form
                className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    warnings.submit();
                }}
            >
                <PageHeader title={title} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="type" label="Type" error={errors.type}>
                        <Select
                            value={data.type}
                            onValueChange={(value) =>
                                setData('type', value as PartyType)
                            }
                            disabled={party?.has_entries ?? false}
                        >
                            <SelectTrigger id="type" className="h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PARTY_TYPES.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={type.value}
                                    >
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    {text('mobile', 'Mobile', {
                        inputMode: 'tel',
                        required: true,
                        maxLength: 20,
                        autoComplete: 'off',
                    })}
                    {text('name', 'Name', { required: true, maxLength: 150 })}
                    <FormField
                        id="name_ur"
                        label="Urdu name"
                        error={errors.name_ur}
                    >
                        <Input
                            id="name_ur"
                            dir="rtl"
                            lang="ur"
                            value={data.name_ur}
                            onChange={(event) =>
                                setData('name_ur', event.target.value)
                            }
                            maxLength={150}
                        />
                    </FormField>
                    {text('business_name', 'Business name', { maxLength: 150 })}
                    {text('contact_person', 'Contact person', {
                        maxLength: 150,
                    })}
                    {text('area', 'Area', { maxLength: 100 })}
                    {text('city', 'City', { maxLength: 100 })}
                    {text('ntn', 'NTN', { maxLength: 30 })}
                </div>

                <FormField id="address" label="Address" error={errors.address}>
                    <Textarea
                        id="address"
                        value={data.address}
                        onChange={(event) =>
                            setData('address', event.target.value)
                        }
                        maxLength={1000}
                    />
                </FormField>

                <div className="grid gap-4 sm:grid-cols-3">
                    {text('bank_name', 'Bank name', { maxLength: 150 })}
                    {text('bank_account_title', 'Account title', {
                        maxLength: 150,
                    })}
                    {text('bank_account_number', 'Account number', {
                        maxLength: 150,
                    })}
                </div>

                {data.type === 'buyer' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('credit_limit', 'Credit limit (Rs)', {
                            inputMode: 'decimal',
                        })}
                        {text('payment_terms_days', 'Payment terms (days)', {
                            inputMode: 'numeric',
                        })}
                    </div>
                )}

                {data.type === 'partner' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('profit_share_percent', 'Share (%)', {
                            inputMode: 'decimal',
                        })}
                        {text('joining_date', 'Joining date', { type: 'date' })}
                    </div>
                )}

                <FormField id="notes" label="Notes" error={errors.notes}>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        maxLength={2000}
                    />
                </FormField>

                <div className="flex flex-wrap justify-between gap-2">
                    <Button type="submit" size="lg" disabled={form.processing}>
                        Save
                    </Button>
                    {party && (
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            onClick={() =>
                                router.post(
                                    party.status === 'active'
                                        ? deactivate.url(party.public_id)
                                        : activate.url(party.public_id),
                                )
                            }
                        >
                            {party.status === 'active'
                                ? 'Make inactive'
                                : 'Make active'}
                        </Button>
                    )}
                </div>
            </form>
            {warnings.dialog}
        </>
    );
}

PartyForm.layout = {
    breadcrumbs: [{ title: 'Parties', href: index() }],
};
