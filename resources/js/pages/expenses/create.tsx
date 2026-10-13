import type { ExpenseFormProps } from '@/components/app/ExpenseForm';
import { ExpenseForm } from '@/components/app/ExpenseForm';
import { create, index } from '@/routes/expenses';

/** Page 13, Quick expense (mobile-first, spec §11.9). */
export default function CreateExpense(props: ExpenseFormProps) {
    return <ExpenseForm {...props} />;
}

CreateExpense.layout = {
    breadcrumbs: [
        { title: 'Expenses', href: index() },
        { title: 'Quick expense', href: create() },
    ],
};
