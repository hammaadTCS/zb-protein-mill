<?php

use Tests\Support\AcceptanceRunner;

/** Spec §17.3 AT-2: oil, an agreed adjustment and unpaid transport, from docs/acceptance/at2.json. */
it('AT-2 oil, adjustment and unpaid transport', function () {
    AcceptanceRunner::load('at2')->setUp()->runAll()->assertExpected();
});
