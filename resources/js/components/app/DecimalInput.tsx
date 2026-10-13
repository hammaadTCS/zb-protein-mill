import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Kg, rate and money fields (spec §15.4): the number keypad on phones, digits and
 * one dot only. The value stays a string; the server decides the saved number.
 */
export function DecimalInput({
    value,
    onChange,
    className,
    ...props
}: Omit<React.ComponentProps<typeof Input>, 'value' | 'onChange'> & {
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <Input
            inputMode="decimal"
            autoComplete="off"
            value={value}
            onChange={(event) => {
                const next = event.target.value.replace(/[^\d.]/g, '');

                if (/^\d*\.?\d*$/.test(next)) {
                    onChange(next);
                }
            }}
            className={cn('h-11 text-right tabular-nums', className)}
            {...props}
        />
    );
}
