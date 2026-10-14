import { Head, Link } from '@inertiajs/react';
import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { bottomBar } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Figures = {
    kg_bought: { item: string; kg: string }[];
    kg_sold: { item: string; kg: string }[];
    received: string;
    paid: string;
    expenses: string;
};

type Alert = {
    key: string;
    label: string;
    count: number;
    route: string;
    severity: 'info' | 'warning' | 'danger';
};

type Balance = {
    id: number;
    name: string;
    type: 'cash' | 'bank';
    balance: string;
};

const SEVERITY: Record<Alert['severity'], string> = {
    info: 'border-blue-300 bg-blue-50 text-blue-950 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100',
    warning:
        'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100',
    danger: 'border-red-300 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950 dark:text-red-100',
};

/**
 * Page 2 (spec §11.3): Today, Alerts, Balances and the quick buttons. On a phone
 * Today and Alerts come first, balances after. BR-DSH-01: the server sends no
 * balances without `dashboard.balances`, so the Accountant sees the simple dashboard.
 */
export default function Dashboard({
    today,
    figures,
    balances,
    alerts,
}: {
    today: string;
    figures: Figures;
    balances: Balance[];
    alerts: Alert[];
}) {
    const can = useCan();
    const formats = useFormats();
    const quick = bottomBar.filter((entry) => can(entry.permission));

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Dashboard" />

                {quick.length > 0 && (
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        {quick.map((entry) => (
                            <Button key={entry.label} asChild>
                                <Link href={entry.href}>{entry.label}</Link>
                            </Button>
                        ))}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="grid content-start gap-3 lg:col-span-2">
                        <h2 className="text-lg font-semibold">
                            Today · {formatDate(today, formats.dateFormat)}
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Figure title="Kg bought">
                                {figures.kg_bought.map((row) => (
                                    <Line key={row.item} label={row.item}>
                                        <KgText value={row.kg} />
                                    </Line>
                                ))}
                            </Figure>
                            <Figure title="Kg sold">
                                {figures.kg_sold.map((row) => (
                                    <Line key={row.item} label={row.item}>
                                        <KgText value={row.kg} />
                                    </Line>
                                ))}
                            </Figure>
                            <Figure title="Money">
                                <Line label="Received">
                                    <MoneyText
                                        value={figures.received}
                                        className="text-green-700 dark:text-green-400"
                                    />
                                </Line>
                                <Line label="Paid">
                                    <MoneyText
                                        value={figures.paid}
                                        className="text-red-700 dark:text-red-400"
                                    />
                                </Line>
                            </Figure>
                            <Figure title="Expenses">
                                <Line label="Total">
                                    <MoneyText value={figures.expenses} />
                                </Line>
                            </Figure>
                        </div>
                    </section>

                    <section className="grid content-start gap-3">
                        <h2 className="text-lg font-semibold">Alerts</h2>
                        {alerts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing needs attention.
                            </p>
                        ) : (
                            alerts.map((alert) => (
                                <Link
                                    key={alert.key}
                                    href={alert.route}
                                    className={cn(
                                        'flex items-center justify-between rounded-md border px-4 py-3 text-sm font-medium',
                                        SEVERITY[alert.severity],
                                    )}
                                >
                                    <span>{alert.label}</span>
                                    <span className="tabular-nums">
                                        {alert.count}
                                    </span>
                                </Link>
                            ))
                        )}
                    </section>

                    {balances.length > 0 && (
                        <section
                            id="balances"
                            className="grid content-start gap-3 lg:col-span-3"
                        >
                            <h2 className="text-lg font-semibold">Balances</h2>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {balances.map((account) => (
                                    <div
                                        key={account.id}
                                        className="rounded-md border px-4 py-3"
                                    >
                                        <div className="text-sm text-muted-foreground">
                                            {account.name}
                                        </div>
                                        <MoneyText
                                            value={account.balance}
                                            signed
                                            className={cn(
                                                'text-lg font-semibold',
                                                account.balance.startsWith(
                                                    '-',
                                                ) &&
                                                    'text-red-700 dark:text-red-400',
                                            )}
                                        />
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}

function Figure({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid content-start gap-1 rounded-md border px-4 py-3">
            <div className="text-sm font-semibold">{title}</div>
            {children}
        </div>
    );
}

function Line({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            {children}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
