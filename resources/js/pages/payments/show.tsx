import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { StatusBadge } from '@/components/app/StatusBadge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { cancel, edit, index } from '@/routes/payments';
import { show as showPurchase } from '@/routes/purchases';
import { show as showSale } from '@/routes/sales';

type Payment = {
    id: number;
    voucher_no: string;
    entry_date: string;
    direction: 'paid' | 'received';
    party: { id: number; code: string; name: string };
    amount: string;
    account: string;
    method: string;
    reference_no: string | null;
    handled_by: string | null;
    note: string | null;
    status: 'active' | 'cancelled';
    approval_status: 'not_required' | 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    cancel_reason: string | null;
    created_by: string | null;
    created_at: string | null;
    is_active: boolean;
    linked: {
        type: 'purchase' | 'sale';
        id: number;
        voucher_no: string;
    } | null;
};

type Attachment = { id: number; name: string; mime_type: string; url: string };

/** Page 11 (spec §11.6): every field, with Edit and Cancel entry. */
export default function ShowPayment({
    payment,
    attachments,
}: {
    payment: Payment;
    attachments: Attachment[];
}) {
    const can = useCan();
    const formats = useFormats();
    const [cancelling, setCancelling] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const label = payment.direction === 'paid' ? 'Payment' : 'Receipt';

    return (
        <>
            <Head title={payment.voucher_no} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title={`${label} ${payment.voucher_no}`}>
                    {payment.status === 'cancelled' && (
                        <StatusBadge status="cancelled" />
                    )}
                    {payment.is_active &&
                        payment.approval_status === 'pending' && (
                            <StatusBadge status="awaiting_approval" />
                        )}
                    {payment.approval_status === 'rejected' && (
                        <StatusBadge status="rejected" />
                    )}
                    {payment.is_active &&
                        payment.approval_status !== 'rejected' &&
                        can('payment.update') && (
                            <Button variant="outline" asChild>
                                <Link href={edit(payment.id)}>Edit</Link>
                            </Button>
                        )}
                    {payment.is_active && can('payment.cancel') && (
                        <Button
                            variant="outline"
                            onClick={() => setCancelling(true)}
                        >
                            Cancel entry
                        </Button>
                    )}
                </PageHeader>

                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <Field label="Date">
                        {formatDate(payment.entry_date, formats.dateFormat)}
                    </Field>
                    <Field label="Party">
                        {payment.party.code} – {payment.party.name}
                    </Field>
                    <Field label="Amount">
                        <MoneyText value={payment.amount} />
                    </Field>
                    <Field label="Account">
                        {payment.account} ({payment.method})
                    </Field>
                    <Field label="Reference number">
                        {payment.reference_no ?? '–'}
                    </Field>
                    <Field label="Handled by">
                        {payment.handled_by ?? '–'}
                    </Field>
                    <Field label="Note">{payment.note ?? '–'}</Field>
                    {payment.linked && (
                        <Field
                            label={
                                payment.linked.type === 'purchase'
                                    ? 'Purchase'
                                    : 'Sale'
                            }
                        >
                            <Link
                                href={
                                    payment.linked.type === 'purchase'
                                        ? showPurchase(payment.linked.id)
                                        : showSale(payment.linked.id)
                                }
                                className="underline-offset-4 hover:underline"
                            >
                                {payment.linked.voucher_no}
                            </Link>
                        </Field>
                    )}
                    <Field label="Created by">
                        {payment.created_by ?? '–'}
                        {payment.created_at && (
                            <span className="text-muted-foreground">
                                {' '}
                                ·{' '}
                                {new Date(payment.created_at).toLocaleString(
                                    'en-GB',
                                    { timeZone: 'Asia/Karachi' },
                                )}
                            </span>
                        )}
                    </Field>
                    {payment.rejection_reason && (
                        <Field label="Reason">{payment.rejection_reason}</Field>
                    )}
                    {payment.cancel_reason && (
                        <Field label="Reason">{payment.cancel_reason}</Field>
                    )}
                </dl>

                {attachments.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Photo</h2>
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
            </div>
            <ConfirmWithReason
                open={cancelling}
                title={`Cancel entry ${payment.voucher_no}`}
                error={error}
                onClose={() => setCancelling(false)}
                onConfirm={(reason) =>
                    router.post(
                        cancel.url(payment.id),
                        { reason },
                        {
                            onError: (errors) =>
                                setError(errors.reason ?? errors.date),
                            onSuccess: () => setCancelling(false),
                        },
                    )
                }
            />
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

ShowPayment.layout = {
    breadcrumbs: [{ title: 'Payments', href: index() }],
};
