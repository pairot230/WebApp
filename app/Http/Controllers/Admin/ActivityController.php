<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Dorm;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Activity::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Activity::STATUSES)],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);
        $query = Activity::with(['dorm', 'academicYear'])->withCount('attendances');
        if ($request->user()->role !== 'superadmin') {
            $query->where('dorm_id', $request->user()->dorm_id);
        }
        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }
        foreach (['status', 'academic_year_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return view('admin.activities.index', [
            'activities' => $query->orderByDesc('starts_at')->paginate(20)->withQueryString(),
            'years' => AcademicYear::orderByDesc('year')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Activity::class);

        return $this->form($request, new Activity(['status' => 'draft', 'score' => 0]));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Activity::class);
        $data = $this->validateData($request);
        $activity = DB::transaction(function () use ($request, $data): Activity {
            $activity = Activity::create([...$data, 'created_by' => $request->user()->id]);
            $this->audit($request, $activity, 'activity.created', null);

            return $activity;
        }, 3);

        return redirect()->route('admin.activities.show', $activity)->with('success', 'สร้างกิจกรรมแล้ว');
    }

    public function show(Activity $activity): View
    {
        Gate::authorize('view', $activity);

        return view('admin.activities.show', ['activity' => $activity->load(['dorm', 'academicYear'])->loadCount('attendances')]);
    }

    public function edit(Request $request, Activity $activity): View
    {
        Gate::authorize('update', $activity);

        return $this->form($request, $activity);
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);
        $data = $this->validateData($request);
        DB::transaction(function () use ($request, $activity, $data): void {
            $locked = Activity::lockForUpdate()->findOrFail($activity->id);
            Gate::authorize('update', $locked);
            if ($locked->attendances()->exists() || $locked->scoreHistories()->exists()) {
                foreach (['dorm_id', 'academic_year_id', 'score'] as $field) {
                    if ((int) $locked->$field !== (int) $data[$field]) {
                        throw ValidationException::withMessages([$field => 'มีประวัติการเข้าร่วมแล้ว จึงเปลี่ยนหอพัก ปีการศึกษา หรือคะแนนไม่ได้']);
                    }
                }
            }
            $old = $locked->only(Activity::AUDIT_FIELDS);
            $locked->fill($data)->save();
            $this->audit($request, $locked, 'activity.updated', $old);
        }, 3);

        return redirect()->route('admin.activities.show', $activity)->with('success', 'แก้ไขกิจกรรมแล้ว');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('delete', $activity);
        DB::transaction(function () use ($request, $activity): void {
            $locked = Activity::lockForUpdate()->findOrFail($activity->id);
            Gate::authorize('delete', $locked);
            if ($locked->attendances()->exists() || $locked->scoreHistories()->exists()) {
                throw ValidationException::withMessages(['activity' => 'ลบกิจกรรมที่มีประวัติการเข้าร่วมไม่ได้ กรุณาปิดหรือยกเลิกกิจกรรม']);
            }
            $this->audit($request, $locked, 'activity.deleted', $locked->only(Activity::AUDIT_FIELDS));
            $locked->delete();
        }, 3);

        return redirect()->route('admin.activities.index')->with('success', 'ลบกิจกรรมแล้ว');
    }

    private function form(Request $request, Activity $activity): View
    {
        $dorms = Dorm::query();
        if ($request->user()->role !== 'superadmin') {
            $dorms->whereKey($request->user()->dorm_id);
        }

        return view('admin.activities.form', [
            'activity' => $activity, 'dorms' => $dorms->orderBy('name')->get(),
            'years' => AcademicYear::orderByDesc('year')->get(),
        ]);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'dorm_id' => ['required', 'integer', 'exists:dorms,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'score' => ['required', 'integer', 'min:0', 'max:10000'],
            'status' => ['required', Rule::in(Activity::STATUSES)],
            'created_by' => ['prohibited'],
        ]);
        abort_if($request->user()->role !== 'superadmin' && $request->user()->dorm_id !== (int) $data['dorm_id'], 403);
        unset($data['created_by']);
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = Carbon::createFromFormat('!Y-m-d\TH:i', $data[$field], 'Asia/Bangkok')->utc();
        }

        return $data;
    }

    private function audit(Request $request, Activity $activity, string $action, ?array $old): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => $action,
            'target_type' => Activity::class, 'target_id' => $activity->id,
            'old_values' => $old,
            'new_values' => $action === 'activity.deleted' ? null : $activity->only(Activity::AUDIT_FIELDS),
        ]);
    }
}
