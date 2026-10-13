import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/app/FormField';
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
import { deactivate, index, store, update } from '@/routes/items';

type ItemType = 'raw_material' | 'fuel' | 'product';

type Item = {
    id: number;
    code: string;
    name: string;
    type: ItemType;
    account_code: string | null;
    is_system: boolean;
    is_active: boolean;
    has_entries: boolean;
};

type FormData = { code: string; name: string; type: ItemType };

const TYPE_LABELS: Record<ItemType, string> = {
    raw_material: 'Raw material',
    fuel: 'Fuel',
    product: 'Product',
};

/** Page 45: the items the mill buys and sells, each with its ledger account (BR-ITM-02). */
export default function Items({ items }: { items: Item[] }) {
    const [editing, setEditing] = useState<Item | 'new' | null>(null);
    const nameError = usePage().props.errors.name;

    return (
        <>
            <Head title="Items" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Items">
                    <Button onClick={() => setEditing('new')}>New item</Button>
                </PageHeader>

                {nameError && editing === null && (
                    <Alert variant="destructive">
                        <AlertDescription>{nameError}</AlertDescription>
                    </Alert>
                )}

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Account</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {items.map((item) => (
                            <TableRow key={item.id}>
                                <TableCell className="tabular-nums">
                                    {item.code}
                                </TableCell>
                                <TableCell className="font-medium">
                                    {item.name}
                                </TableCell>
                                <TableCell>{TYPE_LABELS[item.type]}</TableCell>
                                <TableCell className="tabular-nums">
                                    {item.account_code}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {item.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-right">
                                    <div className="flex justify-end gap-2">
                                        {!item.is_system && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setEditing(item)}
                                            >
                                                Edit
                                            </Button>
                                        )}
                                        {item.is_active && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        deactivate.url(item.id),
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
                <ItemDialog
                    item={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function ItemDialog({
    item,
    onClose,
}: {
    item: Item | null;
    onClose: () => void;
}) {
    const form = useForm<FormData>({
        code: item?.code ?? '',
        name: item?.name ?? '',
        type: item?.type ?? 'raw_material',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (item) {
            form.put(update.url(item.id), options);
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
                            {item ? item.name : 'New item'}
                        </DialogTitle>
                    </DialogHeader>

                    <FormField id="code" label="Code" error={form.errors.code}>
                        <Input
                            id="code"
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                            maxLength={10}
                            disabled={item !== null}
                            required
                        />
                    </FormField>

                    <FormField id="name" label="Name" error={form.errors.name}>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            maxLength={100}
                            required
                        />
                    </FormField>

                    <FormField id="type" label="Type" error={form.errors.type}>
                        <Select
                            value={form.data.type}
                            onValueChange={(value) =>
                                form.setData('type', value as ItemType)
                            }
                            disabled={item?.has_entries ?? false}
                        >
                            <SelectTrigger id="type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(TYPE_LABELS).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </FormField>

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

Items.layout = {
    breadcrumbs: [{ title: 'Items', href: index() }],
};
