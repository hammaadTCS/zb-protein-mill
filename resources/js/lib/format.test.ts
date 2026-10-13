import { describe, expect, it } from 'vite-plus/test';
import { formatDate, formatKg, formatMoney, formatRate } from './format';

const lakh2 = { numberFormat: 'lakh', amountRounding: 'two_decimals' } as const;

describe('formatMoney (BR-RPT-05, spec §15.2)', () => {
    it('groups in lakh with two decimals', () => {
        expect(formatMoney('100000.00', lakh2)).toBe('Rs 1,00,000.00');
    });

    it('groups internationally', () => {
        expect(
            formatMoney('100000.00', {
                numberFormat: 'international',
                amountRounding: 'two_decimals',
            }),
        ).toBe('Rs 100,000.00');
    });

    it('groups crores in lakh format', () => {
        expect(formatMoney('12345678.50', lakh2)).toBe('Rs 1,23,45,678.50');
    });

    it('shows whole rupees without decimals', () => {
        expect(
            formatMoney('999.00', {
                numberFormat: 'lakh',
                amountRounding: 'whole_rupee',
            }),
        ).toBe('Rs 999');
    });

    it('shows zero', () => {
        expect(formatMoney('0.00', lakh2)).toBe('Rs 0.00');
    });

    it('rounds half up when showing whole rupees', () => {
        expect(
            formatMoney('1000.50', {
                numberFormat: 'lakh',
                amountRounding: 'whole_rupee',
            }),
        ).toBe('Rs 1,001');
    });

    it('shows a minus only when the caller allows it', () => {
        expect(formatMoney('-96500.00', { ...lakh2, signed: true })).toBe(
            '-Rs 96,500.00',
        );
        expect(() => formatMoney('-1.00', lakh2)).toThrow();
    });

    it('refuses something that is not a decimal number', () => {
        expect(() => formatMoney('12abc', lakh2)).toThrow();
    });
});

describe('formatKg', () => {
    it('shows whole kg', () => {
        expect(formatKg('2800.000', 0)).toBe('2,800 kg');
    });

    it('keeps three decimals', () => {
        expect(formatKg('812.500', 3)).toBe('812.500 kg');
    });

    it('follows the number format', () => {
        expect(formatKg('150000.000', 0, 'lakh')).toBe('1,50,000 kg');
        expect(formatKg('150000.000', 0, 'international')).toBe('150,000 kg');
    });
});

describe('formatRate', () => {
    it('shows the rate with its decimals', () => {
        expect(formatRate('52.5000', 2)).toBe('Rs 52.50');
        expect(formatRate('50.0000', 0)).toBe('Rs 50');
    });
});

describe('formatDate (O-18)', () => {
    it('shows d-m-Y', () => {
        expect(formatDate('2026-01-05', 'd-m-Y')).toBe('05-01-2026');
    });

    it('shows d M Y', () => {
        expect(formatDate('2026-01-05', 'd M Y')).toBe('05 Jan 2026');
    });
});
