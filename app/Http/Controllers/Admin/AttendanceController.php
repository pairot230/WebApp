<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function preview(Request $request, Activity $activity): JsonResponse
    {
        Gate::authorize('checkIn', $activity);
        $data = $request->validate(['qr_token' => ['required', 'uuid']]);
        $member = User::where('qr_token', $data['qr_token'])->first();
        if (! $member || ! $member->is_active || ! $member->student_id || ! in_array($member->role, ['superadmin', 'admin', 'user'], true) || $member->dorm_id !== (int) $activity->dorm_id) {
            throw ValidationException::withMessages(['qr_token' => 'ไม่พบสมาชิกที่มีสิทธิ์เข้าร่วมกิจกรรมนี้']);
        }
        abort_if($member->role === 'superadmin' && $request->user()->role !== 'superadmin', 403);

        return response()->json(['name' => $member->name, 'student_id' => $member->student_id]);
    }

    public function index(Activity $activity): View
    {
        Gate::authorize('checkIn', $activity);
        $attendances = $activity->attendances()->with(['user', 'scoreHistory', 'checkedInBy'])->latest('checked_in_at')->paginate(20);

        return view('admin.activities.attendances', compact('activity', 'attendances'));
    }

    public function store(Request $request, Activity $activity): JsonResponse
    {
        Gate::authorize('checkIn', $activity);
        $data = $request->validate([
            'qr_token' => ['required', 'uuid'],
            'user_id' => ['prohibited'], 'score' => ['prohibited'], 'checked_in_by' => ['prohibited'],
            'activity_id' => ['prohibited'],
        ]);
        $result = DB::transaction(function () use ($request, $activity, $data): array {
            $locked = Activity::lockForUpdate()->findOrFail($activity->id);
            Gate::authorize('checkIn', $locked);
            if ($locked->status !== 'open' || now()->lt($locked->starts_at) || now()->gt($locked->ends_at)
                || ! $locked->dorm()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['activity' => 'กิจกรรมนี้ยังไม่เปิดเช็กชื่อ หรือพ้นเวลาเช็กชื่อแล้ว']);
            }
            $member = User::where('qr_token', $data['qr_token'])->lockForUpdate()->first();
            if (! $member || ! $member->is_active || ! $member->student_id || ! in_array($member->role, ['superadmin', 'admin', 'user'], true) || $member->dorm_id !== (int) $locked->dorm_id) {
                throw ValidationException::withMessages(['qr_token' => 'ไม่พบสมาชิกที่มีสิทธิ์เข้าร่วมกิจกรรมนี้']);
            }
            abort_if($member->role === 'superadmin' && $request->user()->role !== 'superadmin', 403);
            if ($locked->attendances()->where('user_id', $member->id)->exists()) {
                abort(409, 'สมาชิกคนนี้เช็กชื่อกิจกรรมนี้แล้ว');
            }
            $attendance = Attendance::create([
                'activity_id' => $locked->id, 'user_id' => $member->id,
                'checked_in_by' => $request->user()->id, 'checked_in_at' => now(),
            ]);
            $oldTotal = (int) $member->scoreHistories()->where('academic_year_id', $locked->academic_year_id)->sum('score');
            $history = ScoreHistory::create([
                'user_id' => $member->id, 'academic_year_id' => $locked->academic_year_id,
                'activity_id' => $locked->id, 'attendance_id' => $attendance->id,
                'score' => $locked->score, 'reason' => 'เข้าร่วมกิจกรรม: '.$locked->title,
                'created_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'attendance.created',
                'target_type' => Attendance::class, 'target_id' => $attendance->id,
                'old_values' => ['user_id' => $member->id, 'academic_year_id' => (int) $locked->academic_year_id, 'total_score' => $oldTotal],
                'new_values' => ['user_id' => $member->id, 'activity_id' => $locked->id, 'score_history_id' => $history->id, 'score' => $history->score, 'academic_year_id' => (int) $locked->academic_year_id, 'total_score' => $oldTotal + $history->score],
            ]);

            return ['message' => 'เช็กชื่อสำเร็จ', 'name' => $member->name, 'student_id' => $member->student_id, 'score' => $history->score];
        }, 3);

        return response()->json($result, 201);
    }
}
