<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Floor::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'building_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $records = Floor::query()->with('building.dorm');
        if ($request->user()->role === 'admin') {
            $records->whereHas('building', fn ($query) => $query->where('dorm_id', $request->user()->dorm_id));
        }
        if (! empty($filters['search'])) {
            $records->where(function ($query) use ($filters): void {
                $query->where('number', 'like', '%'.$filters['search'].'%');
                $query->orWhere('name', 'like', '%'.$filters['search'].'%');
            });
        }
        if (! empty($filters['building_id'])) {
            $records->where('building_id', $filters['building_id']);
        }

        return view('admin.floors.index', ['records' => $records->orderBy('id')->paginate(20)->withQueryString(), 'parents' => $this->parentOptions($request)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Floor::class);
        $record = new Floor;

        return view('admin.floors.form', ['record' => $record, 'parents' => $this->parentOptions($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Floor::class);
        $validated = $this->validateData($request);
        $record = new Floor;
        $record->building_id = $validated['building_id'] ?? null;
        $record->number = $validated['number'] ?? null;
        $record->name = $validated['name'] ?? null;
        $record->save();

        return redirect()->route('admin.floors.show', $record)->with('success', 'เพิ่มชั้นเรียบร้อยแล้ว');
    }

    public function show(Floor $floor): View
    {
        Gate::authorize('view', $floor);

        return view('admin.floors.show', ['record' => $floor]);
    }

    public function edit(Request $request, Floor $floor): View
    {
        Gate::authorize('update', $floor);
        $record = $floor;

        return view('admin.floors.form', ['record' => $record, 'parents' => $this->parentOptions($request)]);
    }

    public function update(Request $request, Floor $floor): RedirectResponse
    {
        Gate::authorize('update', $floor);
        $validated = $this->validateData($request, $floor);
        DB::transaction(function () use ($floor, $validated): void {
            $record = Floor::whereKey($floor->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $record);
            $record->building_id = $validated['building_id'] ?? null;
            $record->number = $validated['number'] ?? null;
            $record->name = $validated['name'] ?? null;
            $record->save();
        });

        return redirect()->route('admin.floors.show', $floor)->with('success', 'แก้ไขชั้นเรียบร้อยแล้ว');
    }

    public function destroy(Floor $floor): RedirectResponse
    {
        Gate::authorize('delete', $floor);
        try {
            $floor->delete();
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                return back()->with('error', 'ลบไม่ได้เนื่องจากมีข้อมูลอ้างอิง กรุณาปิดใช้งานแทนหรือจัดการข้อมูลที่เกี่ยวข้องก่อน');
            }
            throw $exception;
        }

        return redirect()->route('admin.floors.index')->with('success', 'ลบชั้นเรียบร้อยแล้ว');
    }

    private function validateData(Request $request, ?Floor $record = null): array
    {
        $validated = $request->validate([
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')],
            'number' => ['required', 'integer', 'min:0', 'max:65535', Rule::unique('floors', 'number')->where('building_id', $request->input('building_id'))->ignore($record?->id)],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $parent = Building::findOrFail($validated['building_id']);
        Gate::authorize('update', $parent);
        if ($record && $record->building_id !== (int) $validated['building_id']) {
            throw ValidationException::withMessages(['building_id' => 'ไม่สามารถย้ายโครงสร้างที่มีอยู่ไปยังตำแหน่งอื่นได้']);
        }

        return $validated;
    }

    private function parentOptions(Request $request): Collection
    {
        $parents = Building::with('dorm');
        if ($request->user()->role === 'admin') {
            $parents->where('dorm_id', $request->user()->dorm_id);
        }

        return $parents->orderBy('id')->get();
    }
}
