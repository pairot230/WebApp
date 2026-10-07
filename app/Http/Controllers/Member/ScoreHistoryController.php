<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScoreHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id']]);
        $query = $request->user()->scoreHistories()->with(['academicYear', 'activity']);
        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        return view('member.scores', [
            'total' => (clone $query)->sum('score'),
            'histories' => $query->latest('id')->paginate(20)->withQueryString(),
            'years' => AcademicYear::orderByDesc('year')->get(),
        ]);
    }
}
