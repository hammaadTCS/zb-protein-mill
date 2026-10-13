import type {
    EditedPartnerEntry,
    PartnerFormProps,
} from '@/components/app/PartnerForm';
import { PartnerForm } from '@/components/app/PartnerForm';
import { index } from '@/routes/partners';

/** Edit a partner entry: reversal and repost; the voucher number stays. */
export default function EditPartnerEntry(
    props: PartnerFormProps & { entry: EditedPartnerEntry },
) {
    return <PartnerForm {...props} />;
}

EditPartnerEntry.layout = {
    breadcrumbs: [{ title: 'Partners', href: index() }],
};
