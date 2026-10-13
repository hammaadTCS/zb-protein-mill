import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { BalanceInWords } from '@/components/app/BalanceInWords';
import type { Paginated } from '@/components/app/Pagination';
import { Pagination } from '@/components/app/Pagination';
import type { PartyType } from '@/components/app/PartyPicker';
import { PageHeader } from '@/components/app/PageHeader';
import { StatusBadge } from '@/components/app/StatusBadge';
import { Badge } from '@/components/ui/badge';
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
import { useCan } from '@/hooks/use-can';
import { create, index, merge, show } from '@/routes/parties';
import { PARTY_TYPE_LABELS, PARTY_TYPES } from './types';

type PartyRow = {
    id: number;
    code: string;
    name: string;
    area: string | null;
    mobile: string;
    type: PartyType;
    status: 'active' | 'inactive';
    needs_review: boolean;
    balance: { words: string; amount: string; pending_kg: string };
};

type Filters = { q: string; type: string; status: string };

const ALL = 'all';

/** Page 17: everyone the mill deals with, with their balance in words. */
export default function Parties({
    parties,
    filters,
}: {
    parties: Paginated<PartyRow>;
    filters: Filters;
}) {
    const can = useCan();
    const [q, setQ] = useState(filters.q);

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, q, ...changes };
        router.get(
            index.url({
                query: Object.fromEntries(
                    Object.entries(next).filter(([, value]) => value !== ''),
                ),
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Parties" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Parties">
                    {can('party.merge') && (
                        <Button variant="outline" asChild>
                            <Link href={merge()}>Merge parties</Link>
                        </Button>
                    )}
                    {can('party.create') && (
                        <Button asChild>
                            <Link href={create()}>New party</Link>
                        </Button>
                    )}
                </PageHeader>

                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({});
                    }}
                >
                    <Input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Name, code or mobile"
                        className="w-full sm:w-64"
                        aria-label="Search"
                    />
                    <Select
                        value={filters.type || ALL}
                        onValueChange={(value) =>
                            apply({ type: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-40" aria-label="Type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All types</SelectItem>
                            {PARTY_TYPES.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status || ALL}
                        onValueChange={(value) =>
                            apply({ status: value === ALL ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-40" aria-label="Status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Area</TableHead>
                            <TableHead>Mobile</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead className="text-right">
                                Balance
                            </TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {parties.data.map((party) => (
                            <TableRow key={party.id}>
                                <TableCell className="tabular-nums">
                                    {party.code}
                                </TableCell>
                                <TableCell className="font-medium">
                                    <Link
                                        href={show(party.id)}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {party.name}
                                    </Link>
                                </TableCell>
                                <TableCell>{party.area}</TableCell>
                                <TableCell className="tabular-nums">
                                    {party.mobile}
                                </TableCell>
                                <TableCell>
                                    {PARTY_TYPE_LABELS[party.type]}
                                </TableCell>
                                <TableCell className="text-right">
                                    <BalanceInWords
                                        words={party.balance.words}
                                        amount={party.balance.amount}
                                        pendingKg={party.balance.pending_kg}
                                    />
                                </TableCell>
                                <TableCell>
                                    <div className="flex gap-1">
                                        <Badge variant="outline">
                                            {party.status === 'active'
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                        {party.needs_review && (
                                            <StatusBadge status="needs_review" />
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {parties.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="text-center text-muted-foreground"
                                >
                                    No party found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <Pagination page={parties} />
            </div>
        </>
    );
}

Parties.layout = {
    breadcrumbs: [{ title: 'Parties', href: index() }],
};
