import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { BalanceInWords } from '@/components/app/BalanceInWords';
import type { HistoryRow } from '@/components/app/ChangeHistory';
import { ChangeHistory } from '@/components/app/ChangeHistory';
import type { KhataLine, Words } from '@/components/app/KhataTable';
import { KhataTable } from '@/components/app/KhataTable';
import { MoneyText } from '@/components/app/MoneyText';
import type { PartyType } from '@/components/app/PartyPicker';
import { PageHeader } from '@/components/app/PageHeader';
import { StatusBadge } from '@/components/app/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { edit, index, review as markReviewed, show } from '@/routes/parties';
import { create as newPurchase } from '@/routes/purchases';
import { create as newSale } from '@/routes/sales';
import { create as newPayment } from '@/routes/payments';
import { PARTY_TYPE_LABELS } from './types';

type Profile = {
    id: number;
    code: string;
    type: PartyType;
    status: 'active' | 'inactive';
    needs_review: boolean;
    balance: { words: string; amount: string; pending_kg: string };
    partner: { invested: string; withdrawn: string; net: string } | null;
    name: string;
    name_ur: string | null;
    business_name: string | null;
    contact_person: string | null;
    mobile: string;
    area: string | null;
    city: string | null;
    address: string | null;
    ntn: string | null;
    bank_name: string | null;
    bank_account_title: string | null;
    bank_account_number: string | null;
    credit_limit: string | null;
    payment_terms_days: number | null;
    profit_share_percent: string | null;
    joining_date: string | null;
    notes: string | null;
};

type BankFields = {
    bank_name?: string | null;
    bank_account_title?: string | null;
    bank_account_number?: string | null;
};

type Review = {
    new_party: boolean;
    bank_change: { old: BankFields; new: BankFields } | null;
    can_review: boolean;
};

const BANK_LABELS: Record<keyof BankFields, string> = {
    bank_name: 'Bank name',
    bank_account_title: 'Account title',
    bank_account_number: 'Account number',
};

type Khata = {
    from: string | null;
    to: string | null;
    opening: Words;
    closing: Words;
    rows: KhataLine[];
};

const TABS = ['Details', 'Khata', 'Change history'] as const;

