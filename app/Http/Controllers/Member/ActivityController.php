<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = Activity::with(['academicYear', 'dorm'])
            ->where(function ($query) use ($request): void {
                $query->where(function ($published) use ($request): void {
                    $published->where('dorm_id', $request->user()->dorm_id)->where('status', '!=', 'draft');
                })->orWhereHas('attendances', fn ($attendance) => $attendance->where('user_id', $request->user()->id));
            })
            ->withExists(['attendances as attended' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderByDesc('starts_at')->paginate(20);

        return view('member.activities', compact('activities'));
    }
}
