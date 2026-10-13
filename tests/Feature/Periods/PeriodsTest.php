<?php

use App\Domain\Ledger\PostingLine;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\Today;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => freezeToday('2026-01-31'));

it('Today::date is the date in Asia/Karachi', function () {
    // 20:30 UTC on 31 January is already 1 February in Karachi (UTC+5).
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-31 20:30:00', 'UTC'));

    expect(Today::date())->toBe('2026-02-01');
});

it('BR-GEN-02 future date is refused', function () {
    expect(fn () => Periods::assertValidEntryDate('2026-02-01'))
        ->toThrow(ValidationException::class, 'Date cannot be in the future.');
});

it('BR-GEN-02 date before system start is refused', function () {
    expect(fn () => Periods::assertValidEntryDate('2025-12-31'))
        ->toThrow(ValidationException::class, 'Date cannot be before the system start date (01-01-2026).');
});

it('BR-MEC-07 a date in a closed month is refused', function () {
    closeMonthForTest('2026-01');

    expect(Periods::isClosed('2026-01-15'))->toBeTrue()
        ->and(fn () => Periods::assertValidEntryDate('2026-01-15'))
        ->toThrow(ValidationException::class, 'January 2026 is closed. Make this change as a correction in an open month.');
});

it('BR-GEN-02 today, the start date and a past open date are accepted', function () {
    Periods::assertValidEntryDate('2026-01-31');
    Periods::assertValidEntryDate('2026-01-01');
    Periods::assertValidEntryDate('2026-01-15');

    expect(Periods::isClosed('2026-01-15'))->toBeFalse();
});

it('the closed-month error is reported on the date field', function () {
    closeMonthForTest('2026-01');

    try {
        Periods::assertDateOpen('2026-01-15');
        $this->fail('The closed month was not refused.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('date');
    }
});

it('BR-LED-07 PostingService refuses a posting date in a closed month', function () {
    closeMonthForTest('2026-01');

    DB::transaction(fn () => PostingService::post(new PostingRequest(
        postingDate: '2026-01-20',
        sourceType: 'test',
        sourceId: 1,
        description: 'Test',
        lines: [PostingLine::debit('5000', '10.00'), PostingLine::credit('3900', '10.00')],
    ), User::factory()->create()));
})->throws(ValidationException::class, 'January 2026 is closed.');
