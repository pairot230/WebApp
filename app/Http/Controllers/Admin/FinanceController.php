<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Dorm;
use App\Models\FinancialTransaction;
use App\Models\MembershipPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', FinancialTransaction::class);
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(array_keys(FinancialTransaction::STATUS_LABELS))],
        ]);
        $query = FinancialTransaction::visibleTo($request->user());
        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
        $totals = FinancialTransaction::totals($query);
        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $query->with(['member', 'academicYear', 'creator'])->orderByDesc('transaction_date')->orderByDesc('id');

        return view('admin.finance.index', [
            'totals' => $totals, 'years' => AcademicYear::orderByDesc('year')->get(),
            'incomes' => (clone $query)->where('type', 'income')->paginate(15, ['*'], 'income_page')->withQueryString(),
            'expenses' => (clone $query)->where('type', 'expense')->paginate(15, ['*'], 'expense_page')->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', FinancialTransaction::class);
        $data = $request->validate(['type' => ['required', Rule::in(['income', 'expense'])]]);

        return $this->form($request, new FinancialTransaction(['type' => $data['type'], 'status' => 'posted', 'is_public' => false]));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', FinancialTransaction::class);
        $data = $this->validateData($request);
        $finance = DB::transaction(function () use ($request, $data): FinancialTransaction {
            Gate::authorize('create', FinancialTransaction::class);
            $dorm = Dorm::when($request->user()->role !== 'superadmin', fn ($query) => $query->whereKey($request->user()->dorm_id))->firstOrFail();
            $this->checkMember($request, $data, $dorm->id, true);
            $finance = FinancialTransaction::create([...$data, 'dorm_id' => $dorm->id, 'created_by' => $request->user()->id]);
            $this->syncMembership($request, $finance);
            $this->audit($request, $finance, 'finance.created', null);

            return $finance;
        }, 3);

        return redirect()->route('admin.finance.show', $finance)->with('success', 'บันทึกรายการการเงินแล้ว');
    }

    public function show(FinancialTransaction $finance): View
    {
        Gate::authorize('update', $finance);

        return view('admin.finance.show', ['finance' => $finance->load(['member', 'academicYear', 'creator', 'voidedBy'])]);
    }

    public function edit(Request $request, FinancialTransaction $finance): View
    {
        Gate::authorize('update', $finance);
        abort_if($finance->status === 'voided', 409, 'รายการที่ยกเลิกแล้วแก้ไขไม่ได้');

        return $this->form($request, $finance);
    }

    public function update(Request $request, FinancialTransaction $finance): RedirectResponse
    {
        Gate::authorize('update', $finance);
        $data = $this->validateData($request);
        DB::transaction(function () use ($request, $finance, $data): void {
            $locked = FinancialTransaction::lockForUpdate()->findOrFail($finance->id);
            Gate::authorize('update', $locked);
            abort_if($locked->status === 'voided', 409, 'รายการที่ยกเลิกแล้วแก้ไขไม่ได้');
            foreach (['type', 'academic_year_id', 'user_id'] as $field) {
                if ((string) $locked->$field !== (string) $data[$field]) {
                    throw ValidationException::withMessages([$field => 'เปลี่ยนประเภท สมาชิก หรือปีของรายการเดิมไม่ได้ กรุณายกเลิกแล้วสร้างใหม่']);
                }
            }
            if ($locked->type === 'income' && $locked->category !== $data['category']) {
                throw ValidationException::withMessages(['category' => 'เปลี่ยนประเภทรายรับเดิมไม่ได้ กรุณายกเลิกแล้วสร้างใหม่']);
            }
            if ($locked->status === 'posted' && $data['status'] !== 'posted') {
                throw ValidationException::withMessages(['status' => 'รายการบันทึกแล้วต้องใช้การยกเลิกพร้อมเหตุผล']);
            }
            $this->checkMember($request, $data, $locked->dorm_id, false);
            $old = $locked->only(FinancialTransaction::AUDIT_FIELDS);
            $locked->fill($data);
            if ($locked->isDirty()) {
                $locked->save();
                $this->syncMembership($request, $locked);
                $this->audit($request, $locked, 'finance.updated', $old);
            }
        }, 3);

        return redirect()->route('admin.finance.show', $finance)->with('success', 'แก้ไขรายการการเงินแล้ว');
    }

    public function void(Request $request, FinancialTransaction $finance): RedirectResponse
    {
        Gate::authorize('update', $finance);
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $finance, $data): void {
            $locked = FinancialTransaction::lockForUpdate()->findOrFail($finance->id);
            Gate::authorize('update', $locked);
            abort_if($locked->status === 'voided', 409, 'รายการนี้ยกเลิกแล้ว');
            $old = $locked->only(FinancialTransaction::AUDIT_FIELDS);
            $locked->status = 'voided';
            $locked->voided_by = $request->user()->id;
            $locked->voided_at = now();
            $locked->void_reason = $data['void_reason'];
            $locked->save();
            $this->syncMembership($request, $locked);
            $this->audit($request, $locked, 'finance.voided', $old);
        }, 3);

        return redirect()->route('admin.finance.show', $finance)->with('success', 'ยกเลิกรายการแล้ว ประวัติเดิมยังคงอยู่');
    }

    private function form(Request $request, FinancialTransaction $finance): View
    {
        $dorm = $finance->exists ? $finance->dorm : Dorm::when($request->user()->role !== 'superadmin', fn ($query) => $query->whereKey($request->user()->dorm_id))->firstOrFail();
        $members = User::where('dorm_id', $dorm->id);
        if (! $finance->exists) {
            $members->where('is_active', true);
        }
        if ($request->user()->role !== 'superadmin') {
            $members->where('role', '!=', 'superadmin');
        }

        return view('admin.finance.form', ['finance' => $finance, 'members' => $members->orderBy('name')->get(), 'years' => AcademicYear::orderByDesc('year')->get()]);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'user_id' => ['nullable', 'required_if:type,income', 'prohibited_if:type,expense', 'integer', 'exists:users,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'category' => ['required', 'string', 'max:100'], 'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/'],
            'transaction_date' => ['required', 'date_format:Y-m-d'], 'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(['pending', 'posted'])], 'is_public' => ['required', 'boolean'],
            'dorm_id' => ['prohibited'], 'created_by' => ['prohibited'], 'voided_by' => ['prohibited'],
            'voided_at' => ['prohibited'], 'void_reason' => ['prohibited'],
        ]);
        if ($data['type'] === 'income' && ! array_key_exists($data['category'], FinancialTransaction::INCOME_CATEGORIES)) {
            throw ValidationException::withMessages(['category' => 'เลือกค่าส่วนกลางหรือรายรับอื่น']);
        }
        $minor = FinancialTransaction::minorUnits((string) $data['amount']);
        if ($minor <= 0) {
            throw ValidationException::withMessages(['amount' => 'จำนวนเงินต้องมากกว่า 0']);
        }

        return [
            'type' => $data['type'], 'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'academic_year_id' => (int) $data['academic_year_id'], 'category' => $data['category'], 'title' => $data['title'],
            'amount' => FinancialTransaction::formatMinorUnits($minor), 'transaction_date' => $data['transaction_date'],
            'description' => $data['description'] ?? null, 'status' => $data['status'], 'is_public' => (bool) $data['is_public'],
        ];
    }

    private function checkMember(Request $request, array $data, int $dormId, bool $requireActive): void
    {
        if ($data['type'] !== 'income') {
            return;
        }
        $member = User::lockForUpdate()->findOrFail($data['user_id']);
        if ($requireActive && ($member->dorm_id !== $dormId || ! $member->is_active)) {
            throw ValidationException::withMessages(['user_id' => 'เลือกสมาชิกที่ใช้งานในหอของระบบ']);
        }
        abort_if($member->role === 'superadmin' && $request->user()->role !== 'superadmin', 403);
    }

    private function syncMembership(Request $request, FinancialTransaction $finance): void
    {
        if ($finance->type !== 'income' || $finance->category !== 'membership') {
            return;
        }
        $payment = MembershipPayment::where('user_id', $finance->user_id)->where('academic_year_id', $finance->academic_year_id)->lockForUpdate()->first();
        if ($payment && ($payment->status === 'paid' || $payment->financial_transaction_id !== null)
            && (int) $payment->financial_transaction_id !== $finance->id && $payment->status !== 'voided') {
            throw ValidationException::withMessages(['user_id' => 'สมาชิกมีรายการค่าส่วนกลางในปีการศึกษานี้แล้ว']);
        }
        $old = $payment?->toArray();
        $payment ??= new MembershipPayment;
        if ($payment->financial_transaction_id !== null && (int) $payment->financial_transaction_id !== $finance->id) {
            $payment->paid_at = null;
        }
        $payment->fill([
            'user_id' => $finance->user_id, 'dorm_id' => $finance->dorm_id, 'academic_year_id' => $finance->academic_year_id,
            'financial_transaction_id' => $finance->id, 'amount' => $finance->amount,
            'status' => ['posted' => 'paid', 'pending' => 'pending', 'voided' => 'voided'][$finance->status],
        ]);
        if ($finance->status === 'posted') {
            $payment->paid_at = Carbon::createFromFormat('!Y-m-d', $finance->transaction_date->format('Y-m-d'), 'Asia/Bangkok')->utc();
        }
        $payment->save();
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => 'membership.synced', 'target_type' => MembershipPayment::class,
            'target_id' => $payment->id, 'old_values' => $old, 'new_values' => $payment->toArray(),
        ]);
    }

    private function audit(Request $request, FinancialTransaction $finance, string $action, ?array $old): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => $action, 'target_type' => FinancialTransaction::class,
            'target_id' => $finance->id, 'old_values' => $old, 'new_values' => $finance->only(FinancialTransaction::AUDIT_FIELDS),
        ]);
    }
}
