import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/** Spec §15.3 badges with their glossary words. Colours never carry the meaning alone. */
const BADGES = {
    rate_pending: {
        label: 'Rate pending',
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    },
    awaiting_approval: {
        label: 'Awaiting approval',
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    },
    advance: {
        label: 'Advance',
        className:
            'border-transparent bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    },
    overdue: {
        label: 'Overdue',
        className:
            'border-transparent bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
    },
    needs_review: {
        label: 'Needs review',
        className:
            'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    },
    rejected: {
        label: 'Rejected',
        className:
            'border-red-600 bg-transparent text-red-700 dark:text-red-300',
    },
    cancelled: {
        label: 'Cancelled',
        className:
            'border-transparent bg-neutral-200 text-neutral-700 line-through dark:bg-neutral-800 dark:text-neutral-300',
    },
} as const;

export type BadgeStatus = keyof typeof BADGES;

export function StatusBadge({
    status,
    className,
}: {
    status: BadgeStatus;
    className?: string;
}) {
    const badge = BADGES[status];

    return (
        <Badge variant="outline" className={cn(badge.className, className)}>
            {badge.label}
        </Badge>
    );
}
