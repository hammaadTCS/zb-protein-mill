/**
 * The menu, as data (spec §15.1). Each task adds its own entries when its route
 * exists. Links come from Wayfinder route functions, never hard-coded URLs.
 */
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    CheckCheck,
    Clock,
    Tags,
    FileSpreadsheet,
    Handshake,
    Banknote,
    Landmark,
    LayoutGrid,
    Package,
    Receipt,
    ShoppingCart,
    Users,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as items } from '@/routes/items';
import { index as moneyAccounts } from '@/routes/money-accounts';
import { index as openingBalances } from '@/routes/opening-balances';
import { index as parties } from '@/routes/parties';
import { index as partners } from '@/routes/partners';
import { index as expenseCategories } from '@/routes/expense-categories';
import { create as newExpense, index as expenses } from '@/routes/expenses';
import { create as newPayment, index as payments } from '@/routes/payments';
import { index as approvals } from '@/routes/approvals';
import { index as ratePending } from '@/routes/rate-pending';
import { create as newSale, index as sales } from '@/routes/sales';
import { trialBalance } from '@/routes/reports';
import { create as newPurchase, index as purchases } from '@/routes/purchases';

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
        group: 'Daily entries',
        label: 'Purchases',
        href: purchases(),
        permission: 'purchase.view',
        mobileFirst: false,
        icon: ShoppingCart,
    },
    {
        group: 'Daily entries',
        label: 'Sales',
        href: sales(),
        permission: 'sale.view',
        mobileFirst: false,
        icon: Receipt,
    },
    {
        group: 'Daily entries',
        label: 'Payments',
        href: payments(),
        permission: 'payment.view',
        mobileFirst: false,
        icon: Banknote,
    },
    {
        group: 'Daily entries',
        label: 'Expenses',
        href: expenses(),
        permission: 'expense.view',
        mobileFirst: false,
        icon: Wallet,
    },
    {
        group: 'People',
        label: 'Parties',
        href: parties(),
        permission: 'party.view',
        mobileFirst: false,
        icon: Users,
    },
    {
        group: 'People',
        label: 'Partners',
        href: partners(),
        permission: 'partner.view',
        mobileFirst: false,
        icon: Handshake,
    },
    {
        group: 'Checks',
        label: 'Rate pending',
        href: ratePending(),
        permission: 'purchase.view',
        mobileFirst: false,
        icon: Clock,
    },
    {
        group: 'Checks',
        label: 'Approvals',
        href: approvals(),
        permission: 'payment.approve',
        mobileFirst: true,
        icon: CheckCheck,
    },
    {
        group: 'Reports',
        label: 'Trial balance',
        href: trialBalance(),
        permission: 'report.view',
        mobileFirst: false,
        icon: FileSpreadsheet,
    },
    {
        group: 'Settings',
        label: 'Accounts setup',
        href: moneyAccounts(),
        permission: 'settings.manage',
        mobileFirst: false,
        icon: Landmark,
    },
    {
        group: 'Settings',
        label: 'Expense categories',
        href: expenseCategories(),
        permission: 'settings.manage',
        mobileFirst: false,
        icon: Tags,
    },
    {
        group: 'Settings',
        label: 'Items',
        href: items(),
        permission: 'settings.manage',
        mobileFirst: false,
        icon: Package,
    },
    {
        group: 'Settings',
        label: 'Opening balances',
        href: openingBalances(),
        permission: 'opening.manage',
        mobileFirst: false,
        icon: BookOpen,
    },
];

/**
 * Phone bottom bar (spec §15.1): New purchase, New sale, New payment, Quick expense.
 * Added by the tasks that build those pages.
 */
export const bottomBar: NavEntry[] = [
    {
        group: 'Daily entries',
        label: 'New purchase',
        href: newPurchase(),
        permission: 'purchase.create',
        mobileFirst: true,
        icon: ShoppingCart,
    },
    {
        group: 'Daily entries',
        label: 'New sale',
        href: newSale(),
        permission: 'sale.create',
        mobileFirst: true,
        icon: Receipt,
    },
    {
        group: 'Daily entries',
        label: 'New payment',
        href: newPayment(),
        permission: 'payment.create',
        mobileFirst: true,
        icon: Banknote,
    },
    {
        group: 'Daily entries',
        label: 'Quick expense',
        href: newExpense(),
        permission: 'expense.create',
        mobileFirst: true,
        icon: Wallet,
    },
];

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
