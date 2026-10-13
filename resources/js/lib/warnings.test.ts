import { describe, expect, it } from 'vite-plus/test';
import { readWarning } from './warnings';

const warning = {
    code: 'duplicate_party',
    message: 'A party with this mobile already exists: Aslam (S-0001).',
    details: { code: 'S-0001' },
};

describe('readWarning (conventions §5)', () => {
    it('reads a warning from a 422 JSON body', () => {
        expect(readWarning({ status: 422, data: { warning } })).toEqual(
            warning,
        );
        expect(
            readWarning({ status: 422, data: JSON.stringify({ warning }) }),
        ).toEqual(warning);
    });

    it('ignores other failures', () => {
        expect(readWarning({ status: 500, data: { warning } })).toBeNull();
        expect(readWarning({ status: 422, data: 'not json' })).toBeNull();
        expect(readWarning({ status: 422, data: { message: 'x' } })).toBeNull();
    });
});
