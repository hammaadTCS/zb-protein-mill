import { useFormats } from '@/hooks/use-formats';
import { formatKg } from '@/lib/format';
import { cn } from '@/lib/utils';

/** A kg value from the server, formatted per the company settings (O-14). */
export function KgText({
    value,
    className,
}: {
    value: string;
    className?: string;
}) {
    const formats = useFormats();

    return (
        <span className={cn('tabular-nums', className)}>
            {formatKg(value, formats.kgDecimals, formats.numberFormat)}
        </span>
    );
}
