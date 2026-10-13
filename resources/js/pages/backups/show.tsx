import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/app/PageHeader';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/backups';

type Kind = {
    configured: boolean;
    late: boolean;
    last_success: {
        finished_at: string | null;
        size_mb: string | null;
        copies: string | null;
    } | null;
    last_attempt: {
        result: 'success' | 'failure';
        finished_at: string | null;
        error: string | null;
    } | null;
};

const karachi = (iso: string | null) =>
    iso === null
        ? '–'
        : new Date(iso).toLocaleString('en-GB', { timeZone: 'Asia/Karachi' });

/**
 * Page 48, Backup status (D-40, BR-OPS-07): what the server backup scripts last
 * reported. Read-only; there is no download (ADR-0006).
 */
export default function Backups({
    status,
    late_after_hours,
}: {
    status: { db: Kind; files: Kind };
    late_after_hours: { db: number; files: number };
}) {
    return (
        <>
            <Head title="Backups" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title="Backups" />
                <p className="text-sm text-muted-foreground">
                    Backups are encrypted and stored off the server. They can
                    only be restored by the key holders.
                </p>
                <KindCard
                    title="Database"
                    kind={status.db}
                    lateAfter={late_after_hours.db}
                />
                <KindCard
                    title="Uploads"
                    kind={status.files}
                    lateAfter={late_after_hours.files}
                />
            </div>
        </>
    );
}

Backups.layout = {
    breadcrumbs: [{ title: 'Backups', href: show() }],
};

function KindCard({
    title,
    kind,
    lateAfter,
}: {
    title: string;
    kind: Kind;
    lateAfter: number;
}) {
    return (
        <section className="grid gap-3 rounded-lg border p-4">
            <div className="flex items-center justify-between gap-2">
                <h2 className="font-semibold">{title}</h2>
                {kind.configured &&
                    (kind.late ? (
                        <Badge className="border-transparent bg-red-600 text-white">
                            Late
                        </Badge>
                    ) : (
                        <Badge
                            variant="outline"
                            className="border-transparent bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100"
                        >
                            OK
                        </Badge>
                    ))}
            </div>

            {!kind.configured ? (
                <p className="text-sm text-muted-foreground">
                    Backups are not configured on this server.
                </p>
            ) : (
                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">
                            Last successful backup
                        </dt>
                        <dd className="tabular-nums">
                            {karachi(kind.last_success?.finished_at ?? null)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Size</dt>
                        <dd className="tabular-nums">
                            {kind.last_success?.size_mb
                                ? `${kind.last_success.size_mb} MB`
                                : '–'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Copies made</dt>
                        <dd>{kind.last_success?.copies ?? '–'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Last attempt</dt>
                        <dd>
                            {kind.last_attempt === null
                                ? '–'
                                : `${kind.last_attempt.result === 'success' ? 'Succeeded' : 'Failed'}, ${karachi(kind.last_attempt.finished_at)}`}
                        </dd>
                    </div>
                    {kind.last_attempt?.error && (
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">Error</dt>
                            <dd className="font-medium text-red-700 dark:text-red-400">
                                {kind.last_attempt.error}
                            </dd>
                        </div>
                    )}
                    <p className="text-xs text-muted-foreground sm:col-span-2">
                        Late when the last successful backup is older than{' '}
                        {lateAfter} hours.
                    </p>
                </dl>
            )}
        </section>
    );
}
