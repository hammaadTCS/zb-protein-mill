import { Head, router } from '@inertiajs/react';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { PrintHeader } from '@/components/app/PrintHeader';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { trialBalance } from '@/routes/reports';

type Row = {
    code: string;
    name: string;
    party: string | null;
    debit: string;
    credit: string;
};

/** Page 36, R-07. Debit and Credit are allowed words on this page only (glossary §5). */
export default function TrialBalance({
    as_of,
    show_parties,
    rows,
    total_debit,
    total_credit,
    balanced,
}: {
    as_of: string;
    show_parties: boolean;
    rows: Row[];
    total_debit: string;
    total_credit: string;
    balanced: boolean;
}) {
    const reload = (query: { as_of: string; parties: boolean }) =>
        router.get(
            trialBalance.url({
                query: {
                    as_of: query.as_of,
                    ...(query.parties ? { parties: 1 } : {}),
                },
            }),
            {},
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Trial balance" />
            <div className="flex flex-col gap-4 p-4">
                <PrintHeader title="Trial balance" asOf={as_of} />
                <PageHeader title="Trial balance">
                    <span
                        className={cn(
                            'rounded-md px-3 py-1 text-sm font-semibold',
                            balanced
                                ? 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100'
                                : 'bg-red-600 text-white',
                        )}
                    >
                        {balanced ? 'Balanced' : 'Not balanced'}
                    </span>
                </PageHeader>

                <div
                    data-print="hide"
                    className="flex flex-wrap items-center gap-4"
                >
                    <Label htmlFor="as_of">As of</Label>
                    <Input
                        id="as_of"
                        type="date"
                        className="w-44"
                        value={as_of}
                        onChange={(event) =>
                            reload({
                                as_of: event.target.value,
                                parties: show_parties,
                            })
                        }
                    />
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="parties"
                            checked={show_parties}
                            onCheckedChange={(checked) =>
                                reload({ as_of, parties: checked === true })
                            }
                        />
                        <Label htmlFor="parties">Show parties</Label>
                    </div>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Account</TableHead>
                            {show_parties && <TableHead>Party</TableHead>}
                            <TableHead className="text-right">Debit</TableHead>
                            <TableHead className="text-right">Credit</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row, index) => (
                            <TableRow key={index}>
                                <TableCell className="tabular-nums">
                                    {row.code}
                                </TableCell>
                                <TableCell>{row.name}</TableCell>
                                {show_parties && (
                                    <TableCell>{row.party}</TableCell>
                                )}
                                <TableCell className="text-right">
                                    {row.debit !== '0.00' && (
                                        <MoneyText value={row.debit} />
                                    )}
                                </TableCell>
                                <TableCell className="text-right">
                                    {row.credit !== '0.00' && (
                                        <MoneyText value={row.credit} />
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell colSpan={show_parties ? 3 : 2}>
                                Total
                            </TableCell>
                            <TableCell className="text-right">
                                <MoneyText value={total_debit} />
                            </TableCell>
                            <TableCell className="text-right">
                                <MoneyText value={total_credit} />
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>
        </>
    );
}

TrialBalance.layout = {
    breadcrumbs: [{ title: 'Trial balance', href: trialBalance() }],
};
