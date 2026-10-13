import { Head, router } from '@inertiajs/react';
import { MoneyText } from '@/components/app/MoneyText';
import type { PartyOption } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { PageHeader } from '@/components/app/PageHeader';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, statement } from '@/routes/partners';

type Row = {
    party_id: number;
    code: string;
    name: string;
    invested: string;
    withdrawn: string;
    net: string;
};

type Filters = { from: string; to: string; party_id: number | null };

/**
 * Page 23, R-09: invested, withdrawn and net invested per partner for a date range.
 * Opening capital counts as invested. No profit is shown (BR-PTN-04).
 */
export default function PartnerStatement({
    filters,
    party,
    rows,
}: {
    filters: Filters;
    party: PartyOption | null;
    rows: Row[];
}) {
    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== '' && v !== null),
        ) as Record<string, string | number>;
        router.get(
            statement.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Partner statement" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Partner statement" />

                <div className="grid gap-2 sm:grid-cols-3">
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
                        types={['partner']}
                        value={party}
                        placeholder="All partners"
                        onChange={(picked) =>
                            apply({ party_id: picked?.id ?? null })
                        }
                    />
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Partner</TableHead>
                            <TableHead className="text-right">
                                Invested
                            </TableHead>
                            <TableHead className="text-right">
                                Withdrawn
                            </TableHead>
                            <TableHead className="text-right">
                                Net invested
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.party_id}>
                                <TableCell>
                                    <span className="text-muted-foreground tabular-nums">
                                        {row.code}
                                    </span>{' '}
                                    {row.name}
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.invested} />
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.withdrawn} />
                                </TableCell>
                                <TableCell className="text-right font-semibold">
                                    <MoneyText value={row.net} signed />
                                </TableCell>
                            </TableRow>
                        ))}
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
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

PartnerStatement.layout = {
    breadcrumbs: [
        { title: 'Partners', href: index() },
        { title: 'Partner statement', href: statement() },
    ],
};
