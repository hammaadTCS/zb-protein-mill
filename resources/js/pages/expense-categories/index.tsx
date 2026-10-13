import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/app/FormField';
import { PageHeader } from '@/components/app/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { deactivate, index, store, update } from '@/routes/expense-categories';

type Category = {
    id: number;
    name: string;
    code: string;
    is_quick: boolean;
    sort_order: number;
    is_active: boolean;
};

/** Page 44: the owner's list of expense categories, each with its ledger account (O-11). */
export default function ExpenseCategories({
    categories,
}: {
    categories: Category[];
}) {
    const [editing, setEditing] = useState<Category | 'new' | null>(null);

    return (
        <>
            <Head title="Expense categories" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Expense categories">
                    <Button onClick={() => setEditing('new')}>
                        New category
                    </Button>
                </PageHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Account</TableHead>
                            <TableHead>Quick</TableHead>
                            <TableHead className="text-right">Order</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {categories.map((category) => (
                            <TableRow key={category.id}>
                                <TableCell className="font-medium">
                                    {category.name}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {category.code}
                                </TableCell>
                                <TableCell>
                                    {category.is_quick ? 'Yes' : ''}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {category.sort_order}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {category.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-right">
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setEditing(category)}
                                        >
                                            Edit
                                        </Button>
                                        {category.is_active && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        deactivate.url(
                                                            category.id,
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
                <CategoryDialog
                    category={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function CategoryDialog({
    category,
    onClose,
}: {
    category: Category | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: category?.name ?? '',
        is_quick: category?.is_quick ?? false,
        sort_order: String(category?.sort_order ?? 0),
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const options = {
                            preserveScroll: true,
                            onSuccess: onClose,
                        };

                        if (category) {
                            form.put(update.url(category.id), options);
                        } else {
                            form.post(store.url(), options);
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {category ? category.name : 'New category'}
                        </DialogTitle>
                    </DialogHeader>
                    <FormField id="name" label="Name" error={form.errors.name}>
                        <Input
                            id="name"
                            value={form.data.name}
                            maxLength={100}
                            required
                            autoFocus
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="sort_order"
                        label="Order"
                        error={form.errors.sort_order}
                    >
                        <Input
                            id="sort_order"
                            inputMode="numeric"
                            value={form.data.sort_order}
                            onChange={(e) =>
                                form.setData(
                                    'sort_order',
                                    e.target.value.replace(/\D/g, ''),
                                )
                            }
                        />
                    </FormField>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_quick"
                            checked={form.data.is_quick}
                            onCheckedChange={(checked) =>
                                form.setData('is_quick', checked === true)
                            }
                        />
                        <Label htmlFor="is_quick">
                            Quick button on Quick expense
                        </Label>
                    </div>
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

ExpenseCategories.layout = {
    breadcrumbs: [{ title: 'Expense categories', href: index() }],
};
