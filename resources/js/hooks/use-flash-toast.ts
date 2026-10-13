import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

/** A toast after every save, showing the voucher number (spec §15.5). */
export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;
            const success = flash?.success as string | undefined;

            if (success) {
                toast.success(success);
            }

            if (data) {
                toast[data.type](data.message);
            }
        });
    }, []);
}
