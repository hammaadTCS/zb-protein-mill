import { Head, router } from '@inertiajs/react';
import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { cn } from '@/lib/utils';
import { incomeVsSpending } from '@/routes/reports';

type Line = { code: string; name: string; amount: string };

type Report = {
    from: string;
    to: string;
    income: string;
    spending: string;
    result: string;
    pending_kg: string;
    income_lines: Line[];
    spending_lines: Line[];
};

/**
 * Page 35, R-08 (BR-RPT-08): the title is always "Income vs Spending" and the banner
 * is always visible. The result may be below zero, so it is shown with its sign.
 */
export default function IncomeVsSpendingPage({
    banner,
    report,
}: {
    banner: string;
    report: Report;
}) {
    const apply = (changes: Partial<{ from: string; to: string }>) =>
        router.get(
            incomeVsSpending.url({
                query: { from: report.from, to: report.to, ...changes },
            }),
            {},
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Income vs Spending" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title="Income vs Spending">
                    <Input
                        type="date"
                        aria-label="From"
                        className="w-44"
                        value={report.from}
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                    <Input
                        type="date"
                        aria-label="To"
                        className="w-44"
                        value={report.to}
                        onChange={(e) => apply({ to: e.target.value })}
                    />
                </PageHeader>

                <Alert className="border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                    <AlertDescription data-testid="ivs-banner">
                        {banner}
                    </AlertDescription>
                </Alert>

                <Section
                    title="Income"
                    lines={report.income_lines}
                    total={report.income}
                />
                <Section
                    title="Spending"
                    lines={report.spending_lines}
                    total={report.spending}
                />

                <div className="grid gap-1 rounded-md border px-4 py-3">
                    <div className="flex justify-between text-lg font-semibold">
                        <span>Income − Spending</span>
                        <MoneyText
                            value={report.result}
                            signed
                            className={cn(
                                report.result.startsWith('-')
                                    ? 'text-red-700 dark:text-red-400'
                                    : 'text-green-700 dark:text-green-400',
                            )}
                        />
                    </div>
                    <div className="flex justify-between text-sm text-muted-foreground">
                        <span>Rate pending (not in the amounts above)</span>
                        <KgText value={report.pending_kg} />
                    </div>
                </div>
            </div>
        </>
    );
}

function Section({
    title,
    lines,
    total,
}: {
    title: string;
    lines: Line[];
    total: string;
}) {
    return (
        <section className="grid gap-2">
            <h2 className="text-lg font-semibold">{title}</h2>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Account</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {lines.map((line) => (
                        <TableRow key={line.code}>
                            <TableCell>
                                <span className="text-muted-foreground tabular-nums">
                                    {line.code}
                                </span>{' '}
                                {line.name}
                            </TableCell>
                            <TableCell className="text-right">
                                <MoneyText value={line.amount} signed />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell>Total</TableCell>
                        <TableCell className="text-right">
                            <MoneyText value={total} signed />
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </section>
    );
}

IncomeVsSpendingPage.layout = {
    breadcrumbs: [{ title: 'Income vs Spending', href: incomeVsSpending() }],
};
