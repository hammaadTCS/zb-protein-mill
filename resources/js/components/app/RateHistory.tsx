import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
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
import { formatDate, formatRate } from '@/lib/format';

export type RateGroup = {
    item: string;
    party: string;
    party_id: number;
    entries: {
        date: string;
        voucher: string;
        kg: string;
        rate: string;
        amount: string;
    }[];
    /** BR-RPT-10: one line per month instead of entries, for long ranges. */
    months?: {
        month: string;
        label: string;
        kg: string;
        amount: string;
        average: string;
    }[];
    kg: string;
    amount: string;
    average: string;
};

/**
 * R-10 rows (page 37 and the party profile): each fixed rate and the weighted average.
 * `monthly` (BR-RPT-10): each line is a month's total and average rate.
 */
export function RateHistory({
    groups,
    monthly = false,
}: {
    groups: RateGroup[];
    monthly?: boolean;
}) {
    const formats = useFormats();
    const rate = (value: string) =>
        formatRate(value, formats.rateDecimals, formats.numberFormat);

    if (groups.length === 0) {
        return <p className="text-sm text-muted-foreground">No entries.</p>;
    }

    return (
        <div className="grid gap-6">
            {groups.map((group) => (
                <section
                    key={`${group.item}-${group.party_id}`}
                    className="grid gap-2"
                >
                    <h2 className="font-semibold">
                        {group.item} · {group.party}
                    </h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {monthly ? (
                                    <TableHead colSpan={2}>Month</TableHead>
                                ) : (
                                    <>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Voucher</TableHead>
                                    </>
                                )}
                                <TableHead className="text-right">Kg</TableHead>
                                <TableHead className="text-right">
                                    Rate
                                </TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {(group.months ?? []).map((month) => (
                                <TableRow key={month.month}>
                                    <TableCell colSpan={2}>
                                        {month.label}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <KgText value={month.kg} />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {rate(month.average)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={month.amount} />
                                    </TableCell>
                                </TableRow>
                            ))}
                            {group.entries.map((entry) => (
                                <TableRow key={entry.voucher}>
                                    <TableCell className="tabular-nums">
                                        {formatDate(
                                            entry.date,
                                            formats.dateFormat,
                                        )}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {entry.voucher}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <KgText value={entry.kg} />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {rate(entry.rate)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={entry.amount} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colSpan={2}>
                                    Weighted average
                                </TableCell>
                                <TableCell className="text-right">
                                    <KgText value={group.kg} />
                                </TableCell>
                                <TableCell className="text-right font-semibold tabular-nums">
                                    {rate(group.average)}
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={group.amount} />
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </section>
            ))}
        </div>
    );
}
