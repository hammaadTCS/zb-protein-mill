import type { EditedEntry, EntryFormProps } from '@/components/app/EntryForm';
import { EntryForm } from '@/components/app/EntryForm';
import { index } from '@/routes/purchases';

/** Edit a purchase (BR-PUR-11): the New purchase form with the saved values. */
export default function EditPurchase({
    purchase,
    ...props
}: EntryFormProps & { purchase: EditedEntry }) {
    return <EntryForm kind="purchase" entry={purchase} {...props} />;
}

EditPurchase.layout = {
    breadcrumbs: [{ title: 'Purchases', href: index() }],
};
