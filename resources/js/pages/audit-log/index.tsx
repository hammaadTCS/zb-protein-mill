import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Paginated } from '@/components/app/Pagination';
import { Pagination } from '@/components/app/Pagination';
import { PageHeader } from '@/components/app/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/audit-log';

type Row = {
    at: string;
    user: string | null;
    action: string;
    record: { type: string; label: string; url: string | null } | null;
    changes: { field: string; old: string | null; new: string | null }[];
    reason: string | null;
    ip: string | null;
};

type Filters = {
    user_id: string | null;
    action: string;
    from: string;
    to: string;
    type: string;
    q: string;
};

const ALL = 'all';

/**
 * Page 41 (BR-AUD-03): who did what and when, newest first. Filters: user, action,
 * date range, record type, voucher or party.
 */
export default function AuditLogPage({
    logs,
    filters,
    users,
    actions,
    types,
}: {
    logs: Paginated<Row>;
    filters: Filters;
    users: { id: number; public_id: string; name: string }[];
    actions: { value: string; label: string }[];
    types: string[];
}) {
    const [q, setQ] = useState(filters.q);

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, q, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== '' && v !== null),
        ) as Record<string, string | number>;
        router.get(
            index.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Audit log" />

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-7"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({});
                    }}
                >
                    <Input
                        type="date"
                        aria-label="From"
                        value={filters.from}
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                    <Input
                        type="date"
                        aria-label="To"
                        value={filters.to}
                        onChange={(e) => apply({ to: e.target.value })}
                    />
                    <Select
                        value={filters.user_id ? filters.user_id : ALL}
                        onValueChange={(value) =>
                            apply({
                                user_id: value === ALL ? null : value,
                            })
                        }
                    >
                        <SelectTrigger aria-label="User">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All users</SelectItem>
                            {users.map((user) => (
                                <SelectItem
                                    key={user.id}
                                    value={user.public_id}
                                >
                                    {user.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.action || ALL}
                        onValueChange={(value) =>
                            apply({ action: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger aria-label="Action">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All actions</SelectItem>
                            {actions.map((action) => (
                                <SelectItem
                                    key={action.value}
                                    value={action.value}
                                >
                                    {action.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.type || ALL}
                        onValueChange={(value) =>
                            apply({ type: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger aria-label="Record type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All records</SelectItem>
                            {types.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {type.replaceAll('_', ' ')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Voucher or party"
                        aria-label="Search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Time</TableHead>
                            <TableHead>User</TableHead>
                            <TableHead>Action</TableHead>
                            <TableHead>Record</TableHead>
                            <TableHead>Changes</TableHead>
                            <TableHead>Reason</TableHead>
                            <TableHead>IP</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {logs.data.map((row, i) => (
                            <TableRow key={i} className="align-top">
                                <TableCell className="whitespace-nowrap tabular-nums">
                                    {new Date(row.at).toLocaleString('en-GB', {
                                        timeZone: 'Asia/Karachi',
                                    })}
                                </TableCell>
                                <TableCell>{row.user ?? '–'}</TableCell>
                                <TableCell>{row.action}</TableCell>
                                <TableCell>
                                    {row.record ? (
                                        <>
                                            <span className="block text-xs text-muted-foreground">
                                                {row.record.type}
                                            </span>
                                            {row.record.url ? (
                                                <Link
                                                    href={row.record.url}
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {row.record.label}
                                                </Link>
                                            ) : (
                                                row.record.label
                                            )}
                                        </>
                                    ) : (
                                        '–'
                                    )}
                                </TableCell>
                                <TableCell className="max-w-96">
                                    <ul className="grid gap-0.5 text-sm">
                                        {row.changes.map((change) => (
                                            <li
                                                key={change.field}
                                                className="break-words"
                                            >
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
                                                {change.new ?? '–'}
                                            </li>
                                        ))}
                                    </ul>
                                </TableCell>
                                <TableCell>{row.reason ?? '–'}</TableCell>
                                <TableCell className="tabular-nums">
                                    {row.ip ?? '–'}
                                </TableCell>
                            </TableRow>
                        ))}
                        {logs.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="text-center text-muted-foreground"
                                >
                                    No entries.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <Pagination page={logs} />
            </div>
        </>
    );
}

AuditLogPage.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
