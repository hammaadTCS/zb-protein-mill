<?php

use Tests\Support\AcceptanceRunner;

/** Spec §17.3: the control scenarios of docs/acceptance/controls.json, one test per case. */
it('AT-3 month-end blocks, then closes after fixes', function () {
    AcceptanceRunner::load('controls', 'AT-3')->setUp()->runAll()->assertExpected();
});

it('AT-4 accountant payment above the limit waits for owner approval', function () {
    AcceptanceRunner::load('controls', 'AT-4')->setUp()->runAll()->assertExpected();
});

it('AT-5 rate correction for an entry in a closed month, with the real close', function () {
    AcceptanceRunner::load('controls', 'AT-5')->setUp()->runAll()->assertExpected();
});

it('AT-6 merge a duplicate supplier', function () {
    AcceptanceRunner::load('controls', 'AT-6')->setUp()->runAll()->assertExpected();
});
