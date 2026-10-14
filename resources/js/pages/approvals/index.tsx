import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { BalanceInWords } from '@/components/app/BalanceInWords';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Button } from '@/components/ui/button';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { approve, index, reject } from '@/routes/approvals';

type Pending = {
    id: number;
    public_id: string;
    voucher_no: string;
    entry_date: string;
    party: string;
    balance: { words: string; amount: string };
    amount: string;
    account: string;
    reference_no: string | null;
    note: string | null;
    created_by: string | null;
};

/** Page 27 (mobile-first): payments waiting for the Owner, oldest first (BR-PAY-08). */
export default function Approvals({ payments }: { payments: Pending[] }) {
    const formats = useFormats();
    const [rejecting, setRejecting] = useState<Pending | null>(null);
    const [error, setError] = useState<string | undefined>();

    return (
        <>
            <Head title="Approvals" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <PageHeader title="Approvals" />
                {payments.length === 0 && (
                    <p className="text-muted-foreground">
                        No payments are awaiting approval.
                    </p>
                )}
                {payments.map((payment) => (
                    <div
                        key={payment.id}
                        className="grid gap-3 rounded-md border p-4"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <span className="font-semibold">
                                {payment.party}
                            </span>
                            <MoneyText
                                value={payment.amount}
                                className="text-lg font-semibold text-red-700 dark:text-red-400"
                            />
                        </div>
                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span className="tabular-nums">
                                {payment.voucher_no}
                            </span>
                            <span>
                                {formatDate(
                                    payment.entry_date,
                                    formats.dateFormat,
                                )}
                            </span>
                            <span>{payment.account}</span>
                            {payment.reference_no && (
                                <span>{payment.reference_no}</span>
                            )}
                            {payment.created_by && (
                                <span>{payment.created_by}</span>
                            )}
                        </div>
                        <div className="text-sm">
                            Balance now:{' '}
                            <BalanceInWords
                                words={payment.balance.words}
                                amount={payment.balance.amount}
                            />
                        </div>
                        {payment.note && (
                            <p className="text-sm">{payment.note}</p>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            <Button
                                size="lg"
                                onClick={() =>
                                    router.post(
                                        approve.url(payment.public_id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Approve
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={() => setRejecting(payment)}
                            >
                                Reject
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
            <ConfirmWithReason
                open={rejecting !== null}
                title={rejecting ? `Reject ${rejecting.voucher_no}` : ''}
                confirmLabel="Reject"
                error={error}
                onClose={() => setRejecting(null)}
                onConfirm={(reason) =>
                    rejecting &&
                    router.post(
                        reject.url(rejecting.public_id),
                        { reason },
                        {
                            preserveScroll: true,
                            onError: (errors) => setError(errors.reason),
                            onSuccess: () => setRejecting(null),
                        },
                    )
                }
            />
        </>
    );
}

Approvals.layout = {
    breadcrumbs: [{ title: 'Approvals', href: index() }],
};
