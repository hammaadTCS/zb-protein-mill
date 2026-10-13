/**
 * The menu, as data (spec §15.1). Each task adds its own entries when its route
 * exists. Links come from Wayfinder route functions, never hard-coded URLs.
 */
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Landmark, LayoutGrid } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as moneyAccounts } from '@/routes/money-accounts';

/** Sidebar groups in the order of spec §15.1. */
export const NAV_GROUPS = [
    'Dashboard',
    'Daily entries',
    'People',
    'Money',
    'Checks',
    'Month-end',
    'Reports',
    'Audit log',
    'Settings',
] as const;

export type NavGroup = (typeof NAV_GROUPS)[number];

export type NavEntry = {
    group: NavGroup;
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** Permission key needed to see the entry; null = every logged-in user. */
    permission: string | null;
    /** The page is designed for a phone first (spec §11.1). */
    mobileFirst: boolean;
    icon?: LucideIcon;
};

/** Sidebar entries. */
export const navigation: NavEntry[] = [
    {
        group: 'Dashboard',
        label: 'Dashboard',
        href: dashboard(),
        permission: null,
        mobileFirst: true,
        icon: LayoutGrid,
    },
    {
        group: 'Settings',
        label: 'Accounts setup',
        href: moneyAccounts(),
        permission: 'settings.manage',
        mobileFirst: false,
        icon: Landmark,
    },
];

/**
 * Phone bottom bar (spec §15.1): New purchase, New sale, New payment, Quick expense.
 * Added by the tasks that build those pages.
 */
export const bottomBar: NavEntry[] = [];

/** The groups that have at least one entry the user may see, in menu order. */
export function visibleGroups(
    entries: NavEntry[],
    can: (permission: string | null) => boolean,
): { group: NavGroup; items: NavEntry[] }[] {
    return NAV_GROUPS.map((group) => ({
        group,
        items: entries.filter(
            (entry) => entry.group === group && can(entry.permission),
        ),
    })).filter(({ items }) => items.length > 0);
}
