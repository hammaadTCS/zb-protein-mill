import type { AmountRounding, DateFormat, NumberFormat } from '@/lib/format';

/** Display formats shared by the server (O-14 to O-18). Null while setup is incomplete. */
export type SharedFormats = {
    kg_decimals: number | null;
    rate_decimals: number | null;
    amount_rounding: AmountRounding | null;
    number_format: NumberFormat | null;
    date_format: DateFormat | null;
};

/** The same formats with every value filled, ready for lib/format.ts. */
export type Formats = {
    kgDecimals: number;
    rateDecimals: number;
    amountRounding: AmountRounding;
    numberFormat: NumberFormat;
    dateFormat: DateFormat;
};
