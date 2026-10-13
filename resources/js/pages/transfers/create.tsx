import type { TransferFormProps } from '@/components/app/TransferForm';
import { TransferForm } from '@/components/app/TransferForm';
import { create, index } from '@/routes/transfers';

/** Page 16, New transfer (spec §11.10). */
export default function CreateTransfer(props: TransferFormProps) {
    return <TransferForm {...props} />;
}

CreateTransfer.layout = {
    breadcrumbs: [
        { title: 'Transfers', href: index() },
        { title: 'New transfer', href: create() },
    ],
};
