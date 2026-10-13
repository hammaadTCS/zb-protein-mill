import { useFormats } from '@/hooks/use-formats';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

/** A money value from the server, formatted per the company settings (BR-RPT-05). */
export function MoneyText({
    value,
    signed = false,
    className,
}: {
    value: string;
    /** Allow a leading minus (only reports such as Income vs Spending need it). */
    signed?: boolean;
    className?: string;
}) {
    const formats = useFormats();

    return (
        <span className={cn('tabular-nums', className)}>
            {formatMoney(value, {
                numberFormat: formats.numberFormat,
                amountRounding: formats.amountRounding,
                signed,
            })}
        </span>
    );
}
