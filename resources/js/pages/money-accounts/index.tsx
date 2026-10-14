import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/app/FormField';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { deactivate, index, store, update } from '@/routes/money-accounts';

type AccountType = 'cash' | 'bank';

type MoneyAccount = {
    id: number;
    public_id: string;
    name: string;
    type: AccountType;
    bank_name: string | null;
    account_title: string | null;
    account_number: string | null;
    code: string;
    balance: string;
    is_active: boolean;
};

type FormData = {
    name: string;
    type: AccountType;
    bank_name: string;
    account_title: string;
    account_number: string;
};

const TYPE_LABELS: Record<AccountType, string> = { cash: 'Cash', bank: 'Bank' };

const EMPTY: FormData = {
    name: '',
    type: 'cash',
    bank_name: '',
    account_title: '',
    account_number: '',
};

/** Page 46: cash and bank accounts, each with its own ledger account (BR-ACC-01). */
export default function MoneyAccounts({
    accounts,
}: {
    accounts: MoneyAccount[];
}) {
    const [editing, setEditing] = useState<MoneyAccount | 'new' | null>(null);
    const accountError = usePage().props.errors.account;

    return (
        <>
            <Head title="Accounts setup" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Accounts setup">
                    <Button onClick={() => setEditing('new')}>
                        New account
                    </Button>
                </PageHeader>

                {accountError && (
                    <Alert variant="destructive">
                        <AlertDescription>{accountError}</AlertDescription>
                    </Alert>
                )}

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead className="text-right">
                                Balance
                            </TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {accounts.map((account) => (
                            <TableRow key={account.id}>
                                <TableCell className="font-medium">
                                    {account.name}
                                </TableCell>
                                <TableCell>
                                    {TYPE_LABELS[account.type]}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {account.code}
                                </TableCell>
                                <TableCell className="text-right">
                                    <MoneyText value={account.balance} signed />
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {account.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-right">
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setEditing(account)}
                                        >
                                            Edit
                                        </Button>
                                        {account.is_active && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        deactivate.url(
                                                            account.public_id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Make inactive
                                            </Button>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {editing !== null && (
                <AccountDialog
                    account={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function AccountDialog({
    account,
    onClose,
}: {
    account: MoneyAccount | null;
    onClose: () => void;
}) {
    const form = useForm<FormData>(
        account
            ? {
                  name: account.name,
                  type: account.type,
                  bank_name: account.bank_name ?? '',
                  account_title: account.account_title ?? '',
                  account_number: account.account_number ?? '',
              }
            : EMPTY,
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (account) {
            form.put(update.url(account.public_id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {account ? account.name : 'New account'}
                        </DialogTitle>
                    </DialogHeader>

                    <FormField id="name" label="Name" error={form.errors.name}>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            maxLength={100}
                            required
                            autoFocus
                        />
                    </FormField>

                    <FormField id="type" label="Type" error={form.errors.type}>
                        <Select
                            value={form.data.type}
                            onValueChange={(value) =>
                                form.setData('type', value as AccountType)
                            }
                            disabled={account !== null}
                        >
                            <SelectTrigger id="type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="bank">Bank</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    {form.data.type === 'bank' && (
                        <>
                            <FormField
                                id="bank_name"
                                label="Bank name"
                                error={form.errors.bank_name}
                            >
                                <Input
                                    id="bank_name"
                                    value={form.data.bank_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'bank_name',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={150}
                                />
                            </FormField>
                            <FormField
                                id="account_title"
                                label="Account title"
                                error={form.errors.account_title}
                            >
                                <Input
                                    id="account_title"
                                    value={form.data.account_title}
                                    onChange={(e) =>
                                        form.setData(
                                            'account_title',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={150}
                                />
                            </FormField>
                            <FormField
                                id="account_number"
                                label="Account number"
                                error={form.errors.account_number}
                            >
                                <Input
                                    id="account_number"
                                    value={form.data.account_number}
                                    onChange={(e) =>
                                        form.setData(
                                            'account_number',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={150}
                                />
                            </FormField>
                        </>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Close
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

MoneyAccounts.layout = {
    breadcrumbs: [{ title: 'Accounts setup', href: index() }],
};
