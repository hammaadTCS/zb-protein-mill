import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/app/FormField';
import { MoneyText } from '@/components/app/MoneyText';
import { PageHeader } from '@/components/app/PageHeader';
import type { PartyType } from '@/components/app/PartyPicker';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';
import { complete, index, store } from '@/routes/opening-balances';
import { PARTY_TYPE_LABELS } from '@/pages/parties/types';

type Side = 'balance' | 'we_owe' | 'they_owe' | 'capital';
type Opening = { voucher_no: string; amount: string; side: Side } | null;

type AccountRow = {
    id: number;
    name: string;
    type: 'cash' | 'bank';
    opening: Opening;
};
type PartyRow = {
    id: number;
    code: string;
    name: string;
    type: PartyType;
    opening: Opening;
};

/** The balance words of each side, per party type (glossary §2). */
const SIDE_WORDS: Record<PartyType, Partial<Record<Side, string>>> = {
    supplier: { we_owe: 'We owe', they_owe: 'Advance' },
    other: { we_owe: 'We owe', they_owe: 'Advance' },
    buyer: { they_owe: 'Owes us', we_owe: 'Advance' },
    employee: { they_owe: 'Owes us', we_owe: 'We owe' },
    partner: { capital: 'Invested' },
};

/** Page 47: balances on the system start date (BR-OPB-01 to BR-OPB-04). */
export default function OpeningBalances({
    start_date,
    completed,
    equity,
    money_accounts,
    parties,
}: {
    start_date: string | null;
    completed: boolean;
    equity: string;
    money_accounts: AccountRow[];
    parties: PartyRow[];
}) {
    const formats = useFormats();
    const [confirming, setConfirming] = useState(false);

    return (
        <>
            <Head title="Opening balances" />
            <div className="flex flex-col gap-6 p-4">
                <PageHeader title="Opening balances">
                    {!completed && (
                        <Button onClick={() => setConfirming(true)}>
                            Mark complete
                        </Button>
                    )}
                </PageHeader>

                <div className="flex flex-wrap gap-6 text-sm">
                    <span>
                        Date:{' '}
                        <strong>
                            {start_date
                                ? formatDate(start_date, formats.dateFormat)
                                : '–'}
                        </strong>
                    </span>
                    <span>
                        Opening Balance Equity:{' '}
                        <strong>
                            <MoneyText value={equity} signed />
                        </strong>
                    </span>
                </div>

                {completed && (
                    <Alert>
                        <AlertDescription>
                            Opening balances are marked complete and cannot
                            change.
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-2">
                    <h2 className="font-semibold">Accounts</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Amount</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {money_accounts.map((account) => (
                                <OpeningRow
                                    key={`a${account.id}`}
                                    name={account.name}
                                    target={{ money_account_id: account.id }}
                                    opening={account.opening}
                                    sides={{ balance: 'Balance' }}
                                    disabled={completed}
                                />
                            ))}
                        </TableBody>
                    </Table>
                </section>

                <section className="grid gap-2">
                    <h2 className="font-semibold">Parties</h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Amount</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {parties.map((party) => (
                                <OpeningRow
                                    key={`p${party.id}`}
                                    name={`${party.code} – ${party.name} (${PARTY_TYPE_LABELS[party.type]})`}
                                    target={{ party_id: party.id }}
                                    opening={party.opening}
                                    sides={SIDE_WORDS[party.type]}
                                    disabled={completed}
                                />
                            ))}
                        </TableBody>
                    </Table>
                </section>
            </div>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Mark complete</DialogTitle>
                        <DialogDescription className="text-foreground">
                            Opening Balance Equity is{' '}
                            <strong>
                                <MoneyText value={equity} signed />
                            </strong>
                            . It is past results not recorded elsewhere, or a
                            missing balance. After this, opening balances cannot
                            change.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirming(false)}
                        >
                            Close
                        </Button>
                        <Button
                            onClick={() =>
                                router.post(
                                    complete.url(),
                                    { confirmed_obe_amount: equity },
                                    { onFinish: () => setConfirming(false) },
                                )
                            }
                        >
                            Mark complete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function OpeningRow({
    name,
    target,
    opening,
    sides,
    disabled,
}: {
    name: string;
    target: { money_account_id: number } | { party_id: number };
    opening: Opening;
    sides: Partial<Record<Side, string>>;
    disabled: boolean;
}) {
    const sideKeys = Object.keys(sides) as Side[];
    const form = useForm({
        ...target,
        amount: opening?.amount ?? '',
        side: opening?.side ?? sideKeys[0],
    });

    return (
        <TableRow>
            <TableCell className="font-medium whitespace-normal">
                {name}
            </TableCell>
            <TableCell>
                <div className="flex flex-wrap items-start gap-2">
                    <FormField
                        id={`amount-${name}`}
                        label=""
                        error={form.errors.amount}
                    >
                        <Input
                            id={`amount-${name}`}
                            aria-label="Amount"
                            inputMode="decimal"
                            className="w-36 text-right tabular-nums"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                            disabled={disabled}
                        />
                    </FormField>
                    {sideKeys.length > 1 && (
                        <Select
                            value={form.data.side}
                            onValueChange={(value) =>
                                form.setData('side', value as Side)
                            }
                            disabled={disabled}
                        >
                            <SelectTrigger className="w-32" aria-label="Side">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {sideKeys.map((side) => (
                                    <SelectItem key={side} value={side}>
                                        {sides[side]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    {sideKeys.length === 1 &&
                        sides[sideKeys[0]] !== 'Balance' && (
                            <span className="py-2 text-sm text-muted-foreground">
                                {sides[sideKeys[0]]}
                            </span>
                        )}
                </div>
            </TableCell>
            <TableCell className="text-right">
                {!disabled && (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={form.processing || form.data.amount === ''}
                        onClick={() =>
                            form.post(store.url(), { preserveScroll: true })
                        }
                    >
                        Save
                    </Button>
                )}
            </TableCell>
        </TableRow>
    );
}

OpeningBalances.layout = {
    breadcrumbs: [{ title: 'Opening balances', href: index() }],
};
