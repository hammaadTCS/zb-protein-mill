import { Head, Link } from '@inertiajs/react';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/accounts';

type Account = {
    id: number;
    public_id: string;
    name: string;
    type: 'cash' | 'bank';
    is_active: boolean;
    balance: string;
};

/** Page 24 (R-06): each cash and bank account with its balance today. */
export default function Accounts({ accounts }: { accounts: Account[] }) {
    return (
        <>
            <Head title="Accounts" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <PageHeader title="Accounts" />
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Account</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead className="text-right">
                                Balance
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {accounts.map((account) => (
                            <TableRow key={account.id}>
                                <TableCell>
                                    <Link
                                        href={show(account.public_id)}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {account.name}
                                    </Link>
                                    {!account.is_active && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            Inactive
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {account.type === 'bank' ? 'Bank' : 'Cash'}
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText
                                        value={account.balance}
                                        signed
                                        className={cn(
                                            'font-semibold',
                                            account.balance.startsWith('-') &&
                                                'text-red-700 dark:text-red-400',
                                        )}
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

Accounts.layout = {
    breadcrumbs: [{ title: 'Accounts', href: index() }],
};
