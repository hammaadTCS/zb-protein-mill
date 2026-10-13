import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ServerWarning } from '@/lib/warnings';
import { readWarning } from '@/lib/warnings';

/**
 * Conventions §5, spec §15.4: a warning the user may confirm. "Go back" closes it;
 * "Save anyway" sends the form again with the warning's code confirmed.
 */
export function WarningDialog({
    warning,
    onGoBack,
    onSaveAnyway,
}: {
    warning: ServerWarning | null;
    onGoBack: () => void;
    onSaveAnyway: (code: string) => void;
}) {
    return (
        <Dialog
            open={warning !== null}
            onOpenChange={(open) => !open && onGoBack()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Check before saving</DialogTitle>
                    <DialogDescription className="text-base text-foreground">
                        {warning?.message}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onGoBack}>
                        Go back
                    </Button>
                    <Button
                        onClick={() => warning && onSaveAnyway(warning.code)}
                    >
                        Save anyway
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The warning flow of one form. `submit(confirmed)` sends the form with the given
 * confirmed codes; a warning from the server opens the dialog instead of an error.
 */
export function useWarnings(
    send: (
        confirmed: string[],
        onHttpException: (response: {
            status: number;
            data: string | Record<string, unknown>;
        }) => boolean | void,
    ) => void,
) {
    const [warning, setWarning] = useState<ServerWarning | null>(null);
    const [confirmed, setConfirmed] = useState<string[]>([]);

    const submit = (codes: string[] = confirmed) =>
        send(codes, (response) => {
            const found = readWarning(response);

            if (found) {
                setWarning(found);

                return false; // handled: no error page
            }
        });

    const dialog = (
        <WarningDialog
            warning={warning}
            onGoBack={() => setWarning(null)}
            onSaveAnyway={(code) => {
                const codes = [...confirmed, code];
                setConfirmed(codes);
                setWarning(null);
                submit(codes);
            }}
        />
    );

    return { submit, dialog, reset: () => setConfirmed([]) };
}
