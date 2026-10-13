import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { AddRateDialog } from '@/components/app/AddRateDialog';
import { AdjustmentDialog } from '@/components/app/AdjustmentDialog';
import type { AdjustmentRow } from '@/components/app/AdjustmentList';
import { AdjustmentList } from '@/components/app/AdjustmentList';
import { RateCorrectionDialog } from '@/components/app/RateCorrectionDialog';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import { KgText } from '@/components/app/KgText';
import type { HistoryRow } from '@/components/app/ChangeHistory';
import { ChangeHistory } from '@/components/app/ChangeHistory';
import type { JournalLineRow } from '@/components/app/JournalLines';
import { JournalLines } from '@/components/app/JournalLines';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { SharePdfButton } from '@/components/app/SharePdfButton';
import { StatusBadge } from '@/components/app/StatusBadge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate, formatRate } from '@/lib/format';
import { cancel, edit, index, rate, slip } from '@/routes/purchases';
import { store as storeAdjustment } from '@/routes/purchases/adjustments';
import { rateCorrection } from '@/routes/purchases';

type RateCorrectionRow = {
    id: number;
    voucher_no: string;
    correction_date: string;
    old_rate: string;
    new_rate: string;
    difference: string;
    reason: string;
};

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

/** Page 5 (spec §11.6): every field, read-only. Edit, add rate and cancel come with T3.1 and T3.2. */
export default function ShowPurchase({
    purchase,
    attachments,
    payments,
    today,
    history,
    journal,
    adjustments,
    adjustment_max,
    rate_corrections,
    effective,
    month_closed,
}: {
    purchase: Purchase;
    attachments: Attachment[];
    payments: LinkedPayment[];
    today: string;
    history: HistoryRow[];
    journal?: JournalLineRow[];
    adjustments: AdjustmentRow[];
    adjustment_max: string | null;
    rate_corrections: RateCorrectionRow[];
    effective: { rate: string | null; amount: string | null };
    month_closed: boolean;
}) {
    const formats = useFormats();
    const can = useCan();
    const [addingRate, setAddingRate] = useState(false);
    const [adjusting, setAdjusting] = useState(false);
    const [correcting, setCorrecting] = useState(false);
    // BR-PUR-14: in a closed month only "Correct rate" is offered.
    const editable = purchase.is_active && !month_closed;
    const canCorrect =
        purchase.is_active &&
        month_closed &&
        purchase.rate_status === 'fixed' &&
        effective.rate !== null &&
        effective.amount !== null;
    const [cancelling, setCancelling] = useState(false);
    const [cancelPayment, setCancelPayment] = useState(false);
    const [cancelError, setCancelError] = useState<string | undefined>();
    const [cancelProcessing, setCancelProcessing] = useState(false);
    const hasActivePayment = payments.some((p) => p.status === 'active');

    return (
        <>
            <Head title={purchase.voucher_no} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title={`Purchase ${purchase.voucher_no}`}>
                    {purchase.status === 'active' && (
                        <SharePdfButton
                            href={slip.url(purchase.id)}
                            filename={`${purchase.voucher_no}.pdf`}
                        />
                    )}
                    {purchase.status === 'cancelled' && (
                        <StatusBadge status="cancelled" />
                    )}
                    {purchase.status === 'active' &&
                        purchase.rate_status === 'pending' && (
                            <StatusBadge status="rate_pending" />
                        )}
                    {editable && can('purchase.update') && (
                        <Button variant="outline" asChild>
                            <Link href={edit(purchase.id)}>Edit</Link>
                        </Button>
                    )}
                    {editable && can('purchase.cancel') && (
                        <Button
                            variant="outline"
                            onClick={() => setCancelling(true)}
                        >
                            Cancel entry
                        </Button>
                    )}
                    {purchase.can_add_rate && can('purchase.update') && (
                        <Button onClick={() => setAddingRate(true)}>
                            Add rate
                        </Button>
                    )}
                    {canCorrect && can('purchase.update') && (
                        <Button
                            variant="outline"
                            onClick={() => setCorrecting(true)}
                        >
                            Correct rate
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
                {rate_corrections.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Rate corrections</h2>
                        {rate_corrections.map((row) => (
                            <div
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-4 py-3 text-sm"
                            >
                                <span>
                                    <span className="tabular-nums">
                                        {row.voucher_no} ·{' '}
                                        {formatDate(
                                            row.correction_date,
                                            formats.dateFormat,
                                        )}
                                    </span>{' '}
                                    · Rate{' '}
                                    {formatRate(
                                        row.old_rate,
                                        formats.rateDecimals,
                                        formats.numberFormat,
                                    )}{' '}
                                    →{' '}
                                    {formatRate(
                                        row.new_rate,
                                        formats.rateDecimals,
                                        formats.numberFormat,
                                    )}
                                    <span className="block text-muted-foreground">
                                        {row.reason}
                                    </span>
                                </span>
                                <MoneyText value={row.difference} signed />
                            </div>
                        ))}
                    </section>
                )}
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
                title={`Cancel entry ${purchase.voucher_no}`}
                error={cancelError}
                processing={cancelProcessing}
                onClose={() => setCancelling(false)}
                onConfirm={(reason) =>
                    router.post(
                        cancel.url(purchase.id),
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
            {canCorrect && (
                <RateCorrectionDialog
                    open={correcting}
                    title={purchase.voucher_no}
                    kg={purchase.kg}
                    currentRate={effective.rate ?? ''}
                    currentAmount={effective.amount ?? ''}
                    url={rateCorrection.url(purchase.id)}
                    today={today}
                    onClose={() => setCorrecting(false)}
                />
            )}
            {adjustment_max !== null && (
                <AdjustmentDialog
                    open={adjusting}
                    title={purchase.voucher_no}
                    maximum={adjustment_max}
                    entryDate={purchase.entry_date}
                    url={storeAdjustment.url(purchase.id)}
                    today={today}
                    onClose={() => setAdjusting(false)}
                />
            )}
            <AddRateDialog
                open={addingRate}
                title={purchase.voucher_no}
                kg={purchase.kg}
                url={rate.url(purchase.id)}
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

ShowPurchase.layout = {
    breadcrumbs: [{ title: 'Purchases', href: index() }],
};
