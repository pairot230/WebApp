<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScoreHistoryController extends Controller
{
    public function index(Request $request, User $user): View
    {
        Gate::authorize('view', $user);
        $filters = $request->validate(['academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id']]);
        $query = $user->scoreHistories()->with(['academicYear', 'activity', 'creator']);
        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        return view('admin.scores.index', [
            'member' => $user, 'total' => (clone $query)->sum('score'),
            'histories' => $query->latest('id')->paginate(20)->withQueryString(),
            'years' => AcademicYear::orderByDesc('year')->get(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'score' => ['required', 'integer', 'between:-10000,10000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:2000'],
            'user_id' => ['prohibited'], 'created_by' => ['prohibited'],
            'activity_id' => ['prohibited'], 'attendance_id' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $user, $data): void {
            $member = User::lockForUpdate()->findOrFail($user->id);
            Gate::authorize('update', $member);
            $oldTotal = (int) $member->scoreHistories()->where('academic_year_id', $data['academic_year_id'])->sum('score');
            $history = ScoreHistory::create([
                'user_id' => $member->id, 'academic_year_id' => $data['academic_year_id'],
                'score' => $data['score'], 'reason' => $data['reason'], 'created_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'score.adjusted',
                'target_type' => ScoreHistory::class, 'target_id' => $history->id,
                'old_values' => ['user_id' => $member->id, 'academic_year_id' => (int) $data['academic_year_id'], 'total_score' => $oldTotal],
                'new_values' => [...$history->only(['user_id', 'academic_year_id', 'score', 'reason', 'created_by']), 'total_score' => $oldTotal + $history->score],
            ]);
        }, 3);

        return redirect()->route('admin.users.scores.index', $user)->with('success', 'บันทึกการปรับคะแนนแล้ว');
    }
}
