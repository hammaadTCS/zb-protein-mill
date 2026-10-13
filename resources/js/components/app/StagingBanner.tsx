import { usePage } from '@inertiajs/react';

/** Glossary "Staging banner": on every page of the staging server (conventions §10). */
export function StagingBanner() {
    const envName = usePage().props.app?.env_name;

    if (envName !== 'staging') {
        return null;
    }

    return (
        <div
            role="status"
            className="w-full bg-yellow-300 px-4 py-1.5 text-center text-sm font-semibold text-black"
        >
            TEST COPY – entries here are not real.
        </div>
    );
}
