import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/app/FormField';
import { PageHeader } from '@/components/app/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit, update } from '@/routes/company-settings';

type Value = string | number | boolean | null;

type Field = {
    key: string;
    label: string;
    type: 'string' | 'date' | 'decimal' | 'integer' | 'boolean' | 'choice';
    choices: string[] | null;
    min: number | null;
    max: number | null;
    required: boolean;
    value: Value;
    open: string | null;
    locked: string | null;
};

const CHOICE_LABELS: Record<string, string> = {
    whole_rupee: 'Whole rupee',
    two_decimals: 'Two decimals',
    lakh: 'Lakh (1,00,000)',
    international: 'International (100,000)',
    'd-m-Y': '05-10-2026',
    'd M Y': '05 Oct 2026',
};

/**
 * Page 42 (spec §9.6, card T5.1): every limit and format, grouped, each with the
 * OPEN item it answers, and a setup checklist at the top.
 */
export default function CompanySettings({
    groups,
    checklist,
    owner_has_two_factor,
}: {
    groups: { title: string; fields: Field[] }[];
    checklist: {
        missing: string[];
        opening_balances_completed: boolean;
        has_cash_account: boolean;
    };
    owner_has_two_factor: boolean;
}) {
    const initial = Object.fromEntries(
        groups.flatMap((group) =>
            group.fields.map((field) => [
                field.key,
                field.type === 'boolean'
                    ? field.value === true
                    : field.value === null
                      ? ''
                      : String(field.value),
            ]),
        ),
    ) as Record<string, string | boolean>;
    const form = useForm({ values: initial });
    const [confirmTwoFactor, setConfirmTwoFactor] = useState(false);
    const values = form.data.values;
    const errors = form.errors as Record<string, string | undefined>;
    const setValue = (key: string, value: string | boolean) =>
        form.setData('values', { ...values, [key]: value });

    const save = () =>
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () => setConfirmTwoFactor(false),
        });

    const complete =
        checklist.missing.length === 0 &&
        checklist.opening_balances_completed &&
        checklist.has_cash_account;

    return (
        <>
            <Head title="Company settings" />
            <form
                className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    const turningOn =
                        values.owner_two_factor_required === true &&
                        initial.owner_two_factor_required !== true;

                    if (turningOn && !owner_has_two_factor) {
                        setConfirmTwoFactor(true);

                        return;
                    }

                    save();
                }}
            >
                <PageHeader title="Company settings" />

                <Alert variant={complete ? 'default' : 'destructive'}>
                    <AlertTitle>Setup checklist</AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc pl-5">
                            {checklist.missing.length === 0 ? (
                                <li>All required settings are filled.</li>
                            ) : (
                                <li>Missing: {checklist.missing.join(', ')}</li>
                            )}
                            <li>
                                Opening balances:{' '}
                                {checklist.opening_balances_completed
                                    ? 'complete'
                                    : 'not complete'}
                            </li>
                            <li>
                                Cash account:{' '}
                                {checklist.has_cash_account
                                    ? 'present'
                                    : 'add at least one'}
                            </li>
                        </ul>
                    </AlertDescription>
                </Alert>

                {groups.map((group) => (
                    <section key={group.title} className="grid gap-4">
                        <h2 className="text-lg font-semibold">{group.title}</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {group.fields.map((field) => (
                                <SettingField
                                    key={field.key}
                                    field={field}
                                    value={values[field.key]}
                                    error={
                                        errors[`values.${field.key}`] ??
                                        errors[field.key]
                                    }
                                    onChange={(value) =>
                                        setValue(field.key, value)
                                    }
                                />
                            ))}
                        </div>
                    </section>
                ))}

                <Button
                    type="submit"
                    size="lg"
                    className="h-12"
                    disabled={form.processing}
                >
                    Save
                </Button>
            </form>

            <Dialog
                open={confirmTwoFactor}
                onOpenChange={(open) => !open && setConfirmTwoFactor(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Two-step login for owners</DialogTitle>
                        <DialogDescription>
                            You do not have two-step login yet. After saving you
                            will be sent to set it up before you can continue.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmTwoFactor(false)}
                        >
                            Go back
                        </Button>
                        <Button onClick={save} disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function SettingField({
    field,
    value,
    error,
    onChange,
}: {
    field: Field;
    value: string | boolean | undefined;
    error?: string;
    onChange: (value: string | boolean) => void;
}) {
    const id = `setting_${field.key}`;
    const disabled = field.locked !== null;
    const hint = (
        <span className="text-xs text-muted-foreground">
            {field.open && <span className="mr-2">{field.open}</span>}
            {field.locked}
        </span>
    );

    if (field.type === 'boolean') {
        return (
            <div className="grid gap-1">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={id}
                        checked={value === true}
                        disabled={disabled}
                        onCheckedChange={(checked) =>
                            onChange(checked === true)
                        }
                    />
                    <Label htmlFor={id}>{field.label}</Label>
                </div>
                {hint}
                {error && (
                    <p className="text-sm text-red-600 dark:text-red-400">
                        {error}
                    </p>
                )}
            </div>
        );
    }

    return (
        <FormField id={id} label={field.label} error={error}>
            {field.type === 'choice' ? (
                <Select
                    value={typeof value === 'string' ? value : ''}
                    disabled={disabled}
                    onValueChange={onChange}
                >
                    <SelectTrigger id={id} className="h-11 w-full">
                        <SelectValue placeholder="Not set" />
                    </SelectTrigger>
                    <SelectContent>
                        {(field.choices ?? []).map((choice) => (
                            <SelectItem key={choice} value={choice}>
                                {CHOICE_LABELS[choice] ?? choice}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : (
                <Input
                    id={id}
                    className="h-11"
                    type={field.type === 'date' ? 'date' : 'text'}
                    inputMode={
                        field.type === 'decimal'
                            ? 'decimal'
                            : field.type === 'integer'
                              ? 'numeric'
                              : undefined
                    }
                    disabled={disabled}
                    value={typeof value === 'string' ? value : ''}
                    placeholder="Not set"
                    onChange={(e) => onChange(e.target.value)}
                />
            )}
            {hint}
        </FormField>
    );
}

CompanySettings.layout = {
    breadcrumbs: [{ title: 'Company settings', href: edit() }],
};
