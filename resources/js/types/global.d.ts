import type { Auth } from '@/types/auth';
import type { SharedFormats } from '@/types/formats';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            settings: { formats: SharedFormats | null };
            app: { env_name: string | null };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
        flashDataType: {
            success?: string;
            voucher?: string;
            toast?: import('@/types/ui').FlashToast;
        };
    }
}
