<?php

// Installed in task T0.3. Protected after merge: never edit to make code pass.
// Each rule enforces a line of docs/conventions.md or CLAUDE.md.

arch('conventions §13: no debugging helpers left in app code')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'print_r']);

arch('conventions §3: no floatval or round on app domain code')
    ->expect('App\Domain')
    ->not->toUse(['floatval', 'round', 'number_format']);

arch('conventions §3: no floatval or round in support code')
    ->expect('App\Support')
    ->not->toUse(['floatval', 'round', 'number_format']);

arch('conventions §1: domain and support code use strict types')
    ->expect(['App\Domain', 'App\Support'])
    ->toUseStrictTypes();

arch('conventions §2: controllers never use the DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('conventions §2: controllers never build journal lines')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'App\Domain\Ledger\PostingService',
        'App\Domain\Ledger\PostingRules',
        'App\Domain\Ledger\Models\JournalEntry',
        'App\Domain\Ledger\Models\JournalLine',
    ]);

arch('CLAUDE.md rule 2: only the Ledger, Reports and Insights modules touch journal models')
    ->expect([
        'App\Domain\Ledger\Models\JournalEntry',
        'App\Domain\Ledger\Models\JournalLine',
    ])
    ->toOnlyBeUsedIn([
        'App\Domain\Ledger',
        'App\Domain\Reports',
        'App\Domain\Insights',
    ]);

arch('CLAUDE.md rule 2: only Ledger code and domain Actions call the PostingService')
    ->expect('App\Domain\Ledger\PostingService')
    ->toOnlyBeUsedIn([
        'App\Domain\Ledger',
        'App\Domain\Purchases\Actions',
        'App\Domain\Sales\Actions',
        'App\Domain\Adjustments\Actions',
        'App\Domain\Payments\Actions',
        'App\Domain\Expenses\Actions',
        'App\Domain\Partners\Actions',
        'App\Domain\Transfers\Actions',
        'App\Domain\OpeningBalances\Actions',
        'App\Domain\Periods\Actions',
        'App\Domain\Parties\Actions',
    ]);

arch('CLAUDE.md rule 3: no soft deletes anywhere in the domain')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Database\Eloquent\SoftDeletes');

arch('conventions §2: actions are classes with a handle method')
    ->expect('App\Domain\*\Actions')
    ->toBeClasses()
    ->toHaveMethod('handle');
