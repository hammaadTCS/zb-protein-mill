import { useForm } from '@inertiajs/react';
import { DecimalInput } from '@/components/app/DecimalInput';
import { FormField } from '@/components/app/FormField';
import { KgText } from '@/components/app/KgText';
import { MoneyText } from '@/components/app/MoneyText';
import { useWarnings } from '@/components/app/WarningDialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useFormats } from '@/hooks/use-formats';
import { previewAmount } from '@/lib/money';

/**
 * "Add rate" for a rate-pending purchase or sale (BR-PUR-04). The total shown is a
 * preview; the server saves kg × rate (BR-GEN-06).
 */
export function AddRateDialog({
    open,
    title,
    kg,
    url,
    today,
    onClose,
}: {
    open: boolean;
    title: string;
    kg: string;
    url: string;
    today: string;
    onClose: () => void;
}) {
    const formats = useFormats();
    const form = useForm({ rate: '', date: today });
    const total = previewAmount(kg, form.data.rate, formats.amountRounding);

    const warnings = useWarnings((confirmed, onHttpException) => {
        form.transform((data) => ({ ...data, confirmed_warnings: confirmed }));
        form.post(url, {
            preserveScroll: true,
            onHttpException,
            onSuccess: () => {
                form.reset();
                warnings.reset();
                onClose();
            },
        });
    });

    return (
        <>
            <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
                <DialogContent>
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            warnings.submit();
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Add rate – {title}</DialogTitle>
                        </DialogHeader>
                        <p className="text-sm">
                            Kg: <KgText value={kg} />
                        </p>
                        <FormField
                            id="add_rate"
                            label="Rate"
                            error={form.errors.rate}
                        >
                            <DecimalInput
                                id="add_rate"
                                value={form.data.rate}
                                onChange={(value) =>
                                    form.setData('rate', value)
                                }
                                autoFocus
                                required
                            />
                        </FormField>
                        <FormField
                            id="add_rate_date"
                            label="Date"
                            error={form.errors.date}
                        >
                            <Input
                                id="add_rate_date"
                                type="date"
                                className="h-11"
                                max={today}
                                value={form.data.date}
                                onChange={(event) =>
                                    form.setData('date', event.target.value)
                                }
                            />
                        </FormField>
                        <div className="flex items-center justify-between rounded-md bg-muted px-4 py-3">
                            <span className="text-sm text-muted-foreground">
                                Total
                            </span>
                            {total !== null ? (
                                <MoneyText
                                    value={total}
                                    className="font-semibold"
                                />
                            ) : (
                                '–'
                            )}
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
                                Add rate
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            {warnings.dialog}
        </>
    );
}
