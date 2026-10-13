<?php

use App\Domain\Settings\Settings;
use App\Support\NumberFormat;

it('BR-RPT-05 server text uses the lakh format and two decimals of the test settings', function () {
    expect(NumberFormat::money('12345678.5'))->toBe('Rs 1,23,45,678.50')
        ->and(NumberFormat::money('999'))->toBe('Rs 999.00')
        ->and(NumberFormat::money('-1500.00'))->toBe('Rs -1,500.00')
        ->and(NumberFormat::kg('2800'))->toBe('2,800.000 kg')
        ->and(NumberFormat::rate('52.5'))->toBe('Rs 52.50');
});

it('BR-RPT-05 international grouping and whole-rupee rounding follow the settings', function () {
    $owner = owner();
    Settings::set('number_format', 'international', $owner);
    Settings::set('amount_rounding', 'whole_rupee', $owner);

    expect(NumberFormat::money('100000.50'))->toBe('Rs 100,001')
        ->and(NumberFormat::money('1234567.49'))->toBe('Rs 1,234,567');
});
