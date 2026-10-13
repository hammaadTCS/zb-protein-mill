import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { subtractDecimals } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dismiss, index, store, systemAmount } from '@/routes/cash-counts';

type HistoryRow = {
    id: number;
    date: string;
    account: string;
    counted: string | null;
    system: string;
    difference: string | null;
    business_closed: boolean;
    note: string | null;
    user: string;
};

/**
 * Page 28, Daily cash check (BR-CHK-01 to BR-CHK-04), mobile-first: counted cash
 * against the system amount, with a live difference. Stored, never posted.
 */
export default function CashCounts({
    today,
    cash_accounts,
    missing,
    can_dismiss,
    history,
}: {
    today: string;
    cash_accounts: { id: number; name: string }[];
    missing: { date: string; accounts: { id: number; name: string }[] } | null;
    can_dismiss: boolean;
    history: HistoryRow[];
}) {
    const formats = useFormats();
    const form = useForm({
        date: today,
        money_account_id: cash_accounts[0] ? String(cash_accounts[0].id) : '',
        counted_amount: '',
        note: '',
    });
    const { data, setData, errors } = form;
    const [system, setSystem] = useState<string | null>(null);

    // BR-CHK-02: refresh the system amount when the date or account changes.
    useEffect(() => {
        if (!data.money_account_id || !data.date) {
            return;
        }

        const controller = new AbortController();
        fetch(
            systemAmount.url({
                query: {
                    money_account_id: data.money_account_id,
                    date: data.date,
                },
            }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            },
        )
            .then((response) => (response.ok ? response.json() : null))
            .then((body) => setSystem(body?.system_amount ?? null))
            .catch(() => undefined);

        return () => controller.abort();
    }, [data.money_account_id, data.date]);

    const difference =
        system !== null ? subtractDecimals(data.counted_amount, system) : null;

    return (
        <>
            <Head title="Daily cash check" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title="Daily cash check" />

                {missing && (
                    <Alert>
                        <AlertTitle>
                            No cash count for{' '}
                            {formatDate(missing.date, formats.dateFormat)}
                        </AlertTitle>
                        <AlertDescription>
                            <div className="flex flex-wrap gap-2 pt-1">
                                {missing.accounts.map((account) => (
                                    <span
                                        key={account.id}
                                        className="flex items-center gap-2"
                                    >
                                        {account.name}
                                        {can_dismiss && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(dismiss.url(), {
                                                        date: missing.date,
                                                        money_account_id:
                                                            account.id,
                                                    })
                                                }
                                            >
                                                Mark business closed
                                            </Button>
                                        )}
                                    </span>
                                ))}
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                <form
                    className="grid gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url(), {
                            preserveScroll: true,
                            onSuccess: () =>
                                form.reset('counted_amount', 'note'),
                        });
                    }}
                >
                    <FormField id="date" label="Date" error={errors.date}>
                        <Input
                            id="date"
                            type="date"
                            className="h-11"
                            max={today}
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                            required
                        />
                    </FormField>
                    <FormField
                        id="money_account_id"
                        label="Cash account"
                        error={errors.money_account_id}
                    >
                        <Select
                            value={data.money_account_id}
                            onValueChange={(value) =>
                                setData('money_account_id', value)
                            }
                        >
                            <SelectTrigger
                                id="money_account_id"
                                className="h-11 w-full"
                            >
                                <SelectValue placeholder="Select account" />
                            </SelectTrigger>
                            <SelectContent>
                                {cash_accounts.map((account) => (
                                    <SelectItem
                                        key={account.id}
                                        value={String(account.id)}
                                    >
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <div className="flex justify-between rounded-md bg-muted px-4 py-3 text-sm">
                        <span className="text-muted-foreground">
                            System amount
                        </span>
                        {system !== null ? (
                            <MoneyText value={system} signed />
                        ) : (
                            '–'
                        )}
                    </div>
                    <FormField
                        id="counted_amount"
                        label="Counted amount"
                        error={errors.counted_amount}
                    >
                        <DecimalInput
                            id="counted_amount"
                            value={data.counted_amount}
                            onChange={(value) =>
                                setData('counted_amount', value)
                            }
                            required
                        />
                    </FormField>
                    <div
                        className={cn(
                            'flex justify-between rounded-md border px-4 py-3 text-sm font-semibold',
                            difference !== null &&
                                difference !== '0.00' &&
                                'border-red-300 text-red-800 dark:border-red-800 dark:text-red-300',
                        )}
                    >
                        <span>Difference</span>
                        {difference !== null ? (
                            <MoneyText value={difference} signed />
                        ) : (
                            '–'
                        )}
                    </div>
                    <FormField id="note" label="Note" error={errors.note}>
                        <Textarea
                            id="note"
                            maxLength={2000}
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                        />
                    </FormField>
                    <Button
                        type="submit"
                        size="lg"
                        className="h-12"
                        disabled={form.processing}
                    >
                        Save
                    </Button>
                </form>

                <section className="grid gap-2">
                    <h2 className="font-semibold">History</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead className="text-right">
                                    Counted
                                </TableHead>
                                <TableHead className="text-right">
                                    System
                                </TableHead>
                                <TableHead className="text-right">
                                    Difference
                                </TableHead>
                                <TableHead>User</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {history.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="tabular-nums">
                                        {formatDate(
                                            row.date,
                                            formats.dateFormat,
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {row.account}
                                        {row.note && (
                                            <span className="block text-xs text-muted-foreground">
                                                {row.note}
                                            </span>
                                        )}
                                    </TableCell>
                                    {row.business_closed ? (
                                        <TableCell
                                            colSpan={3}
                                            className="text-right text-muted-foreground"
                                        >
                                            Business closed
                                        </TableCell>
                                    ) : (
                                        <>
                                            <TableCell className="text-right">
                                                <MoneyText
                                                    value={
                                                        row.counted ?? '0.00'
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <MoneyText
                                                    value={row.system}
                                                    signed
                                                />
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right',
                                                    row.difference !== '0.00' &&
                                                        'font-semibold text-red-700 dark:text-red-400',
                                                )}
                                            >
                                                <MoneyText
                                                    value={
                                                        row.difference ?? '0.00'
                                                    }
                                                    signed
                                                />
                                            </TableCell>
                                        </>
                                    )}
                                    <TableCell>{row.user}</TableCell>
                                </TableRow>
                            ))}
                            {history.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-center text-muted-foreground"
                                    >
                                        No entries.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </section>
            </div>
        </>
    );
}

CashCounts.layout = {
    breadcrumbs: [{ title: 'Daily cash check', href: index() }],
};
