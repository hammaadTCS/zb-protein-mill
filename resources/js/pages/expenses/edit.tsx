import type {
    EditedExpense,
    ExpenseFormProps,
} from '@/components/app/ExpenseForm';
import { ExpenseForm } from '@/components/app/ExpenseForm';
import { index } from '@/routes/expenses';

/** Edit an expense: reversal and repost; the voucher number stays. */
export default function EditExpense(
    props: ExpenseFormProps & { expense: EditedExpense },
) {
    return <ExpenseForm {...props} />;
}

EditExpense.layout = {
    breadcrumbs: [{ title: 'Expenses', href: index() }],
};
