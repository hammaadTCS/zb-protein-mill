import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { DecimalInput } from '@/components/app/DecimalInput';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { compareDecimals } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index as approvals } from '@/routes/approvals';
import {
    bankStatement,
    cashCount,
    cashDifference,
    close,
    show,
} from '@/routes/month-end';
import { index as months } from '@/routes/months';
import { index as ratePending } from '@/routes/rate-pending';

type Check = {
    check:
        | 'previous_months'
        | 'pending_rates'
        | 'awaiting_approval'
        | 'trial_balance'
        | 'bank_match'
        | 'cash_match';
    passed: boolean;
    details: Record<string, unknown>;
};

type Month = { key: string; name: string; last_day: string; ended: boolean };

type BankDetails = {
    money_account_id: number;
    money_account: string;
    statement_balance: string | null;
    system_balance: string;
    difference: string | null;
};

type CashDetails = {
    money_account_id: number;
    money_account: string;
    counted_amount: string | null;
    system_balance: string;
    difference: string | null;
};

/**
 * Page 30, Month-end close (spec §11.12, D-26): the oldest open month as steps, each
 * Done or Not done. "Close month" is enabled only when every step is done; the server
 * runs every check again on close.
 */
export default function MonthEnd({
    month,
    checks,
}: {
    month: Month | null;
    checks: Check[];
}) {
    const formats = useFormats();
    const [acceptPending, setAcceptPending] = useState(false);
    const [closing, setClosing] = useState(false);
    const find = (key: Check['check']) => checks.filter((c) => c.check === key);
    const pending = find('pending_rates')[0];
    const pendingVouchers = (pending?.details.vouchers as string[]) ?? [];
    const pendingDone = pendingVouchers.length === 0 || acceptPending;
    const allDone = checks.every((c) =>
        c.check === 'pending_rates' ? pendingDone : c.passed,
    );

    return (
        <>
            <Head title="Month-end close" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title="Month-end close">
                    <Button variant="outline" asChild>
                        <Link href={months()}>Months</Link>
                    </Button>
                </PageHeader>

                {month === null && (
                    <p className="text-muted-foreground">
                        Every month up to now is closed.
                    </p>
                )}

                {month !== null && !month.ended && (
                    <Alert>
                        <AlertTitle>{month.name} has not ended yet.</AlertTitle>
                        <AlertDescription>
                            It can be closed after{' '}
                            {formatDate(month.last_day, formats.dateFormat)}.
                        </AlertDescription>
                    </Alert>
                )}

                {month !== null && month.ended && (
                    <>
                        <h2 className="text-lg font-semibold">{month.name}</h2>
                        <ol className="grid gap-4">
                            {find('previous_months')
                                .filter((c) => !c.passed)
                                .map((c) => (
                                    <Step
                                        key="previous"
                                        title="Previous months"
                                        done={false}
                                    >
                                        Close{' '}
                                        {(
                                            c.details.open_months as string[]
                                        ).join(', ')}{' '}
                                        first.
                                    </Step>
                                ))}

                            <Step
                                number={1}
                                title="Rate pending"
                                done={pendingDone}
                            >
                                {pendingVouchers.length === 0 ? (
                                    'No rate-pending entries in this month.'
                                ) : (
                                    <div className="grid gap-2">
                                        <p>
                                            {pendingVouchers.length}{' '}
                                            rate-pending{' '}
                                            {pendingVouchers.length === 1
                                                ? 'entry'
                                                : 'entries'}
                                            : {pendingVouchers.join(', ')}.{' '}
                                            <Link
                                                href={ratePending()}
                                                className="underline underline-offset-4"
                                            >
                                                Rate pending
                                            </Link>
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="accept_pending_rates"
                                                checked={acceptPending}
                                                onCheckedChange={(value) =>
                                                    setAcceptPending(
                                                        value === true,
                                                    )
                                                }
                                            />
                                            <Label htmlFor="accept_pending_rates">
                                                Close with pending rates
                                            </Label>
                                        </div>
                                    </div>
                                )}
                            </Step>

                            {find('awaiting_approval').map((c) => (
                                <Step
                                    key="approvals"
                                    number={2}
                                    title="Approvals"
                                    done={c.passed}
                                >
                                    {c.passed ? (
                                        'No payment in this month is awaiting approval.'
                                    ) : (
                                        <p>
                                            Awaiting approval:{' '}
                                            {(
                                                c.details.vouchers as string[]
                                            ).join(', ')}
                                            .{' '}
                                            <Link
                                                href={approvals()}
                                                className="underline underline-offset-4"
                                            >
                                                Approvals
                                            </Link>
                                        </p>
                                    )}
                                </Step>
                            ))}

                            {find('trial_balance').map((c) => (
                                <Step
                                    key="trial"
                                    number={3}
                                    title="Trial balance"
                                    done={c.passed}
                                >
                                    <div className="flex flex-wrap gap-x-6 gap-y-1">
                                        <span>
                                            Total debit{' '}
                                            <MoneyText
                                                value={
                                                    c.details
                                                        .total_debit as string
                                                }
                                            />
                                        </span>
                                        <span>
                                            Total credit{' '}
                                            <MoneyText
                                                value={
                                                    c.details
                                                        .total_credit as string
                                                }
                                            />
                                        </span>
                                    </div>
                                </Step>
                            ))}

                            <Step
                                number={4}
                                title="Bank statement balance"
                                done={find('bank_match').every((c) => c.passed)}
                            >
                                <div className="grid gap-4">
                                    {find('bank_match').length === 0 &&
                                        'No active bank accounts.'}
                                    {find('bank_match').map((c) => (
                                        <BankRow
                                            key={
                                                (c.details as BankDetails)
                                                    .money_account_id
                                            }
                                            period={month.key}
                                            details={c.details as BankDetails}
                                            done={c.passed}
                                        />
                                    ))}
                                </div>
                            </Step>

                            <Step
                                number={5}
                                title={`Cash count on ${formatDate(month.last_day, formats.dateFormat)}`}
                                done={find('cash_match').every((c) => c.passed)}
                            >
                                <div className="grid gap-4">
                                    {find('cash_match').length === 0 &&
                                        'No active cash accounts.'}
                                    {find('cash_match').map((c) => (
                                        <CashRow
                                            key={
                                                (c.details as CashDetails)
                                                    .money_account_id
                                            }
                                            period={month.key}
                                            details={c.details as CashDetails}
                                            done={c.passed}
                                        />
                                    ))}
                                </div>
                            </Step>

                            <Step number={6} title="Close month" done={false}>
                                <div className="grid gap-2">
                                    <p className="text-muted-foreground">
                                        After closing, nothing dated in{' '}
                                        {month.name} can be added, edited or
                                        cancelled.
                                    </p>
                                    <Button
                                        className="w-fit"
                                        disabled={!allDone || closing}
                                        onClick={() =>
                                            router.post(
                                                close.url(),
                                                {
                                                    period: month.key,
                                                    accept_pending_rates:
                                                        acceptPending,
                                                },
                                                {
                                                    onStart: () =>
                                                        setClosing(true),
                                                    onFinish: () =>
                                                        setClosing(false),
                                                },
                                            )
                                        }
                                    >
                                        Close month
                                    </Button>
                                </div>
                            </Step>
                        </ol>
                    </>
                )}
            </div>
        </>
    );
}

