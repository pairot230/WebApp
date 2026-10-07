<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AuditLog::class);
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_type' => ['nullable', Rule::in(array_keys(AuditLog::TARGET_LABELS))],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if (! empty($filters['date_from']) && ! empty($filters['date_to']) && $filters['date_to'] < $filters['date_from']) {
            throw ValidationException::withMessages(['date_to' => 'วันสิ้นสุดต้องไม่น้อยกว่าวันเริ่มต้น']);
        }
        $query = AuditLog::with('actor');
        foreach (['action', 'actor_id', 'target_type', 'target_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::createFromFormat('!Y-m-d', $filters['date_from'], 'Asia/Bangkok')->utc());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<', Carbon::createFromFormat('!Y-m-d', $filters['date_to'], 'Asia/Bangkok')->addDay()->utc());
        }

        return view('superadmin.audit.index', [
            'logs' => $query->latest('id')->paginate(25)->withQueryString(),
            'actors' => User::orderBy('name')->get(), 'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        Gate::authorize('view', $auditLog);

        return view('superadmin.audit.show', ['log' => $auditLog->load('actor')]);
    }
}
