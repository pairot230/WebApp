<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dorm;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['user', 'admin', 'superadmin'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'dorm_id' => ['nullable', 'integer', 'min:1'],
            'room_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $members = User::with(['dorm', 'room.floor.building']);
        if ($request->user()->role === 'admin') {
            $members->where('role', 'user')->where('dorm_id', $request->user()->dorm_id);
        }

        if (! empty($filters['search'])) {
            $members->where(function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('student_id', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%')
                    ->orWhere('phone', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('room', fn ($rooms) => $rooms->where('number', 'like', '%'.$filters['search'].'%'));
            });
        }
        foreach (['role', 'dorm_id', 'room_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $members->where($filter, $filters[$filter]);
            }
        }
        if (($filters['status'] ?? '') !== '') {
            $members->where('is_active', $filters['status'] === 'active');
        }

        return view('admin.users.index', array_merge($this->formOptions($request), [
            'members' => $members->orderBy('name')->paginate(20)->withQueryString(),
        ]));
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        $user->load(['dorm', 'room.floor.building']);

        return view('admin.users.show', ['member' => $user]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', array_merge($this->formOptions($request), ['member' => new User]));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $validated = $this->validateData($request);
        $member = DB::transaction(function () use ($request, $validated): User {
            $this->checkRoom($validated);
            $member = new User;
            foreach ($validated as $field => $value) {
                $member->{$field} = $value;
            }
            $member->role = 'user';
            $member->qr_token = (string) Str::uuid();
            $member->save();
            $this->audit($request, $member, 'user.created', null);

            return $member;
        });

        return redirect()->route('admin.users.show', $member)->with('success', 'เพิ่มสมาชิกเรียบร้อยแล้ว');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', array_merge($this->formOptions($request), ['member' => $user]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $validated = $this->validateData($request, $user);
        DB::transaction(function () use ($request, $user, $validated): void {
            $member = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $member);
            if ($member->google_id !== null && $validated['email'] !== $member->email) {
                throw ValidationException::withMessages(['email' => 'บัญชีที่ผูก Google แล้วไม่สามารถเปลี่ยน email ผ่านฟอร์มนี้ได้']);
            }
            if ($request->user()->id === $member->id && ! $validated['is_active']) {
                throw ValidationException::withMessages(['is_active' => 'ไม่สามารถระงับบัญชีของตนเองได้']);
            }
            $this->checkRoom($validated, $member);
            $oldValues = $member->only($this->auditFields());
            foreach ($validated as $field => $value) {
                $member->{$field} = $value;
            }
            if ($member->isDirty()) {
                $member->save();
                $this->audit($request, $member, 'user.updated', $oldValues);
            }
        });

        return redirect()->route('admin.users.show', $user)->with('success', 'แก้ไขสมาชิกเรียบร้อยแล้ว');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        try {
            DB::transaction(function () use ($request, $user): void {
                $member = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('delete', $member);
                $this->audit($request, $member, 'user.deleted', $member->only($this->auditFields()));
                $member->delete();
            });
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                return back()->with('error', 'ลบไม่ได้เนื่องจากมีประวัติอ้างอิง กรุณาระงับการใช้งานแทน');
            }
            throw $exception;
        }

        return redirect()->route('admin.users.index')->with('success', 'ลบสมาชิกเรียบร้อยแล้ว');
    }

    private function validateData(Request $request, ?User $member = null): array
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $role = $member?->role ?? 'user';
        $validated = $request->validate([
            'student_id' => [$role === 'user' ? 'required' : 'nullable', 'string', 'max:255', Rule::unique('users')->ignore($member?->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($member?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
            'dorm_id' => [$role === 'superadmin' ? 'nullable' : 'required', 'integer', Rule::exists('dorms', 'id')],
            'room_id' => [$role === 'user' ? 'required' : 'nullable', 'integer', Rule::exists('rooms', 'id')],
            'is_active' => ['required', 'boolean'],
            'role' => ['prohibited'],
            'google_id' => ['prohibited'],
            'qr_token' => ['prohibited'],
            'password' => ['prohibited'],
        ]);
        if ($request->user()->role === 'admin') {
            abort_unless((int) $validated['dorm_id'] === $request->user()->dorm_id, 403);
        }
        unset($validated['role'], $validated['google_id'], $validated['qr_token'], $validated['password']);
        $validated['is_active'] = (bool) $validated['is_active'];
        $validated['dorm_id'] = isset($validated['dorm_id']) ? (int) $validated['dorm_id'] : null;
        $validated['room_id'] = isset($validated['room_id']) ? (int) $validated['room_id'] : null;
        $validated['phone'] ??= null;
        $validated['student_id'] ??= null;

        return $validated;
    }

    private function checkRoom(array $validated, ?User $member = null): void
    {
        if (empty($validated['room_id'])) {
            return;
        }
        $room = Room::with('floor.building.dorm')->whereKey($validated['room_id'])->lockForUpdate()->firstOrFail();
        if ($room->floor->building->dorm_id !== (int) $validated['dorm_id']) {
            throw ValidationException::withMessages(['room_id' => 'ห้องพักต้องอยู่ในหอที่เลือก']);
        }
        if ($validated['is_active']) {
            if (! $room->is_active || ! $room->floor->building->is_active || ! $room->floor->building->dorm->is_active) {
                throw ValidationException::withMessages(['room_id' => 'ไม่สามารถใช้ห้องพักหรือหอที่ปิดใช้งาน']);
            }
            $occupants = User::where('room_id', $room->id)->where('is_active', true);
            if ($member) {
                $occupants->where('id', '!=', $member->id);
            }
            if ($occupants->count() >= Room::CAPACITY) {
                throw ValidationException::withMessages(['room_id' => 'ห้องพักเต็มแล้ว']);
            }
        }
    }

    private function formOptions(Request $request): array
    {
        $dorms = Dorm::query();
        $rooms = Room::with('floor.building.dorm');
        if ($request->user()->role === 'admin') {
            $dorms->whereKey($request->user()->dorm_id);
            $rooms->whereHas('floor.building', fn ($query) => $query->where('dorm_id', $request->user()->dorm_id));
        }

        return ['dorms' => $dorms->orderBy('name')->get(), 'rooms' => $rooms->orderBy('number')->get()];
    }

    private function auditFields(): array
    {
        return ['student_id', 'name', 'email', 'phone', 'dorm_id', 'room_id', 'role', 'is_active'];
    }

    private function audit(Request $request, User $member, string $action, ?array $oldValues): void
    {
        $audit = new AuditLog;
        $audit->actor_id = $request->user()->id;
        $audit->action = $action;
        $audit->target_type = User::class;
        $audit->target_id = $member->id;
        $audit->old_values = $oldValues;
        $audit->new_values = $action === 'user.deleted' ? null : $member->only($this->auditFields());
        $audit->save();
    }
}
