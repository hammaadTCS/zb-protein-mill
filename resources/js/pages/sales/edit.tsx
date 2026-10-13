import type { EditedEntry, EntryFormProps } from '@/components/app/EntryForm';
import { EntryForm } from '@/components/app/EntryForm';
import { index } from '@/routes/sales';

/** Edit a sale: the New sale form with the saved values. */
export default function EditSale({
    sale,
    ...props
}: EntryFormProps & { sale: EditedEntry }) {
    return <EntryForm kind="sale" entry={sale} {...props} />;
}

EditSale.layout = {
    breadcrumbs: [{ title: 'Sales', href: index() }],
};
