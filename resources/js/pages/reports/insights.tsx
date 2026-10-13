import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { PrintHeader } from '@/components/app/PrintHeader';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFormats } from '@/hooks/use-formats';
import { formatRate } from '@/lib/format';
import { show } from '@/routes/insights';
import { index as reportsHome } from '@/routes/reports';

type MonthRow = {
    month: string;
    name: string;
    wood_per_kg_waste: string | null;
    wood_bar: number;
    waste_rate: string | null;
    waste_rate_bar: number;
    product_rates: { item: string; rate: string | null; bar: number }[];
};

type Insights = {
    from: string;
    to: string;
    kg_ratio_protein: string | null;
    kg_ratio_oil_fat: string | null;
    kg_ratio_combined: string | null;
    wood_per_kg_waste: string | null;
    rough_cost_per_kg_sold: string | null;
    totals: {
        waste_kg: string;
        wood_kg: string;
        protein_kg: string;
        oil_fat_kg: string;
        product_kg: string;
        spending: string;
    };
    months: MonthRow[];
    suppliers: {
        party_id: number;
        code: string;
        name: string;
        kg: string;
        average_rate: string | null;
        advance: string;
        weight_difference_percent: string | null;
    }[];
    buyers: {
        party_id: number;
        code: string;
        name: string;
        average_days_to_pay: string | null;
        overdue: string;
        adjustments: string;
        adjustments_percent: string | null;
        share_percent: string | null;
    }[];
};

const BAR_COLOURS = ['bg-brand-green', 'bg-amber-600', 'bg-sky-700'];

/**
 * Page 40, R-13 Business insights (spec §12.5, D-36): every figure is a rough
 * indicator from what is already recorded. Bars are drawn from the whole percents the
 * server sends.
 */
