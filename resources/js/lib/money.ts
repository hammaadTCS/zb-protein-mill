/**
 * Live total preview on entry forms (spec §15.4). This is only a preview: the server
 * calculates and saves the real amount (BR-GEN-06). Exact integer maths with BigInt
 * on scaled values; never floating point (conventions §3).
 */
import type { AmountRounding } from './format';

const DECIMAL = /^(\d+)(?:\.(\d+))?$/;

/** A decimal string as an integer and its number of decimals: "52.5" -> [525n, 1]. */
function scaled(value: string): [bigint, number] {
    const match = DECIMAL.exec(value.trim());

    if (!match) {
        throw new Error(`Not a positive decimal number: "${value}"`);
    }

    const fraction = match[2] ?? '';

    return [BigInt(match[1] + fraction), fraction.length];
}

/** Integer division rounding half up (both values are not negative). */
function divideHalfUp(value: bigint, divisor: bigint): bigint {
    return (value * 2n + divisor) / (divisor * 2n);
}

/** kg × rate in whole paisa, rounded half up: ("812.5", "52.5") -> "4265625". */
export function multiplyToPaisa(kg: string, rate: string): string {
    const [kgInt, kgScale] = scaled(kg);
    const [rateInt, rateScale] = scaled(rate);
    const product = kgInt * rateInt; // scale kgScale + rateScale
    const scale = kgScale + rateScale;

    if (scale <= 2) {
        return (product * 10n ** BigInt(2 - scale)).toString();
    }

    return divideHalfUp(product, 10n ** BigInt(scale - 2)).toString();
}

/** Whole paisa as a money string with 2 decimals: "4265625" -> "42656.25". */
export function paisaToMoney(paisa: string): string {
    const padded = paisa.padStart(3, '0');

    return `${padded.slice(0, -2)}.${padded.slice(-2)}`;
}

/**
 * The amount the server will save, for showing while typing. Null while kg or rate
 * is empty or not a number yet.
 */
export function previewAmount(
    kg: string,
    rate: string,
    rounding: AmountRounding,
): string | null {
    if (!DECIMAL.test(kg.trim()) || !DECIMAL.test(rate.trim())) {
        return null;
    }

    const paisa = BigInt(multiplyToPaisa(kg, rate));

    if (rounding === 'whole_rupee') {
        return paisaToMoney((divideHalfUp(paisa, 100n) * 100n).toString());
    }

    return paisaToMoney(paisa.toString());
}

/**
 * Compares two positive decimal strings exactly: -1, 0 or 1. Null when either is not
 * a number yet (used for limits shown while typing, such as BR-EXP-03).
 */
export function compareDecimals(a: string, b: string): -1 | 0 | 1 | null {
    if (!DECIMAL.test(a.trim()) || !DECIMAL.test(b.trim())) {
        return null;
    }

    const [aInt, aScale] = scaled(a);
    const [bInt, bScale] = scaled(b);
    const scale = Math.max(aScale, bScale);
    const left = aInt * 10n ** BigInt(scale - aScale);
    const right = bInt * 10n ** BigInt(scale - bScale);

    return left === right ? 0 : left > right ? 1 : -1;
}
