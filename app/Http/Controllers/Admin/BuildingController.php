<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Dorm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BuildingController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Building::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'dorm_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $records = Building::query()->with('dorm');
        if ($request->user()->role === 'admin') {
            $records->where('dorm_id', $request->user()->dorm_id);
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
        if (! empty($filters['dorm_id'])) {
            $records->where('dorm_id', $filters['dorm_id']);
        }

        return view('admin.buildings.index', ['records' => $records->orderBy('id')->paginate(20)->withQueryString(), 'parents' => $this->parentOptions($request)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Building::class);
        $record = new Building;

        return view('admin.buildings.form', ['record' => $record, 'parents' => $this->parentOptions($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Building::class);
        $validated = $this->validateData($request);
        $record = new Building;
        $record->dorm_id = $validated['dorm_id'] ?? null;
        $record->code = $validated['code'] ?? null;
        $record->name = $validated['name'] ?? null;
        $record->is_active = $validated['is_active'] ?? null;
        $record->save();

        return redirect()->route('admin.buildings.show', $record)->with('success', 'เพิ่มอาคารเรียบร้อยแล้ว');
    }

    public function show(Building $building): View
    {
        Gate::authorize('view', $building);

        return view('admin.buildings.show', ['record' => $building]);
    }

    public function edit(Request $request, Building $building): View
    {
        Gate::authorize('update', $building);
        $record = $building;

        return view('admin.buildings.form', ['record' => $record, 'parents' => $this->parentOptions($request)]);
    }

    public function update(Request $request, Building $building): RedirectResponse
    {
        Gate::authorize('update', $building);
        $validated = $this->validateData($request, $building);
        DB::transaction(function () use ($building, $validated): void {
            $record = Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $record);
            $record->dorm_id = $validated['dorm_id'] ?? null;
            $record->code = $validated['code'] ?? null;
            $record->name = $validated['name'] ?? null;
            $record->is_active = $validated['is_active'] ?? null;
            $record->save();
        });

        return redirect()->route('admin.buildings.show', $building)->with('success', 'แก้ไขอาคารเรียบร้อยแล้ว');
    }

    public function destroy(Building $building): RedirectResponse
    {
        Gate::authorize('delete', $building);
        try {
            $building->delete();
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                return back()->with('error', 'ลบไม่ได้เนื่องจากมีข้อมูลอ้างอิง กรุณาปิดใช้งานแทนหรือจัดการข้อมูลที่เกี่ยวข้องก่อน');
            }
            throw $exception;
        }

        return redirect()->route('admin.buildings.index')->with('success', 'ลบอาคารเรียบร้อยแล้ว');
    }

    private function validateData(Request $request, ?Building $record = null): array
    {
        $validated = $request->validate([
            'dorm_id' => ['required', 'integer', Rule::exists('dorms', 'id')],
            'code' => ['required', 'string', 'max:255', Rule::unique('buildings', 'code')->where('dorm_id', $request->input('dorm_id'))->ignore($record?->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
        $parent = Dorm::findOrFail($validated['dorm_id']);
        Gate::authorize('update', $parent);
        if ($record && $record->dorm_id !== (int) $validated['dorm_id']) {
            throw ValidationException::withMessages(['dorm_id' => 'ไม่สามารถย้ายโครงสร้างที่มีอยู่ไปยังตำแหน่งอื่นได้']);
        }

        return $validated;
    }

    private function parentOptions(Request $request): Collection
    {
        $parents = Dorm::query();
        if ($request->user()->role === 'admin') {
            $parents->whereKey($request->user()->dorm_id);
        }

        return $parents->orderBy('id')->get();
    }
}
