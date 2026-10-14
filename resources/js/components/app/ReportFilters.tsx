import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { PartyOption } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type ReportFilterValues = {
    type: string;
    from: string;
    to: string;
    party_id: string | null;
    [key: string]: string | number | null;
};

/**
 * The filters R-10, R-11 and R-12 share (spec §12): purchases or sales, date range,
 * party; each report can add its own after them.
 */
export function ReportFilters({
    url,
    filters,
    party,
    children,
}: {
    url: (query: Record<string, string | number>) => string;
    filters: ReportFilterValues;
    party: PartyOption | null;
    children?: (
        apply: (changes: Partial<ReportFilterValues>) => void,
    ) => ReactNode;
}) {
    const apply = (changes: Partial<ReportFilterValues>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== '' && v !== null),
        ) as Record<string, string | number>;
        router.get(url(query), {}, { preserveState: true, replace: true });
    };

    return (
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <Select
                value={filters.type}
                onValueChange={(value) =>
                    apply({ type: value, party_id: null, item_id: null })
                }
            >
                <SelectTrigger aria-label="Type">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="purchase">Purchases</SelectItem>
                    <SelectItem value="sale">Sales</SelectItem>
                </SelectContent>
            </Select>
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
                types={[filters.type === 'sale' ? 'buyer' : 'supplier']}
                value={party}
                placeholder="All parties"
                onChange={(picked) =>
                    apply({ party_id: picked?.public_id ?? null })
                }
            />
            {children?.(apply)}
        </div>
    );
}
