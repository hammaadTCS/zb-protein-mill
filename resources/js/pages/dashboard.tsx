import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

/** Page 2. The cards and lists come with T5.6; until then the page is empty. */
export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold text-brand-deep dark:text-foreground">
                    Dashboard
                </h1>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
