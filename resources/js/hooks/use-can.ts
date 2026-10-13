import { usePage } from '@inertiajs/react';

/**
 * Whether the logged-in user has a permission key (spec §7). Only for hiding menu
 * items and buttons: the server checks every request itself (BR-SEC-05).
 */
export function useCan(): (permission: string | null) => boolean {
    const permissions = usePage().props.auth?.user?.permissions ?? [];

    return (permission) =>
        permission === null || permissions.includes(permission);
}
