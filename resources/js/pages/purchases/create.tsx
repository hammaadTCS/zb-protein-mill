import type { PurchaseFormProps } from '@/components/app/PurchaseForm';
import { PurchaseForm } from '@/components/app/PurchaseForm';
import { create, index } from '@/routes/purchases';

/** Page 4, New purchase (mobile-first, spec §11.4). */
export default function CreatePurchase(props: PurchaseFormProps) {
    return <PurchaseForm {...props} />;
}

CreatePurchase.layout = {
    breadcrumbs: [
        { title: 'Purchases', href: index() },
        { title: 'New purchase', href: create() },
    ],
};
