import { usePage } from '@inertiajs/react';
import type { Formats } from '@/types';

/**
 * The company's display formats. Until setup is complete some settings are empty;
 * the screens then show numbers in a plain default (entries are blocked anyway, BR-GEN-07).
 */
export function useFormats(): Formats {
    const formats = usePage().props.settings?.formats;

    return {
        kgDecimals: formats?.kg_decimals ?? 3,
        rateDecimals: formats?.rate_decimals ?? 2,
        amountRounding: formats?.amount_rounding ?? 'two_decimals',
        numberFormat: formats?.number_format ?? 'lakh',
        dateFormat: formats?.date_format ?? 'd-m-Y',
    };
}
