import { MoneyText } from '@/components/app/MoneyText';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFormats } from '@/hooks/use-formats';
import { formatDate } from '@/lib/format';

export type JournalLineRow = {
    entry_no: string;
    posting_date: string;
    reversal_of: string | null;
    code: string;
    account: string;
    party: string | null;
    debit: string;
    credit: string;
};

/**
 * Spec §11.6: the journal lines of an entry, for the Owner only (the server sends
 * them only with `audit.view`). Debit and Credit are allowed here (glossary §5).
 */
export function JournalLines({ rows }: { rows: JournalLineRow[] }) {
    const formats = useFormats();

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Journal</TableHead>
                    <TableHead>Posting date</TableHead>
                    <TableHead>Account</TableHead>
                    <TableHead>Party</TableHead>
                    <TableHead className="text-right">Debit</TableHead>
                    <TableHead className="text-right">Credit</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, index) => (
                    <TableRow key={index}>
                        <TableCell className="tabular-nums">
                            {row.entry_no}
                            {row.reversal_of && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    (reverses {row.reversal_of})
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="tabular-nums">
                            {formatDate(row.posting_date, formats.dateFormat)}
                        </TableCell>
                        <TableCell>
                            {row.code} {row.account}
                        </TableCell>
                        <TableCell>{row.party}</TableCell>
                        <TableCell className="text-right">
                            {row.debit !== '0.00' && (
                                <MoneyText value={row.debit} />
                            )}
                        </TableCell>
                        <TableCell className="text-right">
                            {row.credit !== '0.00' && (
                                <MoneyText value={row.credit} />
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
