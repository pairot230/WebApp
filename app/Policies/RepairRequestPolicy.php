<?php

namespace App\Policies;

use App\Models\RepairRequest;
use App\Models\User;

class RepairRequestPolicy
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

    public function view(User $user, RepairRequest $repairRequest): bool
    {
        return $user->is_active && in_array($user->role, ['superadmin', 'admin', 'user'], true)
            && ($user->id === $repairRequest->user_id || $this->update($user, $repairRequest));
    }

    public function update(User $user, RepairRequest $repairRequest): bool
    {
        return $this->viewAny($user) && ($user->role === 'superadmin'
            || ($user->dorm_id === $repairRequest->dorm_id && $repairRequest->reporter?->role !== 'superadmin'));
    }
}
