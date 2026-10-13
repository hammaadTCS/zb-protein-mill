import { Link } from '@inertiajs/react';
import { useCan } from '@/hooks/use-can';
import { bottomBar } from '@/lib/navigation';

/** Phone bottom bar (spec §15.1): the quick "new entry" buttons the user may use. */
export function MobileBottomBar() {
    const can = useCan();
    const items = bottomBar.filter((item) => can(item.permission));

    if (items.length === 0) {
        return null;
    }

    return (
        <>
            <div className="h-16 md:hidden" aria-hidden />
            <nav className="fixed inset-x-0 bottom-0 z-40 grid h-16 auto-cols-fr grid-flow-col border-t bg-background md:hidden">
                {items.map((item) => (
                    <Link
                        key={item.label}
                        href={item.href}
                        className="flex flex-col items-center justify-center gap-1 text-xs font-medium"
                    >
                        {item.icon && <item.icon className="size-5" />}
                        {item.label}
                    </Link>
                ))}
            </nav>
        </>
    );
}
