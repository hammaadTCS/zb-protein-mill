<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Users\Actions\CreateUser;
use App\Domain\Users\Actions\DisableUser;
use App\Domain\Users\Actions\EnableUser;
use App\Domain\Users\Actions\ResetUserPassword;
use App\Domain\Users\Actions\UpdateUserRole;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Models\User;
use App\Support\Flash;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Page 43, Users and permissions (BR-USR-02 to BR-USR-08). Users are never deleted (BR-USR-03). */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('users.manage');

        $users = User::query()->where('company_id', $this->actor($request)->company_id)->with('roles')->orderBy('name')->get();
        $lastLogins = AuditLog::query()
            ->where('action', AuditActions::LOGIN)
            ->whereIn('user_id', $users->pluck('id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(created_at) AS last_login')
            ->pluck('last_login', 'user_id');

        return Inertia::render('users/index', [
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => (string) $user->getRoleNames()->first(),
                'is_active' => $user->is_active,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'must_change_password' => $user->must_change_password,
                'last_login' => isset($lastLogins[$user->id]) ? (string) $lastLogins[$user->id] : null,
                'is_me' => $user->id === $request->user()?->id,
            ])->values(),
            'roles' => [RolesAndPermissionsSeeder::OWNER, RolesAndPermissionsSeeder::ACCOUNTANT],
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $action): RedirectResponse
    {
        Gate::authorize('users.manage');

        /** @var array{name: string, email: string, role: string, password: string} $data */
        $data = $request->validated();
        $user = $action->handle($this->actor($request), $data);
        Flash::success(__('User :name added.', ['name' => $user->name]));

        return to_route('users.index');
    }

    public function updateRole(Request $request, User $user, UpdateUserRole $action): RedirectResponse
    {
        Gate::authorize('users.manage');
        $this->sameCompany($request, $user);

        $role = (string) $request->validate(['role' => ['required', Rule::in([RolesAndPermissionsSeeder::OWNER, RolesAndPermissionsSeeder::ACCOUNTANT])]])['role'];
        $action->handle($this->actor($request), $user, $role);
        Flash::success(__('Saved.'));

        return to_route('users.index');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user, ResetUserPassword $action): RedirectResponse
    {
        Gate::authorize('users.manage');
        $this->sameCompany($request, $user);

        $action->handle($this->actor($request), $user, (string) $request->validated('password'));
        Flash::success(__('Saved.'));

        return to_route('users.index');
    }

    public function disable(Request $request, User $user, DisableUser $action): RedirectResponse
    {
        Gate::authorize('users.manage');
        $this->sameCompany($request, $user);

        $action->handle($this->actor($request), $user);
        Flash::success(__('Saved.'));

        return to_route('users.index');
    }

    public function enable(Request $request, User $user, EnableUser $action): RedirectResponse
    {
        Gate::authorize('users.manage');
        $this->sameCompany($request, $user);

        $action->handle($this->actor($request), $user);
        Flash::success(__('Saved.'));

        return to_route('users.index');
    }

    /** BR-GEN-01: a user of another company is not found. */
    private function sameCompany(Request $request, User $user): void
    {
        abort_unless($user->company_id === $this->actor($request)->company_id, 404);
    }
}
