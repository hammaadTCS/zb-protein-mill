import { KgText } from '@/components/app/KgText';
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

export type WeightGroup = {
    party: string;
    party_id: number;
    entries: {
        date: string;
        voucher: string;
        their_kg: string;
        our_kg: string;
        difference: string;
    }[];
    /** BR-RPT-10: one line per month instead of entries, for long ranges. */
    months?: {
        month: string;
        label: string;
        their_kg: string;
        our_kg: string;
        difference: string;
    }[];
    their_kg: string;
    our_kg: string;
    difference: string;
    percent: string;
};

/**
 * R-11 rows (page 38 and the party profile): their kg against our kg. A positive
 * difference always means kg lost by the business (spec §12.4). `monthly`
 * (BR-RPT-10): each line is a month's total.
 */
export function WeightDifferences({
    groups,
    monthly = false,
}: {
    groups: WeightGroup[];
    monthly?: boolean;
}) {
    const formats = useFormats();

    if (groups.length === 0) {
        return <p className="text-sm text-muted-foreground">No entries.</p>;
    }

    return (
        <div className="grid gap-6">
            {groups.map((group) => (
                <section key={group.party_id} className="grid gap-2">
                    <h2 className="font-semibold">{group.party}</h2>
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
                                <TableHead className="text-right">
                                    Their kg
                                </TableHead>
                                <TableHead className="text-right">
                                    Our kg
                                </TableHead>
                                <TableHead className="text-right">
                                    Difference
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
                                        <KgText value={month.their_kg} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <KgText value={month.our_kg} />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {month.difference}
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
                                        <KgText value={entry.their_kg} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <KgText value={entry.our_kg} />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {entry.difference}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colSpan={2}>Total</TableCell>
                                <TableCell className="text-right">
                                    <KgText value={group.their_kg} />
                                </TableCell>
                                <TableCell className="text-right">
                                    <KgText value={group.our_kg} />
                                </TableCell>
                                <TableCell className="text-right font-semibold tabular-nums">
                                    {group.difference} kg · {group.percent}%
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </section>
            ))}
        </div>
    );
}
