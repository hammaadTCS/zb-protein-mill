import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { BalanceInWords } from '@/components/app/BalanceInWords';
import type { PartyOption, PartyType } from '@/components/app/PartyPicker';
import { PartyPicker } from '@/components/app/PartyPicker';
import { PageHeader } from '@/components/app/PageHeader';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { index, merge } from '@/routes/parties';
import { store } from '@/routes/parties/merge';

type Words = { words: string; amount: string };

type Picked = PartyOption & { status: string; balance: Words };

const ALL_TYPES: PartyType[] = [
    'supplier',
    'buyer',
    'other',
    'employee',
    'partner',
];

/**
 * Page 20, Merge parties (D-06, BR-PTY-08): the duplicate profile (source) is merged
 * into the real one (target) of the same type. Its balance moves to the target; its
 * entries stay where they are; it becomes inactive.
 */
export default function MergeParties({
    source,
    target,
    result,
}: {
    source: Picked | null;
    target: Picked | null;
    result: Words | null;
}) {
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);
    const sameType = source && target && source.type === target.type;

    const pick = (changes: { source_id?: number; target_id?: number }) => {
        const query = Object.fromEntries(
            Object.entries({
                source_id: source?.id,
                target_id: target?.id,
                ...changes,
            }).filter(([, v]) => v !== undefined),
        ) as Record<string, number>;
        router.get(
            merge.url({ query }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Merge parties" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <PageHeader title="Merge parties" />

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="source">
                            Duplicate profile (will be inactive)
                        </Label>
                        <PartyPicker
                            id="source"
                            types={target ? [target.type] : ALL_TYPES}
                            value={source}
                            onChange={(party) => pick({ source_id: party?.id })}
                        />
                        {source && <BalanceInWords {...source.balance} />}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="target">Merge into</Label>
                        <PartyPicker
                            id="target"
                            types={source ? [source.type] : ALL_TYPES}
                            value={target}
                            onChange={(party) => pick({ target_id: party?.id })}
                        />
                        {target && <BalanceInWords {...target.balance} />}
                    </div>
                </div>

                {source && target && !sameType && (
                    <InputError message="Only parties of the same type can be merged." />
                )}

                {result && source && target && (
                    <div className="grid gap-2 rounded-lg border p-4 text-sm">
                        <p>
                            After the merge, {target.label} ({target.code}):{' '}
                            <BalanceInWords
                                {...result}
                                className="font-semibold"
                            />
                        </p>
                        <p className="text-muted-foreground">
                            {source.label} ({source.code}) keeps its entries and
                            becomes inactive, marked &quot;Merged into{' '}
                            {target.code}&quot;.
                        </p>
                        <Button
                            className="w-fit"
                            onClick={() => {
                                setError(undefined);
                                setConfirming(true);
                            }}
                        >
                            Merge
                        </Button>
                    </div>
                )}
            </div>

            <Dialog
                open={confirming}
                onOpenChange={(value) => !value && setConfirming(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Merge parties</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm">
                        Merge {source?.label} ({source?.code}) into{' '}
                        {target?.label} ({target?.code})? This cannot be undone.
                    </p>
                    <InputError message={error} />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirming(false)}
                        >
                            Close
                        </Button>
                        <Button
                            disabled={processing}
                            onClick={() =>
                                router.post(
                                    store.url(),
                                    {
                                        source_id: source?.id ?? null,
                                        target_id: target?.id ?? null,
                                    },
                                    {
                                        onStart: () => setProcessing(true),
                                        onFinish: () => setProcessing(false),
                                        onError: (errors) =>
                                            setError(
                                                errors.target_id ??
                                                    errors.source_id,
                                            ),
                                    },
                                )
                            }
                        >
                            Merge
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

MergeParties.layout = {
    breadcrumbs: [
        { title: 'Parties', href: index() },
        { title: 'Merge parties', href: merge() },
    ],
};
