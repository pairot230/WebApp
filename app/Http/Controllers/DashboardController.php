<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Dorm;
use App\Models\FinancialTransaction;
use App\Models\RepairRequest;
use App\Models\Room;
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id']]);
        $yearId = ! empty($filters['academic_year_id']) ? (int) $filters['academic_year_id'] : null;
        $years = AcademicYear::orderByDesc('year')->get();
        $user = $request->user();
        if ($user->role === 'user') {
            return $this->member($user, $yearId, $years);
        }
        Gate::authorize('viewAny', User::class);

        return $this->management($user, $yearId, $years);
    }

    private function management(User $user, ?int $yearId, Collection $years): View
    {
        $isSuperadmin = $user->role === 'superadmin';
        $members = User::query();
        $activities = Activity::query();
        $attendances = Attendance::query();
        $scores = ScoreHistory::query();
        $complaints = Complaint::query();
        $repairs = RepairRequest::query();
        $finance = FinancialTransaction::visibleTo($user);
        if (! $isSuperadmin) {
            $members->where('dorm_id', $user->dorm_id)->where('role', 'user');
            $activities->where('dorm_id', $user->dorm_id);
            $attendances->whereHas('activity', fn ($query) => $query->where('dorm_id', $user->dorm_id))
                ->whereHas('user', function ($query) use ($user): void {
                    $query->where(function ($visible) use ($user): void {
                        $visible->where('role', 'user')->where('dorm_id', $user->dorm_id);
                    })->orWhere('id', $user->id);
                });
            $scores->whereHas('user', fn ($query) => $query->where('dorm_id', $user->dorm_id)->where('role', 'user'));
            foreach ([$complaints, $repairs] as $query) {
                $query->where('dorm_id', $user->dorm_id)
                    ->whereHas('reporter', fn ($reporter) => $reporter->where('role', '!=', 'superadmin'));
            }
        }
        if ($yearId !== null) {
            foreach ([$activities, $scores, $finance] as $query) {
                $query->where('academic_year_id', $yearId);
            }
            $attendances->whereHas('activity', fn ($query) => $query->where('academic_year_id', $yearId));
        }
        $yearFilter = $yearId === null ? [] : ['academic_year_id' => $yearId];
        $cards = [
            ['label' => 'สมาชิก', 'value' => $members->count(), 'url' => route('admin.users.index')],
            ['label' => 'กิจกรรม', 'value' => $activities->count(), 'url' => route('admin.activities.index', $yearFilter)],
            ['label' => 'Attendance', 'value' => $attendances->count(), 'url' => route('admin.activities.index', $yearFilter)],
            ['label' => 'คะแนนรวม', 'value' => (int) $scores->sum('score'), 'url' => route('admin.users.index')],
            ['label' => 'เรื่องร้องเรียน', 'value' => $complaints->count(), 'url' => route('admin.complaints.index')],
            ['label' => 'งานแจ้งซ่อม', 'value' => $repairs->count(), 'url' => route('admin.repairs.index')],
        ];
        if ($isSuperadmin) {
            array_splice($cards, 1, 0, [['label' => 'Admin', 'value' => User::where('role', 'admin')->count(), 'url' => route('superadmin.admins.index')]]);
        } else {
            $rooms = Room::whereHas('floor.building', fn ($query) => $query->where('dorm_id', $user->dorm_id));
            $cards[] = ['label' => 'ห้องพัก', 'value' => $rooms->count(), 'url' => route('admin.rooms.index')];
        }

        return view($isSuperadmin ? 'dashboards.superadmin' : 'dashboards.admin', [
            'years' => $years, 'cards' => $cards, 'totals' => FinancialTransaction::totals($finance),
            'dorm' => $isSuperadmin ? Dorm::first() : $user->dorm,
            'openComplaints' => (clone $complaints)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'openRepairs' => (clone $repairs)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'financeUrl' => route('admin.finance.index', $yearFilter),
        ]);
    }

    private function member(User $user, ?int $yearId, Collection $years): View
    {
        $scores = $user->scoreHistories();
        $attendances = $user->attendances();
        $upcoming = Activity::where('dorm_id', $user->dorm_id)->where('status', 'open')->where('ends_at', '>=', now());
        $finance = FinancialTransaction::where('dorm_id', $user->dorm_id)->where('status', 'posted')->where('is_public', true);
        if ($yearId !== null) {
            $scores->where('academic_year_id', $yearId);
            $attendances->whereHas('activity', fn ($query) => $query->where('academic_year_id', $yearId));
            $upcoming->where('academic_year_id', $yearId);
            $finance->where('academic_year_id', $yearId);
        }

        return view('dashboards.user', [
            'years' => $years, 'score' => (int) $scores->sum('score'), 'attendanceCount' => $attendances->count(),
            'complaintCount' => $user->complaints()->count(), 'repairCount' => $user->repairRequests()->count(),
            'recentComplaints' => $user->complaints()->latest('id')->limit(3)->get(),
            'recentRepairs' => $user->repairRequests()->latest('id')->limit(3)->get(),
            'upcoming' => $upcoming->orderBy('starts_at')->limit(5)->get(),
            'totals' => FinancialTransaction::totals($finance),
            'member' => $user->load(['dorm', 'room.floor.building']),
        ]);
    }
}
