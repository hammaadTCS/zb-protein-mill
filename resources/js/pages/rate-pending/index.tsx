import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AddRateDialog } from '@/components/app/AddRateDialog';
import { KgText } from '@/components/app/KgText';
import { PageHeader } from '@/components/app/PageHeader';
import { Button } from '@/components/ui/button';
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
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { rate as purchaseRate, show as showPurchase } from '@/routes/purchases';
import { index } from '@/routes/rate-pending';
import { rate as saleRate, show as showSale } from '@/routes/sales';

type Row = {
    type: 'purchase' | 'sale';
    id: number;
    public_id: string;
    voucher_no: string;
    entry_date: string;
    party: string;
    item: string;
    kg: string;
    age_days: number;
    aged: boolean;
    can_add_rate: boolean;
};

/** Page 26: entries waiting for a rate; red when older than the limit (BR-CHK-05). */
export default function RatePending({
    today,
    type,
    rows,
}: {
    today: string;
    age_limit: number | null;
    type: string;
    rows: Row[];
}) {
    const formats = useFormats();
    const [adding, setAdding] = useState<Row | null>(null);

    return (
        <>
            <Head title="Rate pending" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Rate pending">
                    <Select
                        value={type || 'all'}
                        onValueChange={(value) =>
                            router.get(
                                index.url({
                                    query:
                                        value === 'all' ? {} : { type: value },
                                }),
                                {},
                                { preserveState: true, replace: true },
                            )
                        }
                    >
                        <SelectTrigger className="w-40" aria-label="Type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem value="purchase">Purchases</SelectItem>
                            <SelectItem value="sale">Sales</SelectItem>
                        </SelectContent>
                    </Select>
                </PageHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Voucher</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Party</TableHead>
                            <TableHead>Item</TableHead>
                            <TableHead className="text-right">Kg</TableHead>
                            <TableHead className="text-right">Days</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow
                                key={`${row.type}${row.id}`}
                                className={cn(
                                    row.aged &&
                                        'text-red-700 dark:text-red-400',
                                )}
                            >
                                <TableCell className="tabular-nums">
                                    <Link
                                        href={
                                            row.type === 'sale'
                                                ? showSale(row.public_id)
                                                : showPurchase(row.public_id)
                                        }
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {row.voucher_no}
                                    </Link>
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {formatDate(
                                        row.entry_date,
                                        formats.dateFormat,
                                    )}
                                </TableCell>
                                <TableCell>{row.party}</TableCell>
                                <TableCell>{row.item}</TableCell>
                                <TableCell className="text-right">
                                    <KgText value={row.kg} />
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'text-right tabular-nums',
                                        row.aged && 'font-semibold',
                                    )}
                                >
                                    {row.age_days}
                                </TableCell>
                                <TableCell className="text-right">
                                    {row.can_add_rate && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setAdding(row)}
                                        >
                                            Add rate
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {rows.length === 0 && (
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
            </div>
            {adding && (
                <AddRateDialog
                    open
                    title={adding.voucher_no}
                    kg={adding.kg}
                    url={
                        adding.type === 'sale'
                            ? saleRate.url(adding.public_id)
                            : purchaseRate.url(adding.public_id)
                    }
                    today={today}
                    onClose={() => setAdding(null)}
                />
            )}
        </>
    );
}

RatePending.layout = {
    breadcrumbs: [{ title: 'Rate pending', href: index() }],
};