export default function BusinessInsights({ insights }: { insights: Insights }) {
    const formats = useFormats();
    const rate = (value: string | null) =>
        value === null
            ? '–'
            : formatRate(value, formats.rateDecimals, formats.numberFormat);
    const apply = (changes: { from?: string; to?: string }) => {
        router.get(
            show.url({
                query: { from: insights.from, to: insights.to, ...changes },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Business insights" />
            <div className="flex flex-col gap-6 p-4">
                <PrintHeader
                    title="Business insights"
                    from={insights.from}
                    to={insights.to}
                />
                <PageHeader title="Business insights" />

                <div className="grid gap-2 sm:grid-cols-4">
                    <Input
                        type="date"
                        aria-label="From"
                        value={insights.from}
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                    <Input
                        type="date"
                        aria-label="To"
                        value={insights.to}
                        onChange={(e) => apply({ to: e.target.value })}
                    />
                </div>

                <p className="text-sm text-muted-foreground">
                    Every figure on this page is a rough indicator.
                </p>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Figure label="Kg ratio, protein">
                        {insights.kg_ratio_protein ?? '–'}
                    </Figure>
                    <Figure label="Kg ratio, oil/fat">
                        {insights.kg_ratio_oil_fat ?? '–'}
                    </Figure>
                    <Figure label="Kg ratio, combined">
                        {insights.kg_ratio_combined ?? '–'}
                    </Figure>
                    <Figure label="Wood per kg of waste">
                        {insights.wood_per_kg_waste ?? '–'}
                    </Figure>
                    <Figure label="Rough cost per kg sold">
                        {insights.rough_cost_per_kg_sold === null ? (
                            '–'
                        ) : (
                            <MoneyText
                                value={insights.rough_cost_per_kg_sold}
                            />
                        )}
                    </Figure>
                    <Figure label="Kg in the period">
                        <span className="grid text-sm font-normal">
                            <span>
                                Waste bought{' '}
                                <KgText value={insights.totals.waste_kg} />
                            </span>
                            <span>
                                Wood bought{' '}
                                <KgText value={insights.totals.wood_kg} />
                            </span>
                            <span>
                                Product sold{' '}
                                <KgText value={insights.totals.product_kg} />
                            </span>
                        </span>
                    </Figure>
                </div>

                <Section title="Wood per kg of waste by month">
                    <div className="grid gap-2">
                        {insights.months.map((month) => (
                            <Bar
                                key={month.month}
                                label={month.name}
                                percent={month.wood_bar}
                                colour={BAR_COLOURS[0]}
                                value={month.wood_per_kg_waste ?? '–'}
                            />
                        ))}
                    </div>
                </Section>

                <Section title="Buying vs selling rate by month">
                    <div className="flex flex-wrap gap-4 text-xs">
                        <Legend
                            colour={BAR_COLOURS[0]}
                            label="Waste (buying)"
                        />
                        {insights.months[0]?.product_rates.map((p, i) => (
                            <Legend
                                key={p.item}
                                colour={
                                    BAR_COLOURS[(i + 1) % BAR_COLOURS.length]
                                }
                                label={`${p.item} (selling)`}
                            />
                        ))}
                    </div>
                    <div className="grid gap-4">
                        {insights.months.map((month) => (
                            <div key={month.month} className="grid gap-1">
                                <span className="text-sm font-medium">
                                    {month.name}
                                </span>
                                <Bar
                                    label="Waste"
                                    percent={month.waste_rate_bar}
                                    colour={BAR_COLOURS[0]}
                                    value={rate(month.waste_rate)}
                                />
                                {month.product_rates.map((p, i) => (
                                    <Bar
                                        key={p.item}
                                        label={p.item}
                                        percent={p.bar}
                                        colour={
                                            BAR_COLOURS[
                                                (i + 1) % BAR_COLOURS.length
                                            ]
                                        }
                                        value={rate(p.rate)}
                                    />
                                ))}
                            </div>
                        ))}
                    </div>
                </Section>

                <Section title="Supplier comparison">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Supplier</TableHead>
                                <TableHead className="text-right">Kg</TableHead>
                                <TableHead className="text-right">
                                    Average rate
                                </TableHead>
                                <TableHead className="text-right">
                                    Advance
                                </TableHead>
                                <TableHead className="text-right">
                                    Weight difference
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {insights.suppliers.map((row) => (
                                <TableRow key={row.party_id}>
                                    <TableCell>
                                        <span className="text-muted-foreground tabular-nums">
                                            {row.code}
                                        </span>{' '}
                                        {row.name}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <KgText value={row.kg} />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {rate(row.average_rate)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={row.advance} />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.weight_difference_percent === null
                                            ? '–'
                                            : `${row.weight_difference_percent}%`}
                                    </TableCell>
                                </TableRow>
                            ))}
                            <EmptyRow
                                show={insights.suppliers.length === 0}
                                span={5}
                            />
                        </TableBody>
                    </Table>
                </Section>

                <Section title="Buyer comparison">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Buyer</TableHead>
                                <TableHead className="text-right">
                                    Average days to pay
                                </TableHead>
                                <TableHead className="text-right">
                                    Overdue
                                </TableHead>
                                <TableHead className="text-right">
                                    Adjustments
                                </TableHead>
                                <TableHead className="text-right">
                                    Share of sales
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {insights.buyers.map((row) => (
                                <TableRow key={row.party_id}>
                                    <TableCell>
                                        <span className="text-muted-foreground tabular-nums">
                                            {row.code}
                                        </span>{' '}
                                        {row.name}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.average_days_to_pay ?? '–'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={row.overdue} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <MoneyText value={row.adjustments} />
                                        {row.adjustments_percent !== null && (
                                            <span className="block text-xs text-muted-foreground">
                                                {row.adjustments_percent}% of
                                                his sales
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.share_percent === null
                                            ? '–'
                                            : `${row.share_percent}%`}
                                    </TableCell>
                                </TableRow>
                            ))}
                            <EmptyRow
                                show={insights.buyers.length === 0}
                                span={5}
                            />
                        </TableBody>
                    </Table>
                </Section>
            </div>
        </>
    );
}

BusinessInsights.layout = {
    breadcrumbs: [
        { title: 'Reports', href: reportsHome() },
        { title: 'Business insights', href: show() },
    ],
};

function Figure({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 rounded-lg border p-4">
            <span className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                {label}
                <Badge variant="outline" className="text-[0.65rem]">
                    Rough indicator
                </Badge>
            </span>
            <span className="text-xl font-semibold tabular-nums">
                {children}
            </span>
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-3">
            <h2 className="flex items-center gap-2 font-semibold">
                {title}
                <Badge variant="outline" className="text-[0.65rem] font-normal">
                    Rough indicator
                </Badge>
            </h2>
            {children}
        </section>
    );
}

/** A horizontal bar; its length is the whole percent the server worked out. */
function Bar({
    label,
    percent,
    colour,
    value,
}: {
    label: string;
    percent: number;
    colour: string;
    value: string;
}) {
    return (
        <div className="grid grid-cols-[8rem_1fr_6rem] items-center gap-2 text-sm">
            <span className="truncate text-muted-foreground">{label}</span>
            <span className="h-3 rounded bg-muted">
                <span
                    className={`block h-3 rounded ${colour}`}
                    style={{ width: `${percent}%` }}
                />
            </span>
            <span className="text-right tabular-nums">{value}</span>
        </div>
    );
}

function Legend({ colour, label }: { colour: string; label: string }) {
    return (
        <span className="flex items-center gap-1">
            <span className={`inline-block size-3 rounded ${colour}`} />
            {label}
        </span>
    );
}

function EmptyRow({ show, span }: { show: boolean; span: number }) {
    if (!show) {
        return null;
    }

    return (
        <TableRow>
            <TableCell
                colSpan={span}
                className="text-center text-muted-foreground"
            >
                No entries.
            </TableCell>
        </TableRow>
    );
}
