import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import { MoneyText } from '@/components/app/MoneyText';
import { StatusBadge } from '@/components/app/StatusBadge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { cancel } from '@/routes/adjustments';

export type AdjustmentRow = {
    id: number;
    public_id: string;
    voucher_no: string;
    entry_date: string;
    amount: string;
    reason: string;
    note: string;
    status: 'active' | 'cancelled';
    cancel_reason: string | null;
};

/** Agreed adjustments on a purchase or sale detail page, each with Cancel entry. */
export function AdjustmentList({ rows }: { rows: AdjustmentRow[] }) {
    const can = useCan();
    const formats = useFormats();
    const [cancelling, setCancelling] = useState<AdjustmentRow | null>(null);
    const [error, setError] = useState<string | undefined>();

    return (
        <>
            <div className="grid gap-2">
                {rows.map((row) => (
                    <div
                        key={row.id}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-4 py-3 text-sm"
                    >
                        <span>
                            <span className="tabular-nums">
                                {row.voucher_no} ·{' '}
                                {formatDate(row.entry_date, formats.dateFormat)}
                            </span>{' '}
                            · Agreed adjustment – {row.reason}
                            <span className="block text-muted-foreground">
                                {row.note}
                                {row.cancel_reason &&
                                    ` · Reason: ${row.cancel_reason}`}
                            </span>
                        </span>
                        <span className="flex items-center gap-2">
                            {row.status === 'cancelled' && (
                                <StatusBadge status="cancelled" />
                            )}
                            <MoneyText value={row.amount} />
                            {row.status === 'active' &&
                                can('adjustment.cancel') && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setError(undefined);
                                            setCancelling(row);
                                        }}
                                    >
                                        Cancel entry
                                    </Button>
                                )}
                        </span>
                    </div>
                ))}
            </div>
            <ConfirmWithReason
                open={cancelling !== null}
                title={`Cancel entry ${cancelling?.voucher_no ?? ''}`}
                error={error}
                onClose={() => setCancelling(null)}
                onConfirm={(reason) =>
                    cancelling &&
                    router.post(
                        cancel.url(cancelling.public_id),
                        { reason },
                        {
                            preserveScroll: true,
                            onError: (errors) =>
                                setError(errors.reason ?? errors.date),
                            onSuccess: () => setCancelling(null),
                        },
                    )
                }
            />
        </>
    );
}
