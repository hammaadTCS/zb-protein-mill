import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
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
import { cancel, create, edit, index, statement } from '@/routes/partners';

type Row = {
    id: number;
    voucher_no: string;
    entry_date: string;
    type: 'investment' | 'withdrawal';
    partner: string;
    account: string;
    amount: string;
    note: string | null;
    status: 'active' | 'cancelled';
    cancel_reason: string | null;
};

type Filters = {
    from: string;
    to: string;
    party_id: number | null;
    status: string;
    q: string;
    type: string;
};

const ALL = 'all';

/** Page 21 (spec §11.2): partner investments and withdrawals, with edit and cancel. */
export default function PartnerEntries({
    entries,
    filters,
    party,
    totals,
}: {
    entries: Paginated<Row>;
    filters: Filters;
    party: PartyOption | null;
    totals: { invested: string; withdrawn: string };
}) {
    const can = useCan();
    const formats = useFormats();
    const [q, setQ] = useState(filters.q);
    const [cancelling, setCancelling] = useState<Row | null>(null);
    const [error, setError] = useState<string | undefined>();
    const manage = can('partner.manage');

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
            <Head title="Partners" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Partners">
                    <Button variant="outline" asChild>
                        <Link href={statement()}>Partner statement</Link>
                    </Button>
                    {manage && (
                        <Button asChild>
                            <Link href={create()}>New partner entry</Link>
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
                        value={filters.type || ALL}
                        onValueChange={(value) =>
                            apply({ type: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger aria-label="Type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Investments and withdrawals
                            </SelectItem>
                            <SelectItem value="investment">
                                Investment
                            </SelectItem>
                            <SelectItem value="withdrawal">
                                Withdrawal
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <PartyPicker
                        types={['partner']}
                        value={party}
                        placeholder="All partners"
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
                        placeholder="Voucher or partner"
                        aria-label="Search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <div className="flex flex-wrap gap-6 text-sm">
                    <span>
                        Invested{' '}
                        <MoneyText
                            value={totals.invested}
                            className="font-semibold text-green-700 dark:text-green-400"
                        />
                    </span>
                    <span>
                        Withdrawn{' '}
                        <MoneyText
                            value={totals.withdrawn}
                            className="font-semibold text-red-700 dark:text-red-400"
                        />
                    </span>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Voucher</TableHead>
                            <TableHead />
                            <TableHead>Partner</TableHead>
                            <TableHead>Account</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {entries.data.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="tabular-nums">
                                    {formatDate(
                                        row.entry_date,
                                        formats.dateFormat,
                                    )}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {row.voucher_no}
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'font-medium',
                                        row.type === 'withdrawal'
                                            ? 'text-red-700 dark:text-red-400'
                                            : 'text-green-700 dark:text-green-400',
                                    )}
                                >
                                    {row.type === 'withdrawal'
                                        ? 'Withdrawal'
                                        : 'Investment'}
                                </TableCell>
                                <TableCell>
                                    {row.partner}
                                    {row.note && (
                                        <span className="block text-sm text-muted-foreground">
                                            {row.note}
                                        </span>
                                    )}
                                    {row.cancel_reason && (
                                        <span className="block text-sm text-muted-foreground">
                                            Reason: {row.cancel_reason}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell>{row.account}</TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.amount} />
                                </TableCell>
                                <TableCell className="text-right">
                                    {row.status === 'cancelled' && (
                                        <StatusBadge status="cancelled" />
                                    )}
                                    {row.status === 'active' && manage && (
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link href={edit(row.id)}>
                                                    Edit
                                                </Link>
                                            </Button>
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
                                        </div>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {entries.data.length === 0 && (
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

                <Pagination page={entries} />
            </div>
            <ConfirmWithReason
                open={cancelling !== null}
                title={`Cancel entry ${cancelling?.voucher_no ?? ''}`}
                error={error}
                onClose={() => setCancelling(null)}
                onConfirm={(reason) =>
                    cancelling &&
                    router.post(
                        cancel.url(cancelling.id),
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

PartnerEntries.layout = {
    breadcrumbs: [{ title: 'Partners', href: index() }],
};
