<?php

declare(strict_types=1);

namespace App\Domain\OpeningBalances\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\OpeningBalances\OpeningBalances;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\NumberFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-OPB-04: before marking openings complete, the Owner confirms the figure left
 * in Opening Balance Equity (3900). After that, openings no longer change (BR-OPB-03).
 */
final class MarkOpeningBalancesComplete
{
    public function handle(User $by, string $confirmedEquity): void
    {
        DB::transaction(function () use ($by, $confirmedEquity): void {
            OpeningBalances::assertEditable();
            $equity = OpeningBalances::equity();

            if (preg_match('/^-?\d+(\.\d{1,2})?$/', $confirmedEquity) !== 1 || Decimal::compare($confirmedEquity, $equity) !== 0) {
                throw ValidationException::withMessages([
                    'confirmed_obe_amount' => __('Opening Balance Equity is :amount. Confirm that figure.', ['amount' => NumberFormat::money($equity)]),
                ]);
            }

            Settings::set(SettingKeys::OPENING_BALANCES_COMPLETED, true, $by);

            AuditLogger::log(AuditActions::OPENING_COMPLETED, null, [], ['opening_balance_equity' => $equity], by: $by);
        });
    }
}
