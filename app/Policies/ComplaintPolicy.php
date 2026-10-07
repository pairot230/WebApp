<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->role === 'superadmin'
            || ($user->role === 'admin' && $user->dorm_id !== null));
    }

    public function create(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'user'], true)
            && $user->dorm_id !== null && $user->dorm()->where('is_active', true)->exists();
    }

    public function view(User $user, Complaint $complaint): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'user'], true)
            && ($user->id === $complaint->user_id || $this->update($user, $complaint));
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $this->viewAny($user) && ($user->role === 'superadmin'
            || ($user->dorm_id === $complaint->dorm_id && $complaint->reporter?->role !== 'superadmin'));
    }
}
