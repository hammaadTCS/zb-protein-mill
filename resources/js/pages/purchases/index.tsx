import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { KgText } from '@/components/app/KgText';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate, formatRate } from '@/lib/format';
import { create, index, show } from '@/routes/purchases';

type Row = {
    id: number;
    public_id: string;
    voucher_no: string;
    entry_date: string;
    party: string;
    item: string;
    kg: string;
    rate: string | null;
    amount: string | null;
    rate_status: 'fixed' | 'pending';
    status: 'active' | 'cancelled';
};

type Filters = {
    from: string;
    to: string;
    party_id: string | null;
    status: string;
    q: string;
};

const ALL = 'all';

/** Page 3 (spec §11.2): current month by default, newest first, totals of active entries. */
export default function Purchases({
    purchases,
    filters,
    party,
    totals,
}: {
    purchases: Paginated<Row>;
    filters: Filters;
    party: PartyOption | null;
    totals: { kg: string; amount: string };
}) {
    const can = useCan();
    const formats = useFormats();
    const [q, setQ] = useState(filters.q);

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, q, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) => value !== '' && value !== null,
            ),
        ) as Record<string, string | number>;
        router.get(
            index.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Purchases" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Purchases">
                    {can('purchase.create') && (
                        <Button asChild>
                            <Link href={create()}>New purchase</Link>
                        </Button>
                    )}
                </PageHeader>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-6"
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
                    <PartyPicker
                        types={['supplier']}
                        value={party}
                        placeholder="All suppliers"
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
                            <SelectItem value="rate_pending">
                                Rate pending
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Voucher or party"
                        aria-label="Search"
                    />
                    <div className="flex gap-2">
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                        {party && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => apply({ party_id: null })}
                            >
                                Close
                            </Button>
                        )}
                    </div>
                </form>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Voucher</TableHead>
                            <TableHead>Supplier</TableHead>
                            <TableHead>Item</TableHead>
                            <TableHead className="text-right">Kg</TableHead>
                            <TableHead className="text-right">Rate</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {purchases.data.map((row) => (
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
                                <TableCell>{row.party}</TableCell>
                                <TableCell>{row.item}</TableCell>
                                <TableCell className="text-right">
                                    <KgText value={row.kg} />
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {row.rate !== null
                                        ? formatRate(
                                              row.rate,
                                              formats.rateDecimals,
                                              formats.numberFormat,
                                          )
                                        : ''}
                                </TableCell>
                                <TableCell className="text-right">
                                    {row.amount !== null ? (
                                        <MoneyText value={row.amount} />
                                    ) : (
                                        ''
                                    )}
                                </TableCell>
                                <TableCell>
                                    {row.status === 'cancelled' && (
                                        <StatusBadge status="cancelled" />
                                    )}
                                    {row.status === 'active' &&
                                        row.rate_status === 'pending' && (
                                            <StatusBadge status="rate_pending" />
                                        )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {purchases.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={8}
                                    className="text-center text-muted-foreground"
                                >
                                    No entries.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell colSpan={4}>Total</TableCell>
                            <TableCell className="text-right">
                                <KgText value={totals.kg} />
                            </TableCell>
                            <TableCell />
                            <TableCell className="text-right">
                                <MoneyText value={totals.amount} />
                            </TableCell>
                            <TableCell />
                        </TableRow>
                    </TableFooter>
                </Table>

                <Pagination page={purchases} />
            </div>
        </>
    );
}

Purchases.layout = {
    breadcrumbs: [{ title: 'Purchases', href: index() }],
};
