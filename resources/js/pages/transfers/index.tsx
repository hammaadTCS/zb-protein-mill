import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import { MoneyText } from '@/components/app/MoneyText';
import type { Paginated } from '@/components/app/Pagination';
import { Pagination } from '@/components/app/Pagination';
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
import { cancel, create, edit, index } from '@/routes/transfers';

type Row = {
    id: number;
    public_id: string;
    voucher_no: string;
    entry_date: string;
    from: string;
    to: string;
    amount: string;
    reference_no: string | null;
    note: string | null;
    status: 'active' | 'cancelled';
    cancel_reason: string | null;
};

type Filters = {
    from: string;
    to: string;
    party_id: string | null;
    status: string;
    q: string;
    money_account_id: string | null;
};

const ALL = 'all';

/**
 * Page 15 (spec §11.2): money moved between our own accounts, with edit and cancel.
 * Never income or spending (BR-TRF-03).
 */
export default function Transfers({
    transfers: entries,
    filters,
    money_accounts,
    total,
}: {
    transfers: Paginated<Row>;
    filters: Filters;
    money_accounts: { id: number; public_id: string; name: string }[];
    total: string;
}) {
    const can = useCan();
    const formats = useFormats();
    const [q, setQ] = useState(filters.q);
    const [cancelling, setCancelling] = useState<Row | null>(null);
    const [error, setError] = useState<string | undefined>();
    const canEdit = can('transfer.update');
    const canCancel = can('transfer.cancel');

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
            <Head title="Transfers" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Transfers">
                    {can('transfer.create') && (
                        <Button asChild>
                            <Link href={create()}>New transfer</Link>
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
                            filters.money_account_id
                                ? filters.money_account_id
                                : ALL
                        }
                        onValueChange={(value) =>
                            apply({
                                money_account_id: value === ALL ? null : value,
                            })
                        }
                    >
                        <SelectTrigger aria-label="Account">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All accounts</SelectItem>
                            {money_accounts.map((account) => (
                                <SelectItem
                                    key={account.id}
                                    value={account.public_id}
                                >
                                    {account.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
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
                        placeholder="Voucher or reference"
                        aria-label="Search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <div className="text-sm">
                    Total <MoneyText value={total} className="font-semibold" />
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Voucher</TableHead>
                            <TableHead>From account</TableHead>
                            <TableHead>To account</TableHead>
                            <TableHead>Reference number</TableHead>
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
                                <TableCell>{row.from}</TableCell>
                                <TableCell>
                                    {row.to}
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
                                <TableCell>{row.reference_no ?? '–'}</TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.amount} />
                                </TableCell>
                                <TableCell className="text-right">
                                    {row.status === 'cancelled' && (
                                        <StatusBadge status="cancelled" />
                                    )}
                                    {row.status === 'active' && (
                                        <div className="flex justify-end gap-2">
                                            {canEdit && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(
                                                            row.public_id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                            {canCancel && (
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

Transfers.layout = {
    breadcrumbs: [{ title: 'Transfers', href: index() }],
};
