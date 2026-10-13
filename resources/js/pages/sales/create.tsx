import type { EntryFormProps } from '@/components/app/EntryForm';
import { EntryForm } from '@/components/app/EntryForm';
import { create, index } from '@/routes/sales';

/** Page 7, New sale (mobile-first, spec §11.5). */
export default function CreateSale(props: EntryFormProps) {
    return <EntryForm kind="sale" {...props} />;
}

CreateSale.layout = {
    breadcrumbs: [
        { title: 'Sales', href: index() },
        { title: 'New sale', href: create() },
    ],
};
