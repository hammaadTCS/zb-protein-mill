import { Head, Link, router } from '@inertiajs/react';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Input } from '@/components/ui/input';
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
import { index, show } from '@/routes/accounts';

type Row = {
    date: string;
    voucher: string | null;
    description: string;
    party: string | null;
    in: string | null;
    out: string | null;
    balance: string;
    route: string | null;
};

/** Page 25 (R-06): opening, every movement with a running balance, closing. */
export default function AccountStatementPage({
    account,
    filters,
    statement,
}: {
    account: { id: number; name: string; type: string };
    filters: { from: string; to: string };
    statement: {
        opening: string;
        rows: Row[];
        closing: string;
        total_in: string;
        total_out: string;
    };
}) {
    const formats = useFormats();
    const apply = (changes: Partial<typeof filters>) =>
        router.get(
            show.url(account.id, { query: { ...filters, ...changes } }),
            {},
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={account.name} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={account.name}>
                    <Input
                        type="date"
                        aria-label="From"
                        className="w-44"
                        value={filters.from}
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                    <Input
                        type="date"
                        aria-label="To"
                        className="w-44"
                        value={filters.to}
                        onChange={(e) => apply({ to: e.target.value })}
                    />
                </PageHeader>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Voucher</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Party</TableHead>
                            <TableHead className="text-right">In</TableHead>
                            <TableHead className="text-right">Out</TableHead>
                            <TableHead className="text-right">
                                Balance
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow className="font-medium">
                            <TableCell colSpan={6}>Opening balance</TableCell>
                            <TableCell className="text-right">
                                <MoneyText value={statement.opening} signed />
                            </TableCell>
                        </TableRow>
                        {statement.rows.map((row, i) => (
                            <TableRow key={i}>
                                <TableCell className="tabular-nums">
                                    {formatDate(row.date, formats.dateFormat)}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {row.route ? (
                                        <Link
                                            href={row.route}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {row.voucher}
                                        </Link>
                                    ) : (
                                        (row.voucher ?? '–')
                                    )}
                                </TableCell>
                                <TableCell>{row.description}</TableCell>
                                <TableCell>{row.party ?? '–'}</TableCell>
                                <TableCell className="text-right text-green-700 dark:text-green-400">
                                    {row.in ? <MoneyText value={row.in} /> : ''}
                                </TableCell>
                                <TableCell className="text-right text-red-700 dark:text-red-400">
                                    {row.out ? (
                                        <MoneyText value={row.out} />
                                    ) : (
                                        ''
                                    )}
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={row.balance} signed />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell colSpan={4}>Closing balance</TableCell>
                            <TableCell className="text-right">
                                <MoneyText value={statement.total_in} />
                            </TableCell>
                            <TableCell className="text-right">
                                <MoneyText value={statement.total_out} />
                            </TableCell>
                            <TableCell className="text-right">
                                <MoneyText value={statement.closing} signed />
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>
        </>
    );
}

AccountStatementPage.layout = {
    breadcrumbs: [{ title: 'Accounts', href: index() }],
};
