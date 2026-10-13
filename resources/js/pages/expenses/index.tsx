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
import { create, index, show } from '@/routes/expenses';

type Row = {
    id: number;
    voucher_no: string;
    entry_date: string;
    category: string;
    note: string;
    payment_mode: 'paid' | 'unpaid' | 'paid_by_employee';
    paid_from: string | null;
    amount: string;
    status: 'active' | 'cancelled';
};

type Filters = {
    from: string;
    to: string;
    party_id: number | null;
    status: string;
    q: string;
    category_id: number | null;
};

const ALL = 'all';

const MODE_LABELS: Record<Row['payment_mode'], string> = {
    paid: 'Paid',
    unpaid: 'Unpaid',
    paid_by_employee: 'Paid by employee',
};

/** Page 12 (spec §11.2): expenses with a category filter; each row shows its note. */
export default function Expenses({
    expenses,
    filters,
    party,
    categories,
    total,
}: {
    expenses: Paginated<Row>;
    filters: Filters;
    party: PartyOption | null;
    categories: { id: number; name: string }[];
    total: string;
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
            <Head title="Expenses" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Expenses">
                    {can('expense.create') && (
                        <Button asChild>
                            <Link href={create()}>Quick expense</Link>
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
                        value={
                            filters.category_id
                                ? String(filters.category_id)
                                : ALL
                        }
                        onValueChange={(value) =>
                            apply({
                                category_id:
                                    value === ALL ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger aria-label="Category">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All categories</SelectItem>
                            {categories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={String(category.id)}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <PartyPicker
                        types={['supplier', 'employee', 'other']}
                        value={party}
                        placeholder="All parties"
                        onChange={(picked) =>
                            apply({ party_id: picked?.id ?? null })
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
                        </SelectContent>
                    </Select>
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Voucher, note or party"
                        aria-label="Search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <div className="text-sm">
                    Total{' '}
                    <MoneyText
                        value={total}
                        className="font-semibold text-red-700 dark:text-red-400"
                    />
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Voucher</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Note</TableHead>
                            <TableHead>Payment mode</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {expenses.data.map((row) => (
                            <TableRow
                                key={row.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(show.url(row.id))}
                            >
                                <TableCell className="tabular-nums">
                                    {formatDate(
                                        row.entry_date,
                                        formats.dateFormat,
                                    )}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    <Link
                                        href={show(row.id)}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {row.voucher_no}
                                    </Link>
                                </TableCell>
                                <TableCell>{row.category}</TableCell>
                                <TableCell className="max-w-64 truncate">
                                    {row.note}
                                </TableCell>
                                <TableCell>
                                    {MODE_LABELS[row.payment_mode]}
                                    {row.paid_from && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {row.paid_from}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.amount} />
                                </TableCell>
                                <TableCell>
                                    {row.status === 'cancelled' && (
                                        <StatusBadge status="cancelled" />
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {expenses.data.length === 0 && (
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

                <Pagination page={expenses} />
            </div>
        </>
    );
}

Expenses.layout = {
    breadcrumbs: [{ title: 'Expenses', href: index() }],
};
