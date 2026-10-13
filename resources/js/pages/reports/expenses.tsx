import { Head, Link, router } from '@inertiajs/react';
import { MoneyText } from '@/components/app/MoneyText';
import type { PartyOption } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { PageHeader } from '@/components/app/PageHeader';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { show as showExpense } from '@/routes/expenses';
import { expenses as expenseReport } from '@/routes/reports';

type Filters = {
    from: string;
    to: string;
    category_id: number | null;
    party_id: number | null;
};

type Row = {
    id: number;
    date: string;
    voucher: string;
    category: string;
    amount: string;
    mode: string;
    party: string | null;
    note: string;
};

const ALL = 'all';

/** Page 34, R-05: the total per category, then every expense with its note. */
export default function ExpenseReportPage({
    filters,
    party,
    categories,
    report,
}: {
    filters: Filters;
    party: PartyOption | null;
    categories: { id: number; name: string }[];
    report: {
        totals: { category: string; amount: string }[];
        total: string;
        rows: Row[];
    };
}) {
    const formats = useFormats();
    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== '' && v !== null),
        ) as Record<string, string | number>;
        router.get(
            expenseReport.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Expense report" />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Expense report" />
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
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
                </div>

                <section className="grid gap-2">
                    <h2 className="text-lg font-semibold">By category</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Category</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {report.totals.map((row) => (
                                <TableRow key={row.category}>
                                    <TableCell>{row.category}</TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={row.amount} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell>Total</TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={report.total} />
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </section>

                <section className="grid gap-2">
                    <h2 className="text-lg font-semibold">Expenses</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Voucher</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead>Payment mode</TableHead>
                                <TableHead>Party</TableHead>
                                <TableHead>Note</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {report.rows.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="tabular-nums">
                                        {formatDate(
                                            row.date,
                                            formats.dateFormat,
                                        )}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        <Link
                                            href={showExpense(row.id)}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {row.voucher}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{row.category}</TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={row.amount} />
                                    </TableCell>
                                    <TableCell>{row.mode}</TableCell>
                                    <TableCell>{row.party ?? '–'}</TableCell>
                                    <TableCell>{row.note}</TableCell>
                                </TableRow>
                            ))}
                            {report.rows.length === 0 && (
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
                </section>
            </div>
        </>
    );
}

ExpenseReportPage.layout = {
    breadcrumbs: [{ title: 'Expense report', href: expenseReport() }],
};
