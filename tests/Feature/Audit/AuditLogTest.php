<?php

use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('BR-AUD-02 audit rows cannot be updated or deleted', function (string $operation) {
    $user = User::factory()->create();
    $row = AuditLogger::log('user_changed', $user, ['name' => 'Old'], ['name' => 'New'], by: $user);

    $query = DB::table('audit_logs')->where('id', $row->id);

    expect(fn () => $operation === 'update' ? $query->update(['action' => 'login']) : $query->delete())
        ->toThrow(QueryException::class, 'Rows in audit_logs cannot be updated or deleted');
})->with(['update', 'delete']);

it('BR-AUD-02 the audit model refuses changes too', function () {
    $user = User::factory()->create();
    $row = AuditLogger::log('user_changed', $user, by: $user);

    expect(fn () => $row->update(['action' => 'login']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class)
        ->and(fn () => AuditLog::query()->create(['action' => 'login', 'company_id' => $user->company_id]))
        ->toThrow(LogicException::class, 'only through AuditLogger');
});

it('BR-USR-05 login, failed login and logout are logged', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/logout');

    $rows = AuditLog::query()->orderBy('id')->get(['action', 'user_id', 'auditable_type', 'auditable_id']);

    expect($rows->map(fn (AuditLog $r) => [$r->action, $r->user_id, $r->auditable_type, $r->auditable_id])->all())->toBe([
        ['login_failed', $user->id, 'user', $user->id],
        ['login', $user->id, 'user', $user->id],
        ['logout', $user->id, 'user', $user->id],
    ]);
});

it('BR-USR-05 a failed login for an unknown e-mail is logged without a user', function () {
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);

    $row = AuditLog::query()->sole();

    expect($row->action)->toBe('login_failed')
        ->and($row->user_id)->toBeNull()
        ->and($row->auditable_type)->toBeNull();
});

it('BR-AUD-01 the IP address and device are recorded', function () {
    $user = User::factory()->create();

    $this->withHeader('User-Agent', 'Test Phone')
        ->post('/login', ['email' => $user->email, 'password' => 'password']);

    $row = AuditLog::query()->where('action', 'login')->sole();

    expect($row->ip_address)->toBe('127.0.0.1')
        ->and($row->user_agent)->toBe('Test Phone');
});

it('setting changes are logged with old and new values', function () {
    $owner = User::factory()->create();

    Settings::set('payment_approval_limit', '75000', $owner);

    $row = AuditLog::query()->where('action', 'setting_changed')->sole();

    expect($row->user_id)->toBe($owner->id)
        ->and($row->auditable_type)->toBe('setting')
        ->and($row->old_values)->toBe(['key' => 'payment_approval_limit', 'value' => '50000.00'])
        ->and($row->new_values)->toBe(['key' => 'payment_approval_limit', 'value' => '75000.00']);
});

it('an unchanged setting writes no audit row', function () {
    Settings::set('payment_approval_limit', '50000', User::factory()->create());

    expect(AuditLog::query()->where('action', 'setting_changed')->exists())->toBeFalse();
});

it('an action name outside the list is refused', function () {
    AuditLogger::log('erased', null, by: User::factory()->create());
})->throws(InvalidArgumentException::class);
