import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/app/PageHeader';
import { index } from '@/routes/reports';

/** Page 32: every report this user may open. */
export default function ReportsHome({
    reports,
}: {
    reports: { title: string; description: string; url: string }[];
}) {
    return (
        <>
            <Head title="Reports" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
                <PageHeader title="Reports" />
                <div className="grid gap-3 sm:grid-cols-2">
                    {reports.map((report) => (
                        <Link
                            key={report.url}
                            href={report.url}
                            className="rounded-md border px-4 py-3 hover:bg-muted"
                        >
                            <div className="font-semibold">{report.title}</div>
                            <div className="text-sm text-muted-foreground">
                                {report.description}
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

ReportsHome.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
