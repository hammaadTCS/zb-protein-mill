import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/app/PageHeader';
import { PrintHeader } from '@/components/app/PrintHeader';
import type { PartyOption } from '@/components/app/PartyPicker';
import type { ReportFilterValues } from '@/components/app/ReportFilters';
import { ReportFilters } from '@/components/app/ReportFilters';
import type { WeightGroup } from '@/components/app/WeightDifferences';
import { WeightDifferences } from '@/components/app/WeightDifferences';
import { weightDifference } from '@/routes/reports';

/** Page 38, R-11: their kg against our kg per party (spec §12.4). */
export default function WeightDifferenceReport({
    filters,
    party,
    groups,
}: {
    filters: ReportFilterValues;
    party: PartyOption | null;
    groups: WeightGroup[];
}) {
    return (
        <>
            <Head title="Weight difference" />
            <div className="flex flex-col gap-6 p-4">
                <PrintHeader
                    title="Weight difference"
                    from={filters.from}
                    to={filters.to}
                />
                <PageHeader title="Weight difference" />
                <ReportFilters
                    url={(query) => weightDifference.url({ query })}
                    filters={filters}
                    party={party}
                />
                <WeightDifferences groups={groups} />
            </div>
        </>
    );
}

WeightDifferenceReport.layout = {
    breadcrumbs: [{ title: 'Weight difference', href: weightDifference() }],
};
