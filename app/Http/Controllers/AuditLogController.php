<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Reports\Queries\AuditTrail;
use App\Domain\Reports\Queries\ChangeHistory;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Models\User;
use App\Support\Today;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Page 41, Audit log (BR-AUD-03): Owner only. */
class AuditLogController extends Controller
{
    use ListsEntries;

    public function index(Request $request): Response
    {
        Gate::authorize('audit.view');

        $types = array_keys(Relation::morphMap());
        $input = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::in(AuditActions::all())],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::in($types)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $today = Today::date();
        $filters = [
            'user_id' => isset($input['user_id']) ? (int) $input['user_id'] : null,
            'action' => (string) ($input['action'] ?? ''),
            'from' => $input['from'] ?? substr($today, 0, 8).'01', // spec §11.2: current month
            'to' => $input['to'] ?? $today,
            'type' => (string) ($input['type'] ?? ''),
            'q' => trim((string) ($input['q'] ?? '')),
        ];

        return Inertia::render('audit-log/index', [
            'filters' => $filters,
            'users' => User::query()->where('company_id', $this->actor($request)->company_id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])->values(),
            'actions' => array_map(fn (string $a): array => ['value' => $a, 'label' => ChangeHistory::ACTIONS[$a] ?? ucfirst(str_replace('_', ' ', $a))], AuditActions::all()),
            'types' => $types,
            'logs' => $this->paginated(AuditTrail::page($filters), fn (AuditLog $log): array => AuditTrail::row($log)),
        ]);
    }
}
