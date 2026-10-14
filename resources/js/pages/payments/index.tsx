import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { MoneyText } from '@/components/app/MoneyText';
import type { Paginated } from '@/components/app/Pagination';
import { Pagination } from '@/components/app/Pagination';
import type { PartyOption } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { PageHeader } from '@/components/app/PageHeader';
import { StatusBadge } from '@/components/app/StatusBadge';
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
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/payments';

type Row = {
    id: number;
    public_id: string;
    voucher_no: string;
    entry_date: string;
    direction: 'paid' | 'received';
    party: string;
    account: string;
    amount: string;
    status: 'active' | 'cancelled';
    approval_status: 'not_required' | 'pending' | 'approved' | 'rejected';
};

type Filters = {
    from: string;
    to: string;
    party_id: string | null;
    status: string;
    q: string;
    direction: string;
};

const ALL = 'all';

/** Page 9 (spec §11.2): payments and receipts, with a direction filter. */
export default function Payments({
    payments,
    filters,
    party,
    totals,
}: {
    payments: Paginated<Row>;
    filters: Filters;
    party: PartyOption | null;
    totals: { paid: string; received: string };
}) {
    const can = useCan();
    const formats = useFormats();
    const [q, setQ] = useState(filters.q);

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, q, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== '' && v !== null),
        ) as Record<string, string | number>;
        router.get(
            index.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Payments" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Payments">
                    {can('payment.create') && (
                        <Button asChild>
                            <Link href={create()}>New payment</Link>
                        </Button>
                    )}
                </PageHeader>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-7"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({});
                    }}
                >
                    <Input
                        type="date"
                        aria-label="From"
                        value={filters.from}
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                    <Input
                        type="date"
                        aria-label="To"
                        value={filters.to}
                        onChange={(e) => apply({ to: e.target.value })}
                    />
                    <Select
                        value={filters.direction || ALL}
                        onValueChange={(value) =>
                            apply({ direction: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger aria-label="Direction">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Paid and received
                            </SelectItem>
                            <SelectItem value="paid">Paid</SelectItem>
                            <SelectItem value="received">Received</SelectItem>
                        </SelectContent>
                    </Select>
                    <PartyPicker
                        types={['supplier', 'buyer', 'employee', 'other']}
                        value={party}
                        placeholder="All parties"
                        onChange={(picked) =>
                            apply({ party_id: picked?.public_id ?? null })
                        }
                    />
                    <Select
                        value={filters.status || ALL}
                        onValueChange={(value) =>
                            apply({ status: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger aria-label="Status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                            <SelectItem value="awaiting_approval">
                                Awaiting approval
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Voucher or party"
                        aria-label="Search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <div className="flex flex-wrap gap-6 text-sm">
                    <span>
                        Paid{' '}
                        <MoneyText
                            value={totals.paid}
                            className="font-semibold text-red-700 dark:text-red-400"
                        />
                    </span>
                    <span>
                        Received{' '}
                        <MoneyText
                            value={totals.received}
                            className="font-semibold text-green-700 dark:text-green-400"
                        />
                    </span>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Voucher</TableHead>
                            <TableHead />
                            <TableHead>Party</TableHead>
                            <TableHead>Account</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {payments.data.map((row) => (
                            <TableRow
                                key={row.id}
                                className="cursor-pointer"
                                onClick={() =>
                                    router.visit(show.url(row.public_id))
                                }
                            >
                                <TableCell className="tabular-nums">
                                    {formatDate(
                                        row.entry_date,
                                        formats.dateFormat,
                                    )}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    <Link
                                        href={show(row.public_id)}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {row.voucher_no}
                                    </Link>
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'font-medium',
                                        row.direction === 'paid'
                                            ? 'text-red-700 dark:text-red-400'
                                            : 'text-green-700 dark:text-green-400',
                                    )}
                                >
                                    {row.direction === 'paid'
                                        ? 'Paid'
                                        : 'Received'}
                                </TableCell>
                                <TableCell>{row.party}</TableCell>
                                <TableCell>{row.account}</TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.amount} />
                                </TableCell>
                                <TableCell>
                                    {row.status === 'cancelled' && (
                                        <StatusBadge status="cancelled" />
                                    )}
                                    {row.status === 'active' &&
                                        row.approval_status === 'pending' && (
                                            <StatusBadge status="awaiting_approval" />
                                        )}
                                    {row.approval_status === 'rejected' && (
                                        <StatusBadge status="rejected" />
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {payments.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="text-center text-muted-foreground"
                                >
                                    No entries.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <Pagination page={payments} />
            </div>
        </>
    );
}

Payments.layout = {
    breadcrumbs: [{ title: 'Payments', href: index() }],
};