MonthEnd.layout = {
    breadcrumbs: [{ title: 'Month-end close', href: show() }],
};

function Step({
    number,
    title,
    done,
    children,
}: {
    number?: number;
    title: string;
    done: boolean;
    children: ReactNode;
}) {
    return (
        <li className="grid gap-3 rounded-lg border p-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-semibold">
                    {number !== undefined && `${number}. `}
                    {title}
                </h3>
                {number !== 6 && (
                    <Badge
                        variant="outline"
                        className={cn(
                            'gap-1',
                            done
                                ? 'border-transparent bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100'
                                : 'border-transparent bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
                        )}
                    >
                        {done ? <Check /> : <X />}
                        {done ? 'Done' : 'Not done'}
                    </Badge>
                )}
            </div>
            <div className="text-sm">{children}</div>
        </li>
    );
}

/** A difference with its sign: the statement or count minus the system balance. */
function Difference({ value }: { value: string | null }) {
    if (value === null) {
        return <span className="text-muted-foreground">–</span>;
    }

    return (
        <span
            className={cn(
                compareDecimals(value, '0') !== 0 &&
                    'font-semibold text-red-700 dark:text-red-400',
            )}
        >
            <MoneyText value={value} signed />
        </span>
    );
}

function BankRow({
    period,
    details,
    done,
}: {
    period: string;
    details: BankDetails;
    done: boolean;
}) {
    const form = useForm({
        period,
        money_account_id: details.money_account_id,
        statement_balance: details.statement_balance ?? '',
    });

    return (
        <form
            className="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(bankStatement.url(), { preserveScroll: true });
            }}
        >
            <div className="grid gap-1">
                <Label htmlFor={`bank-${details.money_account_id}`}>
                    {details.money_account}
                    {done && <Check className="ml-1 inline size-4" />}
                </Label>
                <Input
                    id={`bank-${details.money_account_id}`}
                    inputMode="decimal"
                    autoComplete="off"
                    className="h-11 text-right tabular-nums"
                    placeholder="Statement balance"
                    value={form.data.statement_balance}
                    onChange={(event) => {
                        // An overdraft is below zero: one leading minus sign is allowed.
                        const next = event.target.value.replace(/[^\d.-]/g, '');

                        if (/^-?\d*\.?\d*$/.test(next)) {
                            form.setData('statement_balance', next);
                        }
                    }}
                />
                <InputError message={form.errors.statement_balance} />
                <p className="text-muted-foreground">
                    System balance{' '}
                    <MoneyText value={details.system_balance} signed /> ·
                    Difference <Difference value={details.difference} />
                </p>
            </div>
            <Button
                type="submit"
                variant="outline"
                className="h-11"
                disabled={form.processing}
            >
                Save
            </Button>
        </form>
    );
}

