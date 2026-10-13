import { describe, expect, it } from 'vite-plus/test';
import { scaledSize } from './image';

describe('scaledSize (T2.8 browser resize)', () => {
    it('leaves images up to 2000 px alone', () => {
        expect(scaledSize(2000, 1500)).toBeNull();
        expect(scaledSize(800, 600)).toBeNull();
    });

    it('scales the longest side to 2000 px and keeps the proportions', () => {
        expect(scaledSize(4000, 3000)).toEqual({ width: 2000, height: 1500 });
        expect(scaledSize(3024, 4032)).toEqual({ width: 1500, height: 2000 });
        expect(scaledSize(6000, 1)).toEqual({ width: 2000, height: 1 });
    });
});
