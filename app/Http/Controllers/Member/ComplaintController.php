<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Complaint::STATUS_LABELS))],
        ]);
        $query = $request->user()->complaints()->with('dorm');
        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('member.complaints.index', ['complaints' => $query->latest('id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Complaint::class);

        return view('member.complaints.create', ['dorm' => $request->user()->dorm]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Complaint::class);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'user_id' => ['prohibited'], 'dorm_id' => ['prohibited'], 'status' => ['prohibited'],
            'assigned_to' => ['prohibited'], 'note' => ['prohibited'],
        ]);
        $complaint = DB::transaction(function () use ($request, $data): Complaint {
            $member = User::lockForUpdate()->findOrFail($request->user()->id);
            Gate::forUser($member)->authorize('create', Complaint::class);
            $complaint = Complaint::create([
                'user_id' => $member->id, 'dorm_id' => $member->dorm_id,
                'title' => $data['title'], 'description' => $data['description'], 'status' => 'pending',
            ]);
            AuditLog::create([
                'actor_id' => $member->id, 'action' => 'complaint.created',
                'target_type' => Complaint::class, 'target_id' => $complaint->id,
                'new_values' => $complaint->only(['user_id', 'dorm_id', 'title', 'description', 'status']),
            ]);

            return $complaint;
        }, 3);

        return redirect()->route('member.complaints.show', $complaint)->with('success', 'ส่งเรื่องร้องเรียนแล้ว');
    }

    public function show(Request $request, Complaint $complaint): View
    {
        abort_unless($complaint->user_id === $request->user()->id, 403);
        Gate::authorize('view', $complaint);

        return view('member.complaints.show', ['complaint' => $complaint->load(['dorm', 'assignee'])]);
    }
}
