import { describe, expect, it } from 'vite-plus/test';
import type { NavEntry } from './navigation';
import { visibleGroups } from './navigation';

const entry = (
    group: NavEntry['group'],
    label: string,
    permission: string | null,
): NavEntry => ({ group, label, href: '/x', permission, mobileFirst: false });

describe('visibleGroups (spec §15.1)', () => {
    const entries = [
        entry('Settings', 'Users', 'users.manage'),
        entry('Dashboard', 'Dashboard', null),
        entry('Daily entries', 'Purchases', 'purchase.view'),
    ];

    it('keeps the spec order and hides entries without permission', () => {
        const can = (p: string | null) => p === null || p === 'purchase.view';

        expect(
            visibleGroups(entries, can).map(({ group, items }) => [
                group,
                items.map((i) => i.label),
            ]),
        ).toEqual([
            ['Dashboard', ['Dashboard']],
            ['Daily entries', ['Purchases']],
        ]);
    });

    it('shows a group once the user may see one of its entries', () => {
        expect(
            visibleGroups(entries, () => true).map(({ group }) => group),
        ).toEqual(['Dashboard', 'Daily entries', 'Settings']);
    });
});
