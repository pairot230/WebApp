<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RepairRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RepairRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RepairRequest::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(RepairRequest::STATUS_LABELS))],
            'category' => ['nullable', Rule::in(array_keys(RepairRequest::CATEGORIES))],
        ]);
        $query = RepairRequest::with(['dorm', 'reporter']);
        if ($request->user()->role !== 'superadmin') {
            $query->where('dorm_id', $request->user()->dorm_id)
                ->whereHas('reporter', fn ($reporter) => $reporter->where('role', '!=', 'superadmin'));
        }
        if (! empty($filters['search'])) {
            $query->where(function ($search) use ($filters): void {
                foreach (['reporter_name', 'room_label', 'description', 'phone', 'email'] as $field) {
                    $search->orWhere($field, 'like', '%'.$filters['search'].'%');
                }
            });
        }
        foreach (['status', 'category'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return view('admin.repairs.index', ['repairs' => $query->latest('id')->paginate(20)->withQueryString()]);
    }

    public function show(RepairRequest $repair): View
    {
        Gate::authorize('update', $repair);

        return view('admin.repairs.show', ['repair' => $repair->load(['dorm', 'reporter'])]);
    }

    public function update(Request $request, RepairRequest $repair): RedirectResponse
    {
        Gate::authorize('update', $repair);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RepairRequest::STATUS_LABELS))],
            'user_id' => ['prohibited'], 'dorm_id' => ['prohibited'], 'room_id' => ['prohibited'],
            'reporter_name' => ['prohibited'], 'reporter_type' => ['prohibited'], 'room_label' => ['prohibited'],
            'category' => ['prohibited'], 'description' => ['prohibited'], 'phone' => ['prohibited'],
            'email' => ['prohibited'], 'appointment_date' => ['prohibited'], 'appointment_time' => ['prohibited'],
            'assigned_to' => ['prohibited'], 'note' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $repair, $data): void {
            $locked = RepairRequest::lockForUpdate()->findOrFail($repair->id);
            Gate::authorize('update', $locked);
            $old = $locked->status;
            $locked->status = $data['status'];
            if ($locked->isDirty()) {
                $locked->save();
                AuditLog::create([
                    'actor_id' => $request->user()->id, 'action' => 'repair.status_updated',
                    'target_type' => RepairRequest::class, 'target_id' => $locked->id,
                    'old_values' => ['status' => $old], 'new_values' => ['status' => $locked->status],
                ]);
            }
        }, 3);

        return redirect()->route('admin.repairs.show', $repair)->with('success', 'อัปเดตสถานะงานซ่อมแล้ว');
    }
}
