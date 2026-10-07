<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id']]);
        $query = FinancialTransaction::where('dorm_id', $request->user()->dorm_id)->where('is_public', true)->where('status', 'posted');
        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
        $totals = FinancialTransaction::totals($query);
        $query->with('academicYear')->orderByDesc('transaction_date')->orderByDesc('id');

        return view('member.finance.index', [
            'totals' => $totals, 'years' => AcademicYear::orderByDesc('year')->get(),
            'incomes' => (clone $query)->where('type', 'income')->paginate(15, ['*'], 'income_page')->withQueryString(),
            'expenses' => (clone $query)->where('type', 'expense')->paginate(15, ['*'], 'expense_page')->withQueryString(),
        ]);
    }
}
