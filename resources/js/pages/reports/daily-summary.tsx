import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Input } from '@/components/ui/input';
import { dailySummary } from '@/routes/reports';

type Summary = {
    date: string;
    kg_bought: { item: string; kg: string }[];
    kg_sold: { item: string; kg: string }[];
    received: string;
    paid: string;
    expenses: { category: string; amount: string }[];
    expenses_total: string;
    balances: { id: number; name: string; balance: string }[];
    rate_pending: number;
};

/** Page 29, R-04: one page for any day. */
export default function DailySummary({ summary }: { summary: Summary }) {
    return (
        <>
            <Head title="Daily summary" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title="Daily summary">
                    <Input
                        type="date"
                        aria-label="Date"
                        className="w-44"
                        value={summary.date}
                        onChange={(e) =>
                            router.get(
                                dailySummary.url({
                                    query: { date: e.target.value },
                                }),
                                {},
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                </PageHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Block title="Kg bought">
                        {summary.kg_bought.map((row) => (
                            <Line key={row.item} label={row.item}>
                                <KgText value={row.kg} />
                            </Line>
                        ))}
                    </Block>
                    <Block title="Kg sold">
                        {summary.kg_sold.map((row) => (
                            <Line key={row.item} label={row.item}>
                                <KgText value={row.kg} />
                            </Line>
                        ))}
                    </Block>
                    <Block title="Money">
                        <Line label="Received">
                            <MoneyText
                                value={summary.received}
                                className="text-green-700 dark:text-green-400"
                            />
                        </Line>
                        <Line label="Paid">
                            <MoneyText
                                value={summary.paid}
                                className="text-red-700 dark:text-red-400"
                            />
                        </Line>
                    </Block>
                    <Block title="Expenses">
                        {summary.expenses.map((row) => (
                            <Line key={row.category} label={row.category}>
                                <MoneyText value={row.amount} />
                            </Line>
                        ))}
                        <Line label="Total">
                            <MoneyText
                                value={summary.expenses_total}
                                className="font-semibold"
                            />
                        </Line>
                    </Block>
                    <Block title="Closing balances">
                        {summary.balances.map((row) => (
                            <Line key={row.id} label={row.name}>
                                <MoneyText value={row.balance} signed />
                            </Line>
                        ))}
                    </Block>
                    <Block title="Rate pending">
                        <Line label="Entries of this day">
                            <span className="tabular-nums">
                                {summary.rate_pending}
                            </span>
                        </Line>
                    </Block>
                </div>
            </div>
        </>
    );
}

function Block({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid content-start gap-1 rounded-md border px-4 py-3">
            <h2 className="text-sm font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Line({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            {children}
        </div>
    );
}

DailySummary.layout = {
    breadcrumbs: [{ title: 'Daily summary', href: dailySummary() }],
};