function CashRow({
    period,
    details,
    done,
}: {
    period: string;
    details: CashDetails;
    done: boolean;
}) {
    const form = useForm({
        period,
        money_account_id: details.money_account_id,
        counted_amount: '',
    });
    const [posting, setPosting] = useState(false);
    const remaining =
        details.difference !== null &&
        compareDecimals(details.difference, '0') !== 0
            ? details.difference
            : null;

    return (
        <div className="grid gap-2">
            <form
                className="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(cashCount.url(), {
                        preserveScroll: true,
                        onSuccess: () => form.reset('counted_amount'),
                    });
                }}
            >
                <div className="grid gap-1">
                    <Label htmlFor={`cash-${details.money_account_id}`}>
                        {details.money_account}
                        {done && <Check className="ml-1 inline size-4" />}
                    </Label>
                    <DecimalInput
                        id={`cash-${details.money_account_id}`}
                        placeholder={
                            details.counted_amount === null
                                ? 'Counted amount'
                                : `Counted ${details.counted_amount}`
                        }
                        value={form.data.counted_amount}
                        onChange={(value) =>
                            form.setData('counted_amount', value)
                        }
                    />
                    <InputError message={form.errors.counted_amount} />
                    <p className="text-muted-foreground">
                        System balance{' '}
                        <MoneyText value={details.system_balance} signed /> ·
                        Counted{' '}
                        {details.counted_amount === null ? (
                            '–'
                        ) : (
                            <MoneyText value={details.counted_amount} />
                        )}{' '}
                        · Difference <Difference value={details.difference} />
                    </p>
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    className="h-11"
                    disabled={form.processing}
                >
                    Save
                </Button>
            </form>
            {remaining !== null && (
                <Button
                    variant="outline"
                    className="w-fit"
                    onClick={() => setPosting(true)}
                >
                    {remaining.startsWith('-')
                        ? 'Post shortage'
                        : 'Post excess'}
                </Button>
            )}
            {remaining !== null && (
                <CashDifferenceDialog
                    open={posting}
                    period={period}
                    accountId={details.money_account_id}
                    accountName={details.money_account}
                    difference={remaining}
                    onClose={() => setPosting(false)}
                />
            )}
        </div>
    );
}

/** BR-MEC-06: the Owner posts the remaining difference (PR-23 or PR-24) with a note. */
function CashDifferenceDialog({
    open,
    period,
    accountId,
    accountName,
    difference,
    onClose,
}: {
    open: boolean;
    period: string;
    accountId: number;
    accountName: string;
    difference: string;
    onClose: () => void;
}) {
    const shortage = difference.startsWith('-');
    const amount = shortage ? difference.slice(1) : difference;
    const form = useForm({
        period,
        money_account_id: accountId,
        amount,
        direction: shortage ? 'shortage' : 'excess',
        note: '',
    });

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(cashDifference.url(), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {shortage ? 'Post shortage' : 'Post excess'} –{' '}
                            {accountName}
                        </DialogTitle>
                    </DialogHeader>
                    <p className="text-sm">
                        Amount <MoneyText value={amount} />, dated the
                        month&apos;s last day.
                    </p>
                    <InputError message={form.errors.amount} />
                    <div className="grid gap-2">
                        <Label htmlFor="note">Note</Label>
                        <Textarea
                            id="note"
                            maxLength={2000}
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.note} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Close
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || form.data.note.trim() === ''
                            }
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
