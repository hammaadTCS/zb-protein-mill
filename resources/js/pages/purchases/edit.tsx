import type {
    EditedPurchase,
    PurchaseFormProps,
} from '@/components/app/PurchaseForm';
import { PurchaseForm } from '@/components/app/PurchaseForm';
import { index } from '@/routes/purchases';

/** Edit a purchase (BR-PUR-11): the New purchase form with the saved values. */
export default function EditPurchase(
    props: PurchaseFormProps & { purchase: EditedPurchase },
) {
    return <PurchaseForm {...props} />;
}

EditPurchase.layout = {
    breadcrumbs: [{ title: 'Purchases', href: index() }],
};
