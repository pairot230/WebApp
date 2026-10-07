<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RepairRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RepairRequestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(RepairRequest::STATUS_LABELS))],
            'category' => ['nullable', Rule::in(array_keys(RepairRequest::CATEGORIES))],
        ]);
        $query = $request->user()->repairRequests()->with('dorm');
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

        return view('member.repairs.index', ['repairs' => $query->latest('id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', RepairRequest::class);

        return view('member.repairs.create', ['member' => $request->user()->load(['dorm', 'room'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', RepairRequest::class);
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'reporter_name' => ['required', 'string', 'max:255'],
            'reporter_type' => ['required', Rule::in(array_keys(RepairRequest::REPORTER_TYPES))],
            'room_label' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(RepairRequest::CATEGORIES))],
            'description' => ['required', 'string', 'max:10000'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+() .-]{6,30}$/'],
            'email' => ['required', 'email', 'max:255'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'user_id' => ['prohibited'], 'dorm_id' => ['prohibited'], 'room_id' => ['prohibited'],
            'status' => ['prohibited'], 'assigned_to' => ['prohibited'], 'note' => ['prohibited'],
        ]);
        $appointment = Carbon::createFromFormat('!Y-m-d H:i', $data['appointment_date'].' '.$data['appointment_time'], 'Asia/Bangkok');
        if ($appointment->isPast()) {
            throw ValidationException::withMessages(['appointment_date' => 'กรุณาเลือกวันและเวลานัดหมายในอนาคต']);
        }
        $repair = DB::transaction(function () use ($request, $data): RepairRequest {
            $member = User::lockForUpdate()->findOrFail($request->user()->id);
            Gate::forUser($member)->authorize('create', RepairRequest::class);
            if ($member->room_id !== null && $member->room?->floor?->building?->dorm_id !== $member->dorm_id) {
                throw ValidationException::withMessages(['room_label' => 'ข้อมูลห้องและหอพักของบัญชีไม่ตรงกัน กรุณาติดต่อเจ้าหน้าที่']);
            }
            $repair = RepairRequest::create([
                'user_id' => $member->id, 'dorm_id' => $member->dorm_id, 'room_id' => $member->room_id,
                'reporter_name' => $data['reporter_name'], 'reporter_type' => $data['reporter_type'],
                'room_label' => $data['room_label'], 'category' => $data['category'],
                'description' => $data['description'], 'phone' => $data['phone'], 'email' => $data['email'],
                'appointment_date' => $data['appointment_date'], 'appointment_time' => $data['appointment_time'], 'status' => 'new',
            ]);
            AuditLog::create([
                'actor_id' => $member->id, 'action' => 'repair.created',
                'target_type' => RepairRequest::class, 'target_id' => $repair->id,
                'new_values' => $repair->only(['user_id', 'dorm_id', 'room_id', 'reporter_name', 'reporter_type', 'room_label', 'category', 'description', 'phone', 'email', 'appointment_date', 'appointment_time', 'status']),
            ]);

            return $repair;
        }, 3);

        return redirect()->route('member.repairs.show', $repair)->with('success', 'บันทึกการแจ้งซ่อมแล้ว');
    }

    public function show(Request $request, RepairRequest $repair): View
    {
        abort_unless($repair->user_id === $request->user()->id, 403);
        Gate::authorize('view', $repair);

        return view('member.repairs.show', ['repair' => $repair->load('dorm')]);
    }
}
