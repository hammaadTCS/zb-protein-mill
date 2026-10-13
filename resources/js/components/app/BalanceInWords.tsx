import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { cn } from '@/lib/utils';

/**
 * A party balance in words, never with a minus sign (glossary §2, BR-RPT-04):
 * "We owe Rs 52,000", "Advance Rs 20,000", "Settled"; plus "+ 400 kg rate pending".
 */
export function BalanceInWords({
    words,
    amount,
    pendingKg,
    className,
}: {
    words: string;
    /** The absolute amount, as sent by the server. */
    amount: string;
    pendingKg?: string;
    className?: string;
}) {
    const hasPending =
        pendingKg !== undefined && !/^0*(\.0*)?$/.test(pendingKg.trim());

    return (
        <span className={cn('inline-flex flex-col', className)}>
            <span
                className={cn(
                    'tabular-nums',
                    words === 'Advance' && 'text-blue-700 dark:text-blue-300',
                )}
            >
                {words}
                {words !== 'Settled' && (
                    <>
                        {' '}
                        <MoneyText value={amount} />
                    </>
                )}
            </span>
            {hasPending && (
                <span className="text-xs text-muted-foreground">
                    + <KgText value={pendingKg} /> rate pending
                </span>
            )}
        </span>
    );
}
