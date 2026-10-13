import { useEffect, useState } from 'react';
import { useFormats } from '@/hooks/use-formats';
import { formatDate, formatRate } from '@/lib/format';

/**
 * BR-PUR-05: "Last rate: Rs 52.00 on 06-01-2026" under the rate field. Only a hint:
 * the rate is never filled in automatically.
 */
export function LastRateHint({ url }: { url: string | null }) {
    const formats = useFormats();
    const [last, setLast] = useState<{ rate: string; date: string } | null>(
        null,
    );

    useEffect(() => {
        if (url === null) {
            return;
        }

        const controller = new AbortController();
        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => response.json())
            .then((body: { rate: string; date: string } | null) =>
                setLast(body),
            )
            .catch(() => undefined);

        return () => controller.abort();
    }, [url]);

    if (url === null || last === null) {
        return null;
    }

    return (
        <p className="text-sm text-muted-foreground">
            Last rate:{' '}
            {formatRate(last.rate, formats.rateDecimals, formats.numberFormat)}{' '}
            on {formatDate(last.date, formats.dateFormat)}
        </p>
    );
}
