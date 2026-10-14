import { Head, Link, router } from '@inertiajs/react';
import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { PrintHeader } from '@/components/app/PrintHeader';
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
import { show as showParty } from '@/routes/parties';
import { pendingBalances } from '@/routes/reports';

type Row = {
    id: number;
    public_id: string;
    code: string;
    name: string;
    type: string;
    words: string;
    amount: string;
    pending_kg: string;
    advance_age_days: number | null;
    overdue: string | null;
};

type Section = { rows: Row[]; total: string };

const TITLES: Record<string, string> = {
    'Owes us': 'Owes us',
    'We owe': 'We owe',
    Advance: 'Advances',
};

const ALL = 'all';

/** Page 33, R-02: who owes us, whom we owe, and advances, as of a date. */
export default function PendingBalances({
    filters,
    sections,
}: {
    filters: { as_of: string; type: string };
    sections: Record<string, Section>;
}) {
    const apply = (changes: Partial<typeof filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== ''),
        ) as Record<string, string>;
        router.get(
            pendingBalances.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Pending balances" />
            <div className="flex flex-col gap-6 p-4">
                <PrintHeader title="Pending balances" asOf={filters.as_of} />
                <PageHeader title="Pending balances">
                    <Input
                        type="date"
                        aria-label="As of"
                        className="w-44"
                        value={filters.as_of}
                        onChange={(e) => apply({ as_of: e.target.value })}
                    />
                    <Select
                        value={filters.type || ALL}
                        onValueChange={(value) =>
                            apply({ type: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-40" aria-label="Party type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All parties</SelectItem>
                            <SelectItem value="supplier">Suppliers</SelectItem>
                            <SelectItem value="buyer">Buyers</SelectItem>
                            <SelectItem value="employee">Employees</SelectItem>
                            <SelectItem value="other">Others</SelectItem>
                        </SelectContent>
                    </Select>
                </PageHeader>

                {Object.entries(sections).map(([key, section]) => (
                    <section key={key} className="grid gap-2">
                        <h2 className="text-lg font-semibold">{TITLES[key]}</h2>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Party</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Rate pending
                                    </TableHead>
                                    {key === 'Advance' && (
                                        <TableHead className="text-right">
                                            Advance age (days)
                                        </TableHead>
                                    )}
                                    {key === 'Owes us' && (
                                        <TableHead className="text-right">
                                            Overdue
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {section.rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="tabular-nums">
                                            {row.code}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={showParty(row.public_id)}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {row.words}{' '}
                                            <MoneyText value={row.amount} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {row.pending_kg !== '0.000' ? (
                                                <KgText
                                                    value={row.pending_kg}
                                                />
                                            ) : (
                                                '–'
                                            )}
                                        </TableCell>
                                        {key === 'Advance' && (
                                            <TableCell className="text-right tabular-nums">
                                                {row.advance_age_days ?? '–'}
                                            </TableCell>
                                        )}
                                        {key === 'Owes us' && (
                                            <TableCell className="text-right">
                                                {row.overdue &&
                                                row.overdue !== '0.00' ? (
                                                    <MoneyText
                                                        value={row.overdue}
                                                        className="text-red-700 dark:text-red-400"
                                                    />
                                                ) : (
                                                    '–'
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                                {section.rows.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-center text-muted-foreground"
                                        >
                                            No entries.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell colSpan={2}>Total</TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={section.total} />
                                    </TableCell>
                                    <TableCell
                                        colSpan={key === 'We owe' ? 1 : 2}
                                    />
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </section>
                ))}
            </div>
        </>
    );
}

PendingBalances.layout = {
    breadcrumbs: [{ title: 'Pending balances', href: pendingBalances() }],
};
