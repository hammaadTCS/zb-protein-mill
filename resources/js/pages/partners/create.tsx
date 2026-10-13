import type { PartnerFormProps } from '@/components/app/PartnerForm';
import { PartnerForm } from '@/components/app/PartnerForm';
import { create, index } from '@/routes/partners';

/** Page 22, New partner entry (spec §11.10). */
export default function CreatePartnerEntry(props: PartnerFormProps) {
    return <PartnerForm {...props} />;
}

CreatePartnerEntry.layout = {
    breadcrumbs: [
        { title: 'Partners', href: index() },
        { title: 'New partner entry', href: create() },
    ],
};
