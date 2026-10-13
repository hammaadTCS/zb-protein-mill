<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Expenses\Models\Expense;
use App\Domain\OpeningBalances\Models\OpeningBalance;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Sales\Models\Sale;
use App\Domain\Transfers\Models\Transfer;
use App\Support\NumberFormat;

/**
 * The voucher number and a short description of the business record behind each
 * journal (khata, statements). Loads each source type in one query. Each entry
 * type adds its arm when it is built.
 */
final class EntrySources
{
    /**
     * @param  list<array{string, int}>  $refs  [source_type, source_id]
     * @return array<string, array{voucher: string, description: string, route: string|null}> keyed "type:id"
     */
    public static function describe(array $refs): array
    {
        $ids = [];

        foreach ($refs as [$type, $id]) {
            $ids[$type][$id] = $id;
        }

        $sources = [];

        foreach ($ids as $type => $list) {
            foreach (self::load($type, array_values($list)) as $id => $source) {
                $sources["{$type}:{$id}"] = $source;
            }
        }

        return $sources;
    }

    /**
     * A journal whose source has no table of its own (conventions §7: a month-end cash
     * adjustment): its description, and the voucher written in it.
     *
     * @return array{voucher: string|null, description: string, route: null}
     */
    public static function fromJournal(string $description): array
    {
        return [
            'voucher' => preg_match('/\b[A-Z]{2,3}-\d{6}\b/', $description, $m) === 1 ? $m[0] : null,
            'description' => $description,
            'route' => null,
        ];
    }

    /** "Waste 800 kg @ 50", or "Waste 400 kg – Rate pending". */
    public static function entryText(string $item, string $kg, ?string $rate): string
    {
        $text = $item.' '.NumberFormat::plain($kg).' kg';

        return $rate === null ? $text.' – Rate pending' : $text.' @ '.NumberFormat::plain($rate);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{voucher: string, description: string, route: string|null}>
     */
    private static function load(string $type, array $ids): array
    {
        return match ($type) {
            'purchase' => Purchase::query()->with('item')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Purchase $p) => [$p->id => [
                    'voucher' => $p->voucher_no,
                    'description' => self::entryText($p->item->name, $p->kg, $p->rate_status === RateStatus::Fixed ? $p->rate : null),
                    'route' => route('purchases.show', $p->id, false),
                ]])->all(),
            'sale' => Sale::query()->with('item')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Sale $s) => [$s->id => [
                    'voucher' => $s->voucher_no,
                    'description' => self::entryText($s->item->name, $s->kg, $s->rate_status === RateStatus::Fixed ? $s->rate : null),
                    'route' => route('sales.show', $s->id, false),
                ]])->all(),
            'payment' => Payment::query()->with('moneyAccount')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Payment $p) => [$p->id => [
                    'voucher' => $p->voucher_no,
                    'description' => ($p->direction === PaymentDirection::Paid ? 'Paid' : 'Received').' – '.$p->moneyAccount->name
                        .($p->reference_no !== null ? ' – '.$p->reference_no : ''),
                    'route' => route('payments.show', $p->id, false),
                ]])->all(),
            'expense' => Expense::query()->with('category')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Expense $e) => [$e->id => [
                    'voucher' => $e->voucher_no,
                    'description' => $e->category->name.' – '.$e->note,
                    'route' => route('expenses.show', $e->id, false),
                ]])->all(),
            'partner_transaction' => PartnerTransaction::query()->with('moneyAccount')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (PartnerTransaction $t) => [$t->id => [
                    'voucher' => $t->voucher_no,
                    'description' => $t->type->label().' – '.$t->moneyAccount->name,
                    'route' => null,
                ]])->all(),
            'adjustment' => Adjustment::query()->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Adjustment $a) => [$a->id => [
                    'voucher' => $a->voucher_no,
                    'description' => 'Agreed adjustment – '.$a->reason->value,
                    'route' => $a->target_type === 'purchase' ? route('purchases.show', $a->purchase_id, false) : route('sales.show', $a->sale_id, false),
                ]])->all(),
            'rate_correction' => RateCorrection::query()->with(['purchase', 'sale'])->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (RateCorrection $c) => [$c->id => [
                    'voucher' => $c->voucher_no,
                    'description' => 'Rate correction '.$c->target()->voucher_no.' – '.NumberFormat::plain($c->old_rate).' → '.NumberFormat::plain($c->new_rate),
                    'route' => $c->target_type === 'purchase' ? route('purchases.show', $c->purchase_id, false) : route('sales.show', $c->sale_id, false),
                ]])->all(),
            'transfer' => Transfer::query()->with(['fromAccount', 'toAccount'])->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Transfer $t) => [$t->id => [
                    'voucher' => $t->voucher_no,
                    'description' => 'Transfer – '.$t->fromAccount->name.' → '.$t->toAccount->name.($t->reference_no !== null ? ' – '.$t->reference_no : ''),
                    'route' => null,
                ]])->all(),
            'opening_balance' => OpeningBalance::query()->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (OpeningBalance $o) => [$o->id => [
                    'voucher' => $o->voucher_no,
                    'description' => 'Opening balance',
                    'route' => null,
                ]])->all(),
            default => [],
        };
    }
}
