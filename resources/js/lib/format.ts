/**
 * The only number and date formatting on screen (BR-RPT-05, spec §15.2).
 *
 * Values arrive from the server as decimal strings and are formatted as strings:
 * no parseFloat, no Number(), no floating point anywhere (conventions §3).
 */

export type NumberFormat = 'lakh' | 'international';
export type AmountRounding = 'whole_rupee' | 'two_decimals';
export type DateFormat = 'd-m-Y' | 'd M Y';

export type MoneyFormat = {
    numberFormat: NumberFormat;
    amountRounding: AmountRounding;
    /** Show a leading minus for negative values. Off by default: party balances use words. */
    signed?: boolean;
};

type Decimal = { negative: boolean; whole: string; fraction: string };

const DECIMAL = /^(-)?(\d+)(?:\.(\d+))?$/;
const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

function parse(value: string): Decimal {
    const match = DECIMAL.exec(value.trim());

    if (!match) {
        throw new Error(`Not a decimal number: "${value}"`);
    }

    return {
        negative: match[1] === '-',
        whole: match[2].replace(/^0+(?=\d)/, ''),
        fraction: match[3] ?? '',
    };
}

/** Adds one to a string of digits ("099" -> "100"), keeping its length unless it overflows. */
function incrementDigits(digits: string): string {
    const chars = digits.split('');

    for (let i = chars.length - 1; i >= 0; i--) {
        if (chars[i] !== '9') {
            chars[i] = String.fromCharCode(chars[i].charCodeAt(0) + 1);

            return chars.join('');
        }

        chars[i] = '0';
    }

    return '1' + chars.join('');
}

/** Rounds half up to the given number of decimals, on strings. */
function round(value: Decimal, decimals: number): Decimal {
    const fraction = value.fraction.padEnd(decimals + 1, '0');
    const kept = value.whole + fraction.slice(0, decimals);
    const roundUp = fraction.charAt(decimals) >= '5';
    const digits = roundUp ? incrementDigits(kept) : kept;
    const whole = digits.slice(0, digits.length - decimals) || '0';

    return {
        negative: value.negative,
        whole,
        fraction: digits.slice(digits.length - decimals),
    };
}

/** 1,00,000 (lakh) or 100,000 (international). */
function group(whole: string, numberFormat: NumberFormat): string {
    if (whole.length <= 3) {
        return whole;
    }

    if (numberFormat === 'international') {
        return whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    const lastThree = whole.slice(-3);
    const rest = whole.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');

    return `${rest},${lastThree}`;
}

function isZero(value: Decimal): boolean {
    return /^0*$/.test(value.whole + value.fraction);
}

function digitsText(
    value: Decimal,
    decimals: number,
    numberFormat: NumberFormat,
): string {
    const rounded = round(value, decimals);
    const grouped = group(rounded.whole, numberFormat);

    return decimals > 0 ? `${grouped}.${rounded.fraction}` : grouped;
}

/** Rs 1,00,000.00 / Rs 1,00,000 (spec §15.2). */
export function formatMoney(value: string, format: MoneyFormat): string {
    const parsed = parse(value);
    const decimals = format.amountRounding === 'whole_rupee' ? 0 : 2;
    const text = `Rs ${digitsText(parsed, decimals, format.numberFormat)}`;

    if (parsed.negative && !isZero(parsed)) {
        if (!format.signed) {
            throw new Error(
                'Negative money shown without a sign: use words (glossary §2) or signed.',
            );
        }

        return `-${text}`;
    }

    return text;
}

/** 2,800 kg / 812.500 kg. */
export function formatKg(
    value: string,
    decimals: number,
    numberFormat: NumberFormat = 'lakh',
): string {
    return `${digitsText(parse(value), decimals, numberFormat)} kg`;
}

/** Rs 52.50. */
export function formatRate(
    value: string,
    decimals: number,
    numberFormat: NumberFormat = 'lakh',
): string {
    return `Rs ${digitsText(parse(value), decimals, numberFormat)}`;
}

/** 05-01-2026 or 05 Jan 2026 (O-18). The input is a Y-m-d date string. */
export function formatDate(ymd: string, format: DateFormat): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd);

    if (!match) {
        throw new Error(`Not a Y-m-d date: "${ymd}"`);
    }

    const [, year, month, day] = match;

    return format === 'd-m-Y'
        ? `${day}-${month}-${year}`
        : `${day} ${MONTHS[parseInt(month, 10) - 1]} ${year}`;
}
