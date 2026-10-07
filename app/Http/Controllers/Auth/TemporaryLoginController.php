<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TemporaryLoginController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local') && config('dorm.temporary_login'), 404);
        $validated = $request->validate([
            'email' => ['required', 'string', Rule::in(array_column(config('dorm.temporary_members'), 'email'))],
            'role' => ['prohibited'],
        ]);
        $user = User::where('email', $validated['email'])->where('is_active', true)->first();
        abort_unless($user && in_array($user->role, ['superadmin', 'admin', 'user'], true), 403);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
