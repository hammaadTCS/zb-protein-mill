import { Link } from '@inertiajs/react';
import { BalanceInWords } from '@/components/app/BalanceInWords';
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
import { cn } from '@/lib/utils';

export type Words = { words: string; amount: string };

export type KhataLine = {
    date: string;
    voucher: string | null;
    description: string;
    increase: string | null;
    decrease: string | null;
    balance: Words;
    route: string | null;
    awaiting_approval?: boolean;
};

/** R-01 khata (spec §15.6): every line with the running balance in words, no minus sign. */
export function KhataTable({
    opening,
    rows,
    closing,
    showOpening,
}: {
    opening: Words;
    rows: KhataLine[];
    closing: Words;
    showOpening: boolean;
}) {
    const formats = useFormats();

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead>Voucher</TableHead>
                    <TableHead>Details</TableHead>
                    <TableHead className="text-right">Increase</TableHead>
                    <TableHead className="text-right">Decrease</TableHead>
                    <TableHead className="text-right">Balance</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {showOpening && (
                    <TableRow className="bg-muted/40">
                        <TableCell colSpan={5}>Opening balance</TableCell>
                        <TableCell className="text-right">
                            <BalanceInWords
                                words={opening.words}
                                amount={opening.amount}
                            />
                        </TableCell>
                    </TableRow>
                )}
                {rows.map((row, index) => (
                    <TableRow
                        key={index}
                        className={cn(
                            row.awaiting_approval &&
                                'text-muted-foreground italic',
                        )}
                    >
                        <TableCell className="tabular-nums">
                            {formatDate(row.date, formats.dateFormat)}
                        </TableCell>
                        <TableCell className="tabular-nums">
                            {row.route && row.voucher ? (
                                <Link
                                    href={row.route}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {row.voucher}
                                </Link>
                            ) : (
                                row.voucher
                            )}
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {row.description}
                        </TableCell>
                        <TableCell className="text-right">
                            {row.increase && <MoneyText value={row.increase} />}
                        </TableCell>
                        <TableCell className="text-right">
                            {row.decrease && <MoneyText value={row.decrease} />}
                        </TableCell>
                        <TableCell className="text-right">
                            <BalanceInWords
                                words={row.balance.words}
                                amount={row.balance.amount}
                            />
                        </TableCell>
                    </TableRow>
                ))}
                {rows.length === 0 && (
                    <TableRow>
                        <TableCell
                            colSpan={6}
                            className="text-center text-muted-foreground"
                        >
                            No entries.
                        </TableCell>
                    </TableRow>
                )}
                <TableRow className="font-semibold">
                    <TableCell colSpan={5}>Closing balance</TableCell>
                    <TableCell className="text-right">
                        <BalanceInWords
                            words={closing.words}
                            amount={closing.amount}
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    );
}
