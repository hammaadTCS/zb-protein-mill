import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { StatusBadge } from '@/components/app/StatusBadge';
import { useFormats } from '@/hooks/use-formats';
import { formatDate, formatRate } from '@/lib/format';
import { index } from '@/routes/purchases';

type Purchase = {
    id: number;
    voucher_no: string;
    entry_date: string;
    party: { id: number; code: string; name: string };
    item: string;
    kg: string;
    rate: string | null;
    amount: string | null;
    rate_status: 'fixed' | 'pending';
    slip_no: string | null;
    our_weight_kg: string | null;
    note: string | null;
    status: 'active' | 'cancelled';
    cancel_reason: string | null;
    created_by: string | null;
    created_at: string | null;
};

type Attachment = {
    id: number;
    kind: string;
    name: string;
    mime_type: string;
    url: string;
};
type LinkedPayment = {
    id: number;
    voucher_no: string;
    amount: string;
    account: string;
    status: string;
    approval_status: string;
};

/** Page 5 (spec §11.6): every field, read-only. Edit, add rate and cancel come with T3.1 and T3.2. */
export default function ShowPurchase({
    purchase,
    attachments,
    payments,
}: {
    purchase: Purchase;
    attachments: Attachment[];
    payments: LinkedPayment[];
}) {
    const formats = useFormats();

    return (
        <>
            <Head title={purchase.voucher_no} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title={`Purchase ${purchase.voucher_no}`}>
                    {purchase.status === 'cancelled' && (
                        <StatusBadge status="cancelled" />
                    )}
                    {purchase.status === 'active' &&
                        purchase.rate_status === 'pending' && (
                            <StatusBadge status="rate_pending" />
                        )}
                </PageHeader>

                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <Field label="Date">
                        {formatDate(purchase.entry_date, formats.dateFormat)}
                    </Field>
                    <Field label="Supplier">
                        {purchase.party.code} – {purchase.party.name}
                    </Field>
                    <Field label="Item">{purchase.item}</Field>
                    <Field label="Kg">
                        <KgText value={purchase.kg} />
                    </Field>
                    <Field label="Rate">
                        {purchase.rate !== null
                            ? formatRate(
                                  purchase.rate,
                                  formats.rateDecimals,
                                  formats.numberFormat,
                              )
                            : 'Rate pending'}
                    </Field>
                    <Field label="Amount">
                        {purchase.amount !== null ? (
                            <MoneyText value={purchase.amount} />
                        ) : (
                            '–'
                        )}
                    </Field>
                    <Field label="Weight slip number">
                        {purchase.slip_no ?? '–'}
                    </Field>
                    {purchase.our_weight_kg !== null && (
                        <Field label="Our weight">
                            <KgText value={purchase.our_weight_kg} />
                        </Field>
                    )}
                    <Field label="Note">{purchase.note ?? '–'}</Field>
                    <Field label="Created by">
                        {purchase.created_by ?? '–'}
                        {purchase.created_at && (
                            <span className="text-muted-foreground">
                                {' '}
                                ·{' '}
                                {new Date(purchase.created_at).toLocaleString(
                                    'en-GB',
                                    { timeZone: 'Asia/Karachi' },
                                )}
                            </span>
                        )}
                    </Field>
                    {purchase.cancel_reason && (
                        <Field label="Reason">{purchase.cancel_reason}</Field>
                    )}
                </dl>

                {payments.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Paid now</h2>
                        {payments.map((payment) => (
                            <div
                                key={payment.id}
                                className="flex justify-between rounded-md border px-4 py-3 text-sm"
                            >
                                <span className="tabular-nums">
                                    {payment.voucher_no} · {payment.account}
                                </span>
                                <span className="flex items-center gap-2">
                                    {payment.status === 'cancelled' && (
                                        <StatusBadge status="cancelled" />
                                    )}
                                    {payment.approval_status === 'pending' && (
                                        <StatusBadge status="awaiting_approval" />
                                    )}
                                    <MoneyText value={payment.amount} />
                                </span>
                            </div>
                        ))}
                    </section>
                )}

                {attachments.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Slip photo</h2>
                        <div className="flex flex-wrap gap-3">
                            {attachments.map((file) =>
                                file.mime_type.startsWith('image/') ? (
                                    <a
                                        key={file.id}
                                        href={file.url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            src={file.url}
                                            alt={file.name}
                                            className="h-40 rounded-md border object-cover"
                                        />
                                    </a>
                                ) : (
                                    <a
                                        key={file.id}
                                        href={file.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="underline"
                                    >
                                        {file.name}
                                    </a>
                                ),
                            )}
                        </div>
                    </section>
                )}

                <Link
                    href={index()}
                    className="text-sm underline-offset-4 hover:underline"
                >
                    Purchases
                </Link>
            </div>
        </>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium">{children}</dd>
        </div>
    );
}

ShowPurchase.layout = {
    breadcrumbs: [{ title: 'Purchases', href: index() }],
};
