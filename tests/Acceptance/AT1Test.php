<?php

use Tests\Support\AcceptanceRunner;

/** Spec §17.2 AT-1: two days of trading, every expected key from docs/acceptance/at1.json. */
it('AT-1 two days of trading', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll()->assertExpected();
});
