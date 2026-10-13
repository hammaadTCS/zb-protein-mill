import { usePage } from '@inertiajs/react';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';

/**
 * Card T6.9: on paper only, the company name, the report title, the filters used
 * and "Printed on {date time} by {user}". Hidden on screen.
 */
export function PrintHeader({
    title,
    from,
    to,
    asOf,
    extra,
}: {
    title: string;
    from?: string;
    to?: string;
    asOf?: string;
    extra?: string;
}) {
    const formats = useFormats();
    const { company_name, auth } = usePage<{
        company_name: string | null;
        auth: { user: { name: string } | null };
    }>().props;
    const date = (ymd: string) => formatDate(ymd, formats.dateFormat);
    const filters = [
        from && to ? `From ${date(from)} to ${date(to)}` : null,
        asOf ? `As of ${date(asOf)}` : null,
        extra || null,
    ]
        .filter(Boolean)
        .join(' · ');
    const printedOn = new Date().toLocaleString('en-GB', {
        timeZone: 'Asia/Karachi',
    });

    return (
        <div data-print="only" className="mb-4 hidden border-b pb-2">
            <div className="text-lg font-semibold">{company_name}</div>
            <div className="text-base">{title}</div>
            {filters && <div className="text-sm">{filters}</div>}
            <div className="text-xs">
                Printed on {printedOn} by {auth.user?.name ?? '–'}
            </div>
        </div>
    );
}
