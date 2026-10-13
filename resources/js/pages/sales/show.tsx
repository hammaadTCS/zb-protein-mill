import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { AddRateDialog } from '@/components/app/AddRateDialog';
import { AdjustmentDialog } from '@/components/app/AdjustmentDialog';
import type { AdjustmentRow } from '@/components/app/AdjustmentList';
import { AdjustmentList } from '@/components/app/AdjustmentList';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import { KgText } from '@/components/app/KgText';
import type { HistoryRow } from '@/components/app/ChangeHistory';
import { ChangeHistory } from '@/components/app/ChangeHistory';
import type { JournalLineRow } from '@/components/app/JournalLines';
import { JournalLines } from '@/components/app/JournalLines';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { StatusBadge } from '@/components/app/StatusBadge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate, formatRate } from '@/lib/format';
import { cancel, edit, index, rate } from '@/routes/sales';
import { store as storeAdjustment } from '@/routes/sales/adjustments';

type Sale = {
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
    can_add_rate: boolean;
    is_active: boolean;
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

/** Page 8 (spec §11.6): every field, read-only.  */
export default function ShowSale({
    sale,
    attachments,
    payments,
    today,
    history,
    journal,
    adjustments,
    adjustment_max,
}: {
    sale: Sale;
    attachments: Attachment[];
    payments: LinkedPayment[];
    today: string;
    history: HistoryRow[];
    journal?: JournalLineRow[];
    adjustments: AdjustmentRow[];
    adjustment_max: string | null;
}) {
    const formats = useFormats();
    const can = useCan();
    const [addingRate, setAddingRate] = useState(false);
    const [adjusting, setAdjusting] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [cancelPayment, setCancelPayment] = useState(false);
    const [cancelError, setCancelError] = useState<string | undefined>();
    const [cancelProcessing, setCancelProcessing] = useState(false);
    const hasActivePayment = payments.some((p) => p.status === 'active');

    return (
        <>
            <Head title={sale.voucher_no} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title={`Sale ${sale.voucher_no}`}>
                    {sale.status === 'cancelled' && (
                        <StatusBadge status="cancelled" />
                    )}
                    {sale.status === 'active' &&
                        sale.rate_status === 'pending' && (
                            <StatusBadge status="rate_pending" />
                        )}
                    {sale.is_active && can('sale.update') && (
                        <Button variant="outline" asChild>
                            <Link href={edit(sale.id)}>Edit</Link>
                        </Button>
                    )}
                    {sale.is_active && can('sale.cancel') && (
                        <Button
                            variant="outline"
                            onClick={() => setCancelling(true)}
                        >
                            Cancel entry
                        </Button>
                    )}
                    {sale.can_add_rate && can('sale.update') && (
                        <Button onClick={() => setAddingRate(true)}>
                            Add rate
                        </Button>
                    )}
                    {adjustment_max !== null && can('adjustment.create') && (
                        <Button
                            variant="outline"
                            onClick={() => setAdjusting(true)}
                        >
                            Add agreed adjustment
                        </Button>
                    )}
                </PageHeader>

                <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <Field label="Date">
                        {formatDate(sale.entry_date, formats.dateFormat)}
                    </Field>
                    <Field label="Buyer">
                        {sale.party.code} – {sale.party.name}
                    </Field>
                    <Field label="Item">{sale.item}</Field>
                    <Field label="Kg">
                        <KgText value={sale.kg} />
                    </Field>
                    <Field label="Rate">
                        {sale.rate !== null
                            ? formatRate(
                                  sale.rate,
                                  formats.rateDecimals,
                                  formats.numberFormat,
                              )
                            : 'Rate pending'}
                    </Field>
                    <Field label="Amount">
                        {sale.amount !== null ? (
                            <MoneyText value={sale.amount} />
                        ) : (
                            '–'
                        )}
                    </Field>
                    <Field label="Weight slip number">
                        {sale.slip_no ?? '–'}
                    </Field>
                    {sale.our_weight_kg !== null && (
                        <Field label="Weight that left the plant">
                            <KgText value={sale.our_weight_kg} />
                        </Field>
                    )}
                    <Field label="Note">{sale.note ?? '–'}</Field>
                    <Field label="Created by">
                        {sale.created_by ?? '–'}
                        {sale.created_at && (
                            <span className="text-muted-foreground">
                                {' '}
                                ·{' '}
                                {new Date(sale.created_at).toLocaleString(
                                    'en-GB',
                                    { timeZone: 'Asia/Karachi' },
                                )}
                            </span>
                        )}
                    </Field>
                    {sale.cancel_reason && (
                        <Field label="Reason">{sale.cancel_reason}</Field>
                    )}
                </dl>

                {payments.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Received now</h2>
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
                    Sales
                </Link>
                {adjustments.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Agreed adjustments</h2>
                        <AdjustmentList rows={adjustments} />
                    </section>
                )}
                {journal && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Journal lines</h2>
                        <JournalLines rows={journal} />
                    </section>
                )}

                <section className="grid gap-2">
                    <h2 className="font-semibold">Change history</h2>
                    <ChangeHistory rows={history} />
                </section>
            </div>
            <ConfirmWithReason
                open={cancelling}
                title={`Cancel entry ${sale.voucher_no}`}
                error={cancelError}
                processing={cancelProcessing}
                onClose={() => setCancelling(false)}
                onConfirm={(reason) =>
                    router.post(
                        cancel.url(sale.id),
                        { reason, cancel_linked_payment: cancelPayment },
                        {
                            onStart: () => setCancelProcessing(true),
                            onFinish: () => setCancelProcessing(false),
                            onError: (errors) =>
                                setCancelError(errors.reason ?? errors.date),
                            onSuccess: () => setCancelling(false),
                        },
                    )
                }
            >
                {hasActivePayment && (
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="cancel_payment"
                            checked={cancelPayment}
                            onCheckedChange={(checked) =>
                                setCancelPayment(checked === true)
                            }
                        />
                        <Label htmlFor="cancel_payment">
                            Also cancel the linked payment?
                        </Label>
                    </div>
                )}
            </ConfirmWithReason>
            {adjustment_max !== null && (
                <AdjustmentDialog
                    open={adjusting}
                    title={sale.voucher_no}
                    maximum={adjustment_max}
                    entryDate={sale.entry_date}
                    url={storeAdjustment.url(sale.id)}
                    today={today}
                    onClose={() => setAdjusting(false)}
                />
            )}
            <AddRateDialog
                open={addingRate}
                title={sale.voucher_no}
                kg={sale.kg}
                url={rate.url(sale.id)}
                today={today}
                onClose={() => setAddingRate(false)}
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

ShowSale.layout = {
    breadcrumbs: [{ title: 'Sales', href: index() }],
};
