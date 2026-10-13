import type {
    EditedTransfer,
    TransferFormProps,
} from '@/components/app/TransferForm';
import { TransferForm } from '@/components/app/TransferForm';
import { index } from '@/routes/transfers';

/** Edit a transfer: reversal and repost; the voucher number stays. */
export default function EditTransfer(
    props: TransferFormProps & { transfer: EditedTransfer },
) {
    return <TransferForm {...props} />;
}

EditTransfer.layout = {
    breadcrumbs: [{ title: 'Transfers', href: index() }],
};
