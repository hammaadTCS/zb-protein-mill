import { describe, expect, it } from 'vite-plus/test';
import { compareDecimals, multiplyToPaisa, previewAmount } from './money';

describe('multiplyToPaisa (BR-GEN-06 preview only)', () => {
    it('multiplies exactly in whole paisa', () => {
        expect(multiplyToPaisa('812.5', '52.5')).toBe('4265625');
    });

    it('rounds half up to the paisa', () => {
        expect(multiplyToPaisa('0.005', '1')).toBe('1');
        expect(multiplyToPaisa('1.000', '52.3333')).toBe('5233');
    });

    it('works on AT-1 figures', () => {
        expect(multiplyToPaisa('800', '50')).toBe('4000000');
    });
});

describe('previewAmount', () => {
    it('gives a money string like the server', () => {
        expect(previewAmount('812.5', '52.5', 'two_decimals')).toBe('42656.25');
        expect(previewAmount('100.5', '1', 'whole_rupee')).toBe('101.00');
    });

    it('is null while the inputs are incomplete', () => {
        expect(previewAmount('', '50', 'two_decimals')).toBeNull();
        expect(previewAmount('800', '', 'two_decimals')).toBeNull();
        expect(previewAmount('80a', '50', 'two_decimals')).toBeNull();
    });
});

describe('compareDecimals (BR-EXP-03 limit in the form)', () => {
    it('compares exactly, whatever the decimals', () => {
        expect(compareDecimals('10000', '10000.00')).toBe(0);
        expect(compareDecimals('9999.99', '10000.00')).toBe(-1);
        expect(compareDecimals('100000.01', '100000')).toBe(1);
    });

    it('is null while the amount is incomplete', () => {
        expect(compareDecimals('', '10000.00')).toBeNull();
        expect(compareDecimals('1.', '10000.00')).toBeNull();
    });
});
