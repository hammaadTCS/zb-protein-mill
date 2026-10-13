import { Head } from '@inertiajs/react';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import type { PartyOption } from '@/components/app/PartyPicker';
import type { ReportFilterValues } from '@/components/app/ReportFilters';
import { ReportFilters } from '@/components/app/ReportFilters';
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
import { adjustments as adjustmentsReport } from '@/routes/reports';

type Row = {
    party: string;
    party_id: number;
    reason: string;
    total: string;
    count: number;
    base: string;
    percent: string | null;
};

const ALL = 'all';
const REASONS = ['quality', 'weight', 'freight', 'other'];

/** Page 39, R-12: agreed adjustments per party and reason, and % of their entries. */
export default function AdjustmentsReport({
    filters,
    party,
    rows,
}: {
    filters: ReportFilterValues;
    party: PartyOption | null;
    rows: Row[];
}) {
    return (
        <>
            <Head title="Adjustments report" />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Adjustments report" />
                <ReportFilters
                    url={(query) => adjustmentsReport.url({ query })}
                    filters={filters}
                    party={party}
                >
                    {(apply) => (
                        <Select
                            value={(filters.reason as string | null) || ALL}
                            onValueChange={(value) =>
                                apply({ reason: value === ALL ? null : value })
                            }
                        >
                            <SelectTrigger aria-label="Reason">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All reasons</SelectItem>
                                {REASONS.map((reason) => (
                                    <SelectItem key={reason} value={reason}>
                                        {reason[0].toUpperCase() +
                                            reason.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </ReportFilters>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Party</TableHead>
                            <TableHead>Reason</TableHead>
                            <TableHead className="text-right">Count</TableHead>
                            <TableHead className="text-right">Total</TableHead>
                            <TableHead className="text-right">
                                {filters.type === 'sale'
                                    ? 'Sales'
                                    : 'Purchases'}
                            </TableHead>
                            <TableHead className="text-right">%</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={`${row.party_id}-${row.reason}`}>
                                <TableCell>{row.party}</TableCell>
                                <TableCell>{row.reason}</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {row.count}
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.total} />
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.base} />
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {row.percent ?? '–'}
                                </TableCell>
                            </TableRow>
                        ))}
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="text-center text-muted-foreground"
                                >
                                    No entries.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

AdjustmentsReport.layout = {
    breadcrumbs: [{ title: 'Adjustments report', href: adjustmentsReport() }],
};
