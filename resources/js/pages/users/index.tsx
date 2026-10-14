import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/app/FormField';
import { PageHeader } from '@/components/app/PageHeader';
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
import {
    disable,
    enable,
    index,
    password as resetPassword,
    role as updateRole,
    store,
} from '@/routes/users';

type UserRow = {
    id: number;
    public_id: string;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    two_factor: boolean;
    must_change_password: boolean;
    last_login: string | null;
    is_me: boolean;
};

const roleLabel = (role: string) => (role === 'owner' ? 'Owner' : 'Accountant');

/**
 * Page 43 (BR-USR-02 to BR-USR-08): add users with a temporary password, change a
 * role, reset a password, disable or enable. Users are never deleted (BR-USR-03).
 */
export default function Users({
    users,
    roles,
}: {
    users: UserRow[];
    roles: string[];
}) {
    const [adding, setAdding] = useState(false);
    const [resetting, setResetting] = useState<UserRow | null>(null);
    const [error, setError] = useState<string | null>(null);
    const onError = (errors: Record<string, string>) =>
        setError(Object.values(errors)[0] ?? null);

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title="Users">
                    <Button onClick={() => setAdding(true)}>Add user</Button>
                </PageHeader>
                {error && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {error}
                    </p>
                )}

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>E-mail</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Two-step login</TableHead>
                            <TableHead>Last login</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.map((user) => (
                            <TableRow key={user.id}>
                                <TableCell>
                                    {user.name}
                                    {user.must_change_password && (
                                        <span className="block text-xs text-muted-foreground">
                                            Temporary password
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell>{user.email}</TableCell>
                                <TableCell>
                                    <Select
                                        value={user.role}
                                        onValueChange={(value) => {
                                            setError(null);
                                            router.put(
                                                updateRole.url(user.public_id),
                                                { role: value },
                                                {
                                                    preserveScroll: true,
                                                    onError,
                                                },
                                            );
                                        }}
                                    >
                                        <SelectTrigger
                                            className="w-36"
                                            aria-label={`Role of ${user.name}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role}
                                                    value={role}
                                                >
                                                    {roleLabel(role)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                                <TableCell>
                                    {user.is_active ? (
                                        <Badge variant="outline">Active</Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            Disabled
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {user.two_factor ? 'On' : 'Off'}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {user.last_login
                                        ? new Date(
                                              user.last_login,
                                          ).toLocaleString('en-GB', {
                                              timeZone: 'Asia/Karachi',
                                          })
                                        : '–'}
                                </TableCell>
                                <TableCell className="text-right">
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setResetting(user)}
                                        >
                                            Reset password
                                        </Button>
                                        {user.is_active ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    setError(null);
                                                    router.post(
                                                        disable.url(
                                                            user.public_id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            onError,
                                                        },
                                                    );
                                                }}
                                            >
                                                Disable
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        enable.url(
                                                            user.public_id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Enable
                                            </Button>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <AddUserDialog
                open={adding}
                roles={roles}
                onClose={() => setAdding(false)}
            />
            {resetting && (
                <ResetPasswordDialog
                    user={resetting}
                    onClose={() => setResetting(null)}
                />
            )}
        </>
    );
}

function AddUserDialog({
    open,
    roles,
    onClose,
}: {
    open: boolean;
    roles: string[];
    onClose: () => void;
}) {
    const form = useForm({
        name: '',
        email: '',
        role: 'accountant',
        password: '',
    });

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url(), {
                            onSuccess: () => {
                                form.reset();
                                onClose();
                            },
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Add user</DialogTitle>
                    </DialogHeader>
                    <FormField
                        id="user_name"
                        label="Name"
                        error={form.errors.name}
                    >
                        <Input
                            id="user_name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="user_email"
                        label="E-mail"
                        error={form.errors.email}
                    >
                        <Input
                            id="user_email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="user_role"
                        label="Role"
                        error={form.errors.role}
                    >
                        <Select
                            value={form.data.role}
                            onValueChange={(value) =>
                                form.setData('role', value)
                            }
                        >
                            <SelectTrigger id="user_role" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {roleLabel(role)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="user_password"
                        label="Temporary password"
                        error={form.errors.password}
                    >
                        <Input
                            id="user_password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Go back
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

function ResetPasswordDialog({
    user,
    onClose,
}: {
    user: UserRow;
    onClose: () => void;
}) {
    const form = useForm({ password: '' });

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(resetPassword.url(user.public_id), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Reset password – {user.name}</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        The user must choose a new password at the next login.
                        Their open sessions end now.
                    </p>
                    <FormField
                        id="reset_password"
                        label="Temporary password"
                        error={form.errors.password}
                    >
                        <Input
                            id="reset_password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Go back
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

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
