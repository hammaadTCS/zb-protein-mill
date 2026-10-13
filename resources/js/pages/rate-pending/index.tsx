import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { AddRateDialog } from '@/components/app/AddRateDialog';
import { KgText } from '@/components/app/KgText';
import { PageHeader } from '@/components/app/PageHeader';
import { Button } from '@/components/ui/button';
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
import { rate as purchaseRate, show as showPurchase } from '@/routes/purchases';
import { index } from '@/routes/rate-pending';

type Row = {
    type: 'purchase' | 'sale';
    id: number;
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
    rows,
}: {
    today: string;
    age_limit: number | null;
    rows: Row[];
}) {
    const can = useCan();
    const formats = useFormats();
    const [adding, setAdding] = useState<Row | null>(null);

    return (
        <>
            <Head title="Rate pending" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Rate pending" />
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
                                        href={showPurchase(row.id)}
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
                                    {row.can_add_rate &&
                                        can('purchase.update') && (
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
                    url={purchaseRate.url(adding.id)}
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
