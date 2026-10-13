import type { EntryFormProps } from '@/components/app/EntryForm';
import { EntryForm } from '@/components/app/EntryForm';
import { create, index } from '@/routes/purchases';

/** Page 4, New purchase (mobile-first, spec §11.4). */
export default function CreatePurchase(props: EntryFormProps) {
    return <EntryForm kind="purchase" {...props} />;
}

CreatePurchase.layout = {
    breadcrumbs: [
        { title: 'Purchases', href: index() },
        { title: 'New purchase', href: create() },
    ],
};
