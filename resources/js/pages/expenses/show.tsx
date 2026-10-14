import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import type { HistoryRow } from '@/components/app/ChangeHistory';
import { ChangeHistory } from '@/components/app/ChangeHistory';
import type { LinkedEntry } from '@/components/app/ExpenseForm';
import type { JournalLineRow } from '@/components/app/JournalLines';
import { JournalLines } from '@/components/app/JournalLines';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { StatusBadge } from '@/components/app/StatusBadge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { cancel, edit, index } from '@/routes/expenses';
import { show as showPurchase } from '@/routes/purchases';
import { show as showSale } from '@/routes/sales';

type Expense = {
    id: number;
    public_id: string;
    voucher_no: string;
    entry_date: string;
    category: string;
    amount: string;
    payment_mode: string;
    payment_mode_label: string;
    account: string | null;
    party: { id: number; code: string; name: string } | null;
    handled_by: string | null;
    linked: LinkedEntry | null;
    note: string;
    status: 'active' | 'cancelled';
    cancel_reason: string | null;
    created_by: string | null;
    created_at: string | null;
    is_active: boolean;
};

type Attachment = { id: number; name: string; mime_type: string; url: string };

/** Page 14 (spec §11.6): every field, with Edit, Cancel entry and Change history. */
export default function ShowExpense({
    expense,
    attachments,
    history,
    journal,
}: {
    expense: Expense;
    attachments: Attachment[];
    history: HistoryRow[];
    journal?: JournalLineRow[];
}) {
    const can = useCan();
    const formats = useFormats();
    const [cancelling, setCancelling] = useState(false);
    const [error, setError] = useState<string | undefined>();

    return (
        <>
            <Head title={expense.voucher_no} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title={`Expense ${expense.voucher_no}`}>
                    {expense.status === 'cancelled' && (
                        <StatusBadge status="cancelled" />
                    )}
                    {expense.is_active && can('expense.update') && (
                        <Button variant="outline" asChild>
                            <Link href={edit(expense.public_id)}>Edit</Link>
                        </Button>
                    )}
                    {expense.is_active && can('expense.cancel') && (
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
                        {formatDate(expense.entry_date, formats.dateFormat)}
                    </Field>
                    <Field label="Category">{expense.category}</Field>
                    <Field label="Amount">
                        <MoneyText value={expense.amount} />
                    </Field>
                    <Field label="Payment mode">
                        {expense.payment_mode_label}
                    </Field>
                    {expense.account && (
                        <Field label="Account">{expense.account}</Field>
                    )}
                    <Field label="Party">
                        {expense.party
                            ? `${expense.party.code} – ${expense.party.name}`
                            : '–'}
                    </Field>
                    <Field label="Handled by">
                        {expense.handled_by ?? '–'}
                    </Field>
                    {expense.linked && (
                        <Field
                            label={
                                expense.linked.type === 'purchase'
                                    ? 'Purchase'
                                    : 'Sale'
                            }
                        >
                            <Link
                                href={
                                    expense.linked.type === 'purchase'
                                        ? showPurchase(expense.linked.public_id)
                                        : showSale(expense.linked.public_id)
                                }
                                className="underline-offset-4 hover:underline"
                            >
                                {expense.linked.label}
                            </Link>
                        </Field>
                    )}
                    <Field label="Note">{expense.note}</Field>
                    <Field label="Created by">
                        {expense.created_by ?? '–'}
                        {expense.created_at && (
                            <span className="text-muted-foreground">
                                {' '}
                                ·{' '}
                                {new Date(expense.created_at).toLocaleString(
                                    'en-GB',
                                    { timeZone: 'Asia/Karachi' },
                                )}
                            </span>
                        )}
                    </Field>
                    {expense.cancel_reason && (
                        <Field label="Reason">{expense.cancel_reason}</Field>
                    )}
                </dl>

                {attachments.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Receipt photo</h2>
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
                title={`Cancel entry ${expense.voucher_no}`}
                error={error}
                onClose={() => setCancelling(false)}
                onConfirm={(reason) =>
                    router.post(
                        cancel.url(expense.public_id),
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

ShowExpense.layout = {
    breadcrumbs: [{ title: 'Expenses', href: index() }],
};
