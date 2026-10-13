import type {
    EditedPayment,
    PaymentFormProps,
} from '@/components/app/PaymentForm';
import { PaymentForm } from '@/components/app/PaymentForm';
import { index } from '@/routes/payments';

/** Edit a payment: reversal and repost; the voucher number stays. */
export default function EditPayment(
    props: PaymentFormProps & { payment: EditedPayment },
) {
    return <PaymentForm {...props} />;
}

EditPayment.layout = {
    breadcrumbs: [{ title: 'Payments', href: index() }],
};
