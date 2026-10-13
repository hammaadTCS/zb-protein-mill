<?php

use Database\Seeders\SystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // conventions §11: system data in every feature test (test settings follow in T1.2).
        $this->seed(SystemDataSeeder::class);
    })
    ->in('Feature');
