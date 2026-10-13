import { describe, expect, it } from 'vite-plus/test';
import { newIdempotencyKey } from './uuid';

describe('newIdempotencyKey (BR-GEN-11)', () => {
    it('makes a v4 UUID, different each time', () => {
        const a = newIdempotencyKey();
        const b = newIdempotencyKey();

        expect(a).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
        );
        expect(a).not.toBe(b);
    });
});