/** Page 19 (spec §11.11): header with balance in words; tabs Details and Khata. */
export default function PartyProfile({
    party,
    khata,
    history,
    review,
}: {
    party: Profile;
    khata: Khata;
    history: HistoryRow[];
    review: Review;
}) {
    const can = useCan();
    const [tab, setTab] = useState<(typeof TABS)[number]>(
        khata.from || khata.to ? 'Khata' : 'Details',
    );
    const [range, setRange] = useState({
        from: khata.from ?? '',
        to: khata.to ?? '',
    });

    return (
        <>
            <Head title={party.name} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={`${party.code} – ${party.name}`}>
                    {party.type === 'supplier' &&
                        party.status === 'active' &&
                        can('purchase.create') && (
                            <Button asChild>
                                <Link
                                    href={newPurchase({
                                        query: { party_id: party.id },
                                    })}
                                >
                                    New purchase
                                </Link>
                            </Button>
                        )}
                    {party.type === 'buyer' &&
                        party.status === 'active' &&
                        can('sale.create') && (
                            <Button asChild>
                                <Link
                                    href={newSale({
                                        query: { party_id: party.id },
                                    })}
                                >
                                    New sale
                                </Link>
                            </Button>
                        )}
                    {party.type !== 'partner' &&
                        party.status === 'active' &&
                        can('payment.create') && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={newPayment({
                                        query: { party_id: party.id },
                                    })}
                                >
                                    New payment
                                </Link>
                            </Button>
                        )}
                    {can('party.update') && (
                        <Button variant="outline" asChild>
                            <Link href={edit(party.id)}>Edit</Link>
                        </Button>
                    )}
                </PageHeader>

                <div className="flex flex-wrap items-center gap-4 rounded-md border p-4">
                    <Badge variant="outline">
                        {PARTY_TYPE_LABELS[party.type]}
                    </Badge>
                    {party.status === 'inactive' && (
                        <Badge variant="outline">Inactive</Badge>
                    )}
                    {party.needs_review && (
                        <StatusBadge status="needs_review" />
                    )}
                    <BalanceInWords
                        words={party.balance.words}
                        amount={party.balance.amount}
                        pendingKg={party.balance.pending_kg}
                        className="text-lg font-semibold"
                    />
                    {party.partner && (
                        <span className="flex gap-4 text-sm">
                            <span>
                                Invested{' '}
                                <MoneyText value={party.partner.invested} />
                            </span>
                            <span>
                                Withdrawn{' '}
                                <MoneyText value={party.partner.withdrawn} />
                            </span>
                        </span>
                    )}
                </div>

                {(review.new_party || review.bank_change) && (
                    <div className="grid gap-3 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                        {review.new_party && (
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-medium">
                                    Needs review
                                </span>
                                {review.can_review && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                markReviewed.url(party.id),
                                                { what: 'new_party' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Mark reviewed
                                    </Button>
                                )}
                            </div>
                        )}
                        {review.bank_change && (
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <span className="font-medium">
                                        Bank details changed
                                    </span>
                                    <ul className="mt-1">
                                        {(
                                            Object.keys(
                                                BANK_LABELS,
                                            ) as (keyof BankFields)[]
                                        )
                                            .filter(
                                                (key) =>
                                                    key in
                                                        review.bank_change!
                                                            .new ||
                                                    key in
                                                        review.bank_change!.old,
                                            )
                                            .map((key) => (
                                                <li key={key}>
                                                    {BANK_LABELS[key]}:{' '}
                                                    <span className="line-through">
                                                        {review.bank_change!
                                                            .old[key] ?? '–'}
                                                    </span>{' '}
                                                    →{' '}
                                                    {review.bank_change!.new[
                                                        key
                                                    ] ?? '–'}
                                                </li>
                                            ))}
                                    </ul>
                                </div>
                                {review.can_review && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                markReviewed.url(party.id),
                                                { what: 'bank_change' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Mark reviewed
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                )}

                <div role="tablist" className="flex gap-1 border-b">
                    {TABS.map((name) => (
                        <button
                            key={name}
                            role="tab"
                            type="button"
                            aria-selected={tab === name}
                            onClick={() => setTab(name)}
                            className={cn(
                                '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
                                tab === name
                                    ? 'border-brand-green text-foreground'
                                    : 'border-transparent text-muted-foreground',
                            )}
                        >
                            {name}
                        </button>
                    ))}
                </div>

                {tab === 'Details' && (
                    <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Detail label="Mobile">{party.mobile}</Detail>
                        <Detail label="Urdu name">
                            <span dir="rtl" lang="ur">
                                {party.name_ur}
                            </span>
                        </Detail>
                        <Detail label="Business name">
                            {party.business_name}
                        </Detail>
                        <Detail label="Contact person">
                            {party.contact_person}
                        </Detail>
                        <Detail label="Area">{party.area}</Detail>
                        <Detail label="City">{party.city}</Detail>
                        <Detail label="Address">{party.address}</Detail>
                        <Detail label="NTN">{party.ntn}</Detail>
                        <Detail label="Bank name">{party.bank_name}</Detail>
                        <Detail label="Account title">
                            {party.bank_account_title}
                        </Detail>
                        <Detail label="Account number">
                            {party.bank_account_number}
                        </Detail>
                        {party.type === 'buyer' && (
                            <>
                                <Detail label="Credit limit">
                                    {party.credit_limit && (
                                        <MoneyText value={party.credit_limit} />
                                    )}
                                </Detail>
                                <Detail label="Payment terms (days)">
                                    {party.payment_terms_days}
                                </Detail>
                            </>
                        )}
                        {party.type === 'partner' && (
                            <>
                                <Detail label="Share (%)">
                                    {party.profit_share_percent}
                                </Detail>
                                <Detail label="Joining date">
                                    {party.joining_date}
                                </Detail>
                            </>
                        )}
                        <Detail label="Notes">{party.notes}</Detail>
                    </dl>
                )}

                {tab === 'Change history' && <ChangeHistory rows={history} />}

                {tab === 'Khata' && (
                    <div className="grid gap-3">
                        <form
                            className="flex flex-wrap gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const query = Object.fromEntries(
                                    Object.entries(range).filter(
                                        ([, v]) => v !== '',
                                    ),
                                );
                                router.get(
                                    show.url(party.id, { query }),
                                    {},
                                    { preserveState: true, replace: true },
                                );
                            }}
                        >
                            <Input
                                type="date"
                                aria-label="From"
                                className="w-44"
                                value={range.from}
                                onChange={(e) =>
                                    setRange({ ...range, from: e.target.value })
                                }
                            />
                            <Input
                                type="date"
                                aria-label="To"
                                className="w-44"
                                value={range.to}
                                onChange={(e) =>
                                    setRange({ ...range, to: e.target.value })
                                }
                            />
                            <Button type="submit" variant="outline">
                                Show
                            </Button>
                        </form>
                        <KhataTable
                            opening={khata.opening}
                            rows={khata.rows}
                            closing={khata.closing}
                            showOpening={khata.from !== null}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium">{children || '–'}</dd>
        </div>
    );
}

PartyProfile.layout = {
    breadcrumbs: [{ title: 'Parties', href: index() }],
};
