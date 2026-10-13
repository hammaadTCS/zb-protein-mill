import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavEntry } from '@/lib/navigation';
import { visibleGroups } from '@/lib/navigation';

/**
 * The sidebar groups of spec §15.1. An entry is shown only when the user has its
 * permission, and a group with no visible entry is hidden.
 */
export function NavMain({ entries }: { entries: NavEntry[] }) {
    const can = useCan();
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {visibleGroups(entries, can).map(({ group, items }) => (
                <SidebarGroup key={group} className="px-2 py-0">
                    {/* A one-entry group named like its entry needs no label. */}
                    {!(items.length === 1 && items[0].label === group) && (
                        <SidebarGroupLabel className="text-sidebar-foreground/70">
                            {group}
                        </SidebarGroupLabel>
                    )}
                    <SidebarMenu>
                        {items.map((item) => (
                            <SidebarMenuItem key={item.label}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                    tooltip={{ children: item.label }}
                                    className="data-[active=true]:shadow-[inset_3px_0_0_var(--color-brand-gold)]"
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.label}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
