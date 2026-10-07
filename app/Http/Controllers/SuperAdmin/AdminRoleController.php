<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRoleController extends Controller
{
    public function index(): View
    {
        return view('superadmin.admins.index', [
            'members' => User::whereIn('role', ['user', 'admin'])->orderBy('name')->paginate(20),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('changeRole', $user);
        $validated = $request->validate(['role' => ['required', Rule::in(['user', 'admin'])]]);
        DB::transaction(function () use ($request, $user, $validated): void {
            $member = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('changeRole', $member);
            if ($member->role === $validated['role']) {
                return;
            }
            if ($validated['role'] === 'admin') {
                abort_unless($member->is_active && $member->dorm_id !== null, 422, 'Admin ต้องมีหอสังกัดและบัญชีที่ใช้งานได้');
            }
            $oldRole = $member->role;
            $member->role = $validated['role'];
            $member->save();

            $audit = new AuditLog;
            $audit->actor_id = $request->user()->id;
            $audit->action = 'user.role_changed';
            $audit->target_type = User::class;
            $audit->target_id = $member->id;
            $audit->old_values = ['role' => $oldRole];
            $audit->new_values = ['role' => $member->role];
            $audit->save();
        });

        return redirect()->route('superadmin.admins.index')->with('success', 'บันทึกสิทธิ์เรียบร้อยแล้ว');
    }
}
