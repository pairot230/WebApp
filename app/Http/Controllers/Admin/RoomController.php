<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Room::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'floor_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $records = Room::query()->with('floor.building.dorm')
            ->withCount(['users as occupied_count' => fn ($query) => $query->where('is_active', true)]);
        if ($request->user()->role === 'admin') {
            $records->whereHas('floor.building', fn ($query) => $query->where('dorm_id', $request->user()->dorm_id));
        }
        if (! empty($filters['search'])) {
            $records->where(function ($query) use ($filters): void {
                $query->where('number', 'like', '%'.$filters['search'].'%');
            });
        }
        if (($filters['status'] ?? '') !== '') {
            $records->where('is_active', $filters['status'] === 'active');
        }
        if (! empty($filters['floor_id'])) {
            $records->where('floor_id', $filters['floor_id']);
        }

        return view('admin.rooms.index', ['records' => $records->orderBy('id')->paginate(20)->withQueryString(), 'parents' => $this->parentOptions($request)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Room::class);
        $record = new Room;

        return view('admin.rooms.form', ['record' => $record, 'parents' => $this->parentOptions($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Room::class);
        $validated = $this->validateData($request);
        $record = new Room;
        $record->floor_id = $validated['floor_id'] ?? null;
        $record->number = $validated['number'] ?? null;
        $record->capacity = $validated['capacity'] ?? null;
        $record->is_active = $validated['is_active'] ?? null;
        $record->save();

        return redirect()->route('admin.rooms.show', $record)->with('success', 'เพิ่มห้องพักเรียบร้อยแล้ว');
    }

    public function show(Room $room): View
    {
        Gate::authorize('view', $room);
        $room->load(['floor.building.dorm', 'users' => fn ($query) => $query->where('is_active', true)->orderBy('name')]);

        return view('admin.rooms.show', ['record' => $room]);
    }

    public function edit(Request $request, Room $room): View
    {
        Gate::authorize('update', $room);
        $record = $room;

        return view('admin.rooms.form', ['record' => $record, 'parents' => $this->parentOptions($request)]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('update', $room);
        $validated = $this->validateData($request, $room);
        DB::transaction(function () use ($room, $validated): void {
            $record = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $record);
            if ((int) $validated['capacity'] < User::where('room_id', $record->id)->where('is_active', true)->count()) {
                throw ValidationException::withMessages(['capacity' => 'ความจุต้องไม่น้อยกว่าจำนวนสมาชิกที่ใช้งานอยู่']);
            }
            $record->floor_id = $validated['floor_id'] ?? null;
            $record->number = $validated['number'] ?? null;
            $record->capacity = $validated['capacity'] ?? null;
            $record->is_active = $validated['is_active'] ?? null;
            $record->save();
        });

        return redirect()->route('admin.rooms.show', $room)->with('success', 'แก้ไขห้องพักเรียบร้อยแล้ว');
    }

    public function destroy(Room $room): RedirectResponse
    {
        Gate::authorize('delete', $room);
        try {
            $room->delete();
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                return back()->with('error', 'ลบไม่ได้เนื่องจากมีข้อมูลอ้างอิง กรุณาปิดใช้งานแทนหรือจัดการข้อมูลที่เกี่ยวข้องก่อน');
            }
            throw $exception;
        }

        return redirect()->route('admin.rooms.index')->with('success', 'ลบห้องพักเรียบร้อยแล้ว');
    }

    private function validateData(Request $request, ?Room $record = null): array
    {
        $validated = $request->validate([
            'floor_id' => ['required', 'integer', Rule::exists('floors', 'id')],
            'number' => ['required', 'string', 'max:255', Rule::unique('rooms', 'number')->where('floor_id', $request->input('floor_id'))->ignore($record?->id)],
            'capacity' => ['required', 'integer', Rule::in([Room::CAPACITY])],
            'is_active' => ['required', 'boolean'],
        ]);
        $parent = Floor::findOrFail($validated['floor_id']);
        Gate::authorize('update', $parent);
        if ($record && $record->floor_id !== (int) $validated['floor_id']) {
            throw ValidationException::withMessages(['floor_id' => 'ไม่สามารถย้ายโครงสร้างที่มีอยู่ไปยังตำแหน่งอื่นได้']);
        }

        return $validated;
    }

    private function parentOptions(Request $request): Collection
    {
        $parents = Floor::with('building.dorm');
        if ($request->user()->role === 'admin') {
            $parents->whereHas('building', fn ($query) => $query->where('dorm_id', $request->user()->dorm_id));
        }

        return $parents->orderBy('id')->get();
    }
}
