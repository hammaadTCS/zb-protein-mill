import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/app/PageHeader';
import type { PartyOption } from '@/components/app/PartyPicker';
import type { ReportFilterValues } from '@/components/app/ReportFilters';
import { ReportFilters } from '@/components/app/ReportFilters';
import { RateHistory } from '@/components/app/RateHistory';
import type { RateGroup } from '@/components/app/RateHistory';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { rates } from '@/routes/reports';

const ALL = 'all';

/** Page 37, R-10: each fixed rate over time and the weighted average (BR-RPT-03). */
export default function RatesReport({
    filters,
    party,
    items,
    groups,
}: {
    filters: ReportFilterValues;
    party: PartyOption | null;
    items: { id: number; name: string }[];
    groups: RateGroup[];
}) {
    return (
        <>
            <Head title="Rate report" />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Rate report" />
                <ReportFilters
                    url={(query) => rates.url({ query })}
                    filters={filters}
                    party={party}
                >
                    {(apply) => (
                        <Select
                            value={
                                filters.item_id ? String(filters.item_id) : ALL
                            }
                            onValueChange={(value) =>
                                apply({
                                    item_id:
                                        value === ALL ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger aria-label="Item">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All items</SelectItem>
                                {items.map((item) => (
                                    <SelectItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </ReportFilters>
                <RateHistory groups={groups} />
            </div>
        </>
    );
}

RatesReport.layout = {
    breadcrumbs: [{ title: 'Rate report', href: rates() }],
};
