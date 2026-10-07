<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Complaint::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Complaint::STATUS_LABELS))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $query = Complaint::with(['dorm', 'reporter', 'assignee']);
        if ($request->user()->role !== 'superadmin') {
            $query->where('dorm_id', $request->user()->dorm_id)
                ->whereHas('reporter', fn ($reporter) => $reporter->where('role', '!=', 'superadmin'));
        }
        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }
        foreach (['status', 'assigned_to'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return view('admin.complaints.index', ['complaints' => $query->latest('id')->paginate(20)->withQueryString()]);
    }

    public function show(Request $request, Complaint $complaint): View
    {
        Gate::authorize('update', $complaint);
        $assignees = User::where('is_active', true)->where(function ($query) use ($request, $complaint): void {
            $query->where(function ($admins) use ($complaint): void {
                $admins->where('role', 'admin')->where('dorm_id', $complaint->dorm_id);
            });
            if ($request->user()->role === 'superadmin') {
                $query->orWhere('role', 'superadmin');
            }
        })->orderBy('name')->get();

        return view('admin.complaints.show', [
            'complaint' => $complaint->load(['dorm', 'reporter', 'assignee']), 'assignees' => $assignees,
        ]);
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('update', $complaint);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Complaint::STATUS_LABELS))],
            'assigned_to' => ['present', 'nullable', 'integer', 'exists:users,id'],
            'note' => ['present', 'nullable', 'string', 'max:10000'],
            'user_id' => ['prohibited'], 'dorm_id' => ['prohibited'],
            'title' => ['prohibited'], 'description' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $complaint, $data): void {
            $locked = Complaint::lockForUpdate()->findOrFail($complaint->id);
            Gate::authorize('update', $locked);
            if ($data['assigned_to'] !== null) {
                $assignee = User::lockForUpdate()->findOrFail($data['assigned_to']);
                $eligible = $assignee->is_active && (
                    ($assignee->role === 'admin' && $assignee->dorm_id === $locked->dorm_id)
                    || ($request->user()->role === 'superadmin' && $assignee->role === 'superadmin')
                );
                if (! $eligible) {
                    throw ValidationException::withMessages(['assigned_to' => 'เลือก Admin ที่ใช้งานในหอของเรื่องนี้ หรือ SuperAdmin เมื่อคุณมีสิทธิ์ SuperAdmin']);
                }
            }
            $old = $locked->only(['status', 'assigned_to', 'note']);
            $locked->status = $data['status'];
            $locked->assigned_to = $data['assigned_to'];
            $locked->note = $data['note'];
            if ($locked->isDirty()) {
                $locked->save();
                AuditLog::create([
                    'actor_id' => $request->user()->id, 'action' => 'complaint.updated',
                    'target_type' => Complaint::class, 'target_id' => $locked->id,
                    'old_values' => $old, 'new_values' => $locked->only(['status', 'assigned_to', 'note']),
                ]);
            }
        }, 3);

        return redirect()->route('admin.complaints.show', $complaint)->with('success', 'บันทึกการดำเนินการแล้ว');
    }
}
