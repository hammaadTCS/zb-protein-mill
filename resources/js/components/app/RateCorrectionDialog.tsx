import { useForm } from '@inertiajs/react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { KgText } from '@/components/app/KgText';
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
import { Textarea } from '@/components/ui/textarea';
import { useFormats } from '@/hooks/use-formats';
import { formatRate } from '@/lib/format';
import { previewAmount } from '@/lib/money';

/**
 * "Correct rate" for a purchase or sale in a closed month (BR-LED-09): only the
 * difference is posted, on a date in an open month. The total is a preview; the
 * server works out the real amounts (BR-GEN-06).
 */
export function RateCorrectionDialog({
    open,
    title,
    kg,
    currentRate,
    currentAmount,
    url,
    today,
    onClose,
}: {
    open: boolean;
    title: string;
    kg: string;
    currentRate: string;
    currentAmount: string;
    url: string;
    today: string;
    onClose: () => void;
}) {
    const formats = useFormats();
    const form = useForm({ rate: '', date: today, reason: '' });
    const total = previewAmount(kg, form.data.rate, formats.amountRounding);

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
                                form.reset();
                                onClose();
                            },
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Correct rate – {title}</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm">
                        Kg: <KgText value={kg} /> · Rate:{' '}
                        {formatRate(
                            currentRate,
                            formats.rateDecimals,
                            formats.numberFormat,
                        )}{' '}
                        · Amount: <MoneyText value={currentAmount} />
                    </p>
                    <FormField
                        id="correct_rate"
                        label="New rate"
                        error={form.errors.rate}
                    >
                        <DecimalInput
                            id="correct_rate"
                            value={form.data.rate}
                            onChange={(value) => form.setData('rate', value)}
                            required
                        />
                        {total && (
                            <p className="text-sm text-muted-foreground">
                                New amount <MoneyText value={total} />
                            </p>
                        )}
                    </FormField>
                    <FormField
                        id="correct_date"
                        label="Date"
                        error={form.errors.date}
                    >
                        <Input
                            id="correct_date"
                            type="date"
                            max={today}
                            value={form.data.date}
                            onChange={(e) =>
                                form.setData('date', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="correct_reason"
                        label="Reason"
                        error={form.errors.reason}
                    >
                        <Textarea
                            id="correct_reason"
                            maxLength={2000}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
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
