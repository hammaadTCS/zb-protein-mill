/** BR-AUD-04, spec §11.6: what changed, who changed it and when. */
export type HistoryRow = {
    at: string;
    user: string | null;
    action: string;
    changes: { field: string; old: string | null; new: string | null }[];
    reason: string | null;
};

export function ChangeHistory({ rows }: { rows: HistoryRow[] }) {
    if (rows.length === 0) {
        return <p className="text-sm text-muted-foreground">No changes.</p>;
    }

    return (
        <ol className="grid gap-3">
            {rows.map((row, index) => (
                <li key={index} className="rounded-md border p-3 text-sm">
                    <div className="flex flex-wrap justify-between gap-2">
                        <span className="font-medium">{row.action}</span>
                        <span className="text-muted-foreground">
                            {row.user ?? '–'} ·{' '}
                            {new Date(row.at).toLocaleString('en-GB', {
                                timeZone: 'Asia/Karachi',
                            })}
                        </span>
                    </div>
                    {row.changes.length > 0 && (
                        <ul className="mt-2 grid gap-1">
                            {row.changes.map((change) => (
                                <li key={change.field} className="tabular-nums">
                                    <span className="text-muted-foreground">
                                        {change.field}:{' '}
                                    </span>
                                    {change.old !== null && (
                                        <>
                                            <span className="line-through">
                                                {change.old}
                                            </span>{' '}
                                            →{' '}
                                        </>
                                    )}
                                    <span>{change.new ?? '–'}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {row.reason && <p className="mt-2">Reason: {row.reason}</p>}
                </li>
            ))}
        </ol>
    );
}
