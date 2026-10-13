import { useForm } from '@inertiajs/react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { MoneyText } from '@/components/app/MoneyText';
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
import { Textarea } from '@/components/ui/textarea';
import { newIdempotencyKey } from '@/lib/uuid';

const REASONS = ['quality', 'weight', 'freight', 'other'] as const;

/**
 * Spec §11.7, "Add agreed adjustment" on a purchase or sale with a fixed rate: date,
 * amount (the maximum is shown, BR-ADJ-03), reason and what was agreed (BR-ADJ-04).
 */
export function AdjustmentDialog({
    open,
    title,
    maximum,
    entryDate,
    url,
    today,
    onClose,
}: {
    open: boolean;
    title: string;
    maximum: string;
    entryDate: string;
    url: string;
    today: string;
    onClose: () => void;
}) {
    const blank = () => ({
        idempotency_key: newIdempotencyKey(), // BR-GEN-11
        date: today,
        amount: '',
        reason: '',
        note: '',
    });
    const form = useForm(blank());

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(url, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.setDefaults(blank());
                                form.reset();
                                onClose();
                            },
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            Add agreed adjustment – {title}
                        </DialogTitle>
                    </DialogHeader>
                    <FormField
                        id="adjustment_date"
                        label="Date"
                        error={form.errors.date}
                    >
                        <Input
                            id="adjustment_date"
                            type="date"
                            min={entryDate}
                            max={today}
                            value={form.data.date}
                            onChange={(e) =>
                                form.setData('date', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="adjustment_amount"
                        label="Amount"
                        error={form.errors.amount}
                    >
                        <DecimalInput
                            id="adjustment_amount"
                            value={form.data.amount}
                            onChange={(value) => form.setData('amount', value)}
                            required
                        />
                        <p className="text-sm text-muted-foreground">
                            Maximum <MoneyText value={maximum} />
                        </p>
                    </FormField>
                    <FormField
                        id="adjustment_reason"
                        label="Reason"
                        error={form.errors.reason}
                    >
                        <Select
                            value={form.data.reason}
                            onValueChange={(value) =>
                                form.setData('reason', value)
                            }
                        >
                            <SelectTrigger
                                id="adjustment_reason"
                                className="w-full"
                            >
                                <SelectValue placeholder="Select reason" />
                            </SelectTrigger>
                            <SelectContent>
                                {REASONS.map((reason) => (
                                    <SelectItem key={reason} value={reason}>
                                        {reason[0].toUpperCase() +
                                            reason.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        id="adjustment_note"
                        label="What was agreed"
                        error={form.errors.note}
                    >
                        <Textarea
                            id="adjustment_note"
                            maxLength={2000}
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
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
