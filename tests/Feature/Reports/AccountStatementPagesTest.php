<?php

use App\Domain\Reports\Queries\AccountStatement;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

it('BR-RPT-09 pages of the statement are the one-page statement cut into pieces', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $hbl = $runner->accounts['hbl'];
    $whole = AccountStatement::for($hbl, '2026-01-01', '2026-01-31');

    $pages = array_map(fn (int $page) => AccountStatement::for($hbl, '2026-01-01', '2026-01-31', $page, 3), [1, 2, 3]);

    expect($whole['rows'])->toHaveCount(8)
        ->and(array_merge(...array_column($pages, 'rows')))->toBe($whole['rows'])
        ->and(array_column($pages, 'page'))->toBe([
            ['current_page' => 1, 'last_page' => 3, 'total' => 8, 'per_page' => 3],
            ['current_page' => 2, 'last_page' => 3, 'total' => 8, 'per_page' => 3],
            ['current_page' => 3, 'last_page' => 3, 'total' => 8, 'per_page' => 3],
        ]);

    foreach ($pages as $page) {
        // Opening, closing and the totals always cover the whole range.
        expect([$page['opening'], $page['closing'], $page['total_in'], $page['total_out']])
            ->toBe([$whole['opening'], $whole['closing'], $whole['total_in'], $whole['total_out']]);
    }

    expect($pages[0]['brought_forward'])->toBe('300000.00')
        ->and($pages[1]['brought_forward'])->toBe($whole['rows'][2]['balance'])
        ->and($pages[2]['brought_forward'])->toBe($whole['rows'][5]['balance'])
        ->and($whole['closing'])->toBe(acceptance('at1')['expected']['money_accounts']['hbl']);
});

it('BR-RPT-09 a page number past the end shows the last page', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();

    $page = AccountStatement::for($runner->accounts['hbl'], '2026-01-01', '2026-01-31', 99, 3);

    expect($page['page']['current_page'])->toBe(3)
        ->and($page['rows'])->toHaveCount(2);
});

it('BR-RPT-09 an empty range has one empty page that opens and closes on the same balance', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();

    $page = AccountStatement::for($runner->accounts['hbl'], '2026-02-01', '2026-02-28');

    expect($page['rows'])->toBe([])
        ->and($page['page'])->toBe(['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100])
        ->and($page['opening'])->toBe('417600.00')
        ->and($page['brought_forward'])->toBe('417600.00')
        ->and($page['closing'])->toBe('417600.00');
});

it('BR-RPT-09 the page sends 100 rows per page with previous and next links', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();

    $this->actingAs($runner->owner)->get(route('accounts.show', [$runner->accounts['hbl'], 'from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/show')
            ->has('statement.rows', 8)
            ->where('pages.current_page', 1)
            ->where('pages.last_page', 1)
            ->where('pages.per_page', 100)
            ->where('pages.prev_page_url', null)
            ->where('pages.next_page_url', null));
});
