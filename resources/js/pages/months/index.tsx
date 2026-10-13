import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmWithReason } from '@/components/app/ConfirmWithReason';
import { PageHeader } from '@/components/app/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show as monthEnd } from '@/routes/month-end';
import { index, reopen } from '@/routes/months';

type MonthRow = {
    key: string;
    name: string;
    status: 'open' | 'closed';
    closed_by: string | null;
    closed_at: string | null;
    can_reopen: boolean;
};

/**
 * Page 31, Months list (spec §11.12): every month from the start month to now, its
 * status and who closed it. Only the latest closed month can be reopened, by the
 * Owner, with a reason (BR-MEC-08).
 */
export default function Months({
    months,
    can_reopen,
}: {
    months: MonthRow[];
    can_reopen: boolean;
}) {
    const [reopening, setReopening] = useState<MonthRow | null>(null);
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title="Months" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <PageHeader title="Months">
                    <Button variant="outline" asChild>
                        <Link href={monthEnd()}>Month-end close</Link>
                    </Button>
                </PageHeader>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Month</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Closed by</TableHead>
                            <TableHead>Closed on</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {months.map((month) => (
                            <TableRow key={month.key}>
                                <TableCell className="font-medium">
                                    {month.name}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        className={
                                            month.status === 'closed'
                                                ? 'border-transparent bg-neutral-200 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200'
                                                : ''
                                        }
                                    >
                                        {month.status === 'closed'
                                            ? 'Closed'
                                            : 'Open'}
                                    </Badge>
                                </TableCell>
                                <TableCell>{month.closed_by ?? '–'}</TableCell>
                                <TableCell className="tabular-nums">
                                    {month.closed_at
                                        ? new Date(
                                              month.closed_at,
                                          ).toLocaleString('en-GB', {
                                              timeZone: 'Asia/Karachi',
                                          })
                                        : '–'}
                                </TableCell>
                                <TableCell className="text-right">
                                    {can_reopen && month.can_reopen && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setError(undefined);
                                                setReopening(month);
                                            }}
                                        >
                                            Reopen month
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <ConfirmWithReason
                open={reopening !== null}
                title={`Reopen ${reopening?.name ?? ''}`}
                confirmLabel="Reopen month"
                error={error}
                processing={processing}
                onClose={() => setReopening(null)}
                onConfirm={(reason) => {
                    if (reopening === null) {
                        return;
                    }

                    router.post(
                        reopen.url(reopening.key),
                        { reason },
                        {
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                            onSuccess: () => setReopening(null),
                            onError: (errors) =>
                                setError(errors.reason ?? errors.period),
                        },
                    );
                }}
            />
        </>
    );
}

Months.layout = {
    breadcrumbs: [{ title: 'Months', href: index() }],
};
