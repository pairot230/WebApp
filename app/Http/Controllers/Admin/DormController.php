<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dorm;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DormController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Dorm::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $records = Dorm::query();
        if ($request->user()->role === 'admin') {
            $records->whereKey($request->user()->dorm_id);
        }
        if (! empty($filters['search'])) {
            $records->where(function ($query) use ($filters): void {
                $query->where('code', 'like', '%'.$filters['search'].'%');
                $query->orWhere('name', 'like', '%'.$filters['search'].'%');
            });
        }
        if (($filters['status'] ?? '') !== '') {
            $records->where('is_active', $filters['status'] === 'active');
        }

        return view('admin.dorms.index', ['records' => $records->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Dorm::class);
        $record = new Dorm;

        return view('admin.dorms.form', ['record' => $record]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Dorm::class);
        $validated = $this->validateData($request);
        $record = DB::transaction(function () use ($validated): Dorm {
            abort_if(Dorm::lockForUpdate()->first() !== null, 403, 'ระบบนี้ใช้สำหรับหอพักเดียว');
            Gate::authorize('create', Dorm::class);
            $record = new Dorm;
            $record->code = $validated['code'];
            $record->name = $validated['name'];
            $record->description = $validated['description'] ?? null;
            $record->is_active = $validated['is_active'];
            $record->save();

            return $record;
        }, 3);

        return redirect()->route('admin.dorms.show', $record)->with('success', 'เพิ่มหอพักเรียบร้อยแล้ว');
    }

    public function show(Dorm $dorm): View
    {
        Gate::authorize('view', $dorm);

        return view('admin.dorms.show', ['record' => $dorm]);
    }

    public function edit(Request $request, Dorm $dorm): View
    {
        Gate::authorize('update', $dorm);
        $record = $dorm;

        return view('admin.dorms.form', ['record' => $record]);
    }

    public function update(Request $request, Dorm $dorm): RedirectResponse
    {
        Gate::authorize('update', $dorm);
        $validated = $this->validateData($request, $dorm);
        DB::transaction(function () use ($dorm, $validated): void {
            $record = Dorm::whereKey($dorm->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $record);
            $record->code = $validated['code'] ?? null;
            $record->name = $validated['name'] ?? null;
            $record->description = $validated['description'] ?? null;
            $record->is_active = $validated['is_active'] ?? null;
            $record->save();
        });

        return redirect()->route('admin.dorms.show', $dorm)->with('success', 'แก้ไขหอพักเรียบร้อยแล้ว');
    }

    public function destroy(Dorm $dorm): RedirectResponse
    {
        Gate::authorize('delete', $dorm);
        try {
            $dorm->delete();
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                return back()->with('error', 'ลบไม่ได้เนื่องจากมีข้อมูลอ้างอิง กรุณาปิดใช้งานแทนหรือจัดการข้อมูลที่เกี่ยวข้องก่อน');
            }
            throw $exception;
        }

        return redirect()->route('admin.dorms.index')->with('success', 'ลบหอพักเรียบร้อยแล้ว');
    }

    private function validateData(Request $request, ?Dorm $record = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('dorms', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ]);

        return $validated;
    }
}
