import type { PaymentFormProps } from '@/components/app/PaymentForm';
import { PaymentForm } from '@/components/app/PaymentForm';
import { create, index } from '@/routes/payments';

/** Page 10, New payment / receipt (mobile-first, spec §11.8). */
export default function CreatePayment(props: PaymentFormProps) {
    return <PaymentForm {...props} />;
}

CreatePayment.layout = {
    breadcrumbs: [
        { title: 'Payments', href: index() },
        { title: 'New payment', href: create() },
    ],
};
