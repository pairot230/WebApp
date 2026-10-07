<?php

namespace App\Policies;

use App\Models\Dorm;
use App\Models\FinancialTransaction;
use App\Models\User;

class FinancialTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->role === 'superadmin' || ($user->role === 'admin' && $user->dorm_id !== null));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && Dorm::where('is_active', true)
            ->when($user->role !== 'superadmin', fn ($query) => $query->whereKey($user->dorm_id))->exists();
    }

    public function view(User $user, FinancialTransaction $finance): bool
    {
        if ($user->role === 'user') {
            return $user->is_active && $user->dorm_id === $finance->dorm_id && $finance->is_public && $finance->status === 'posted';
        }

        return $this->update($user, $finance);
    }

    public function update(User $user, FinancialTransaction $finance): bool
    {
        return $this->viewAny($user) && ($user->role === 'superadmin'
            || ($user->dorm_id === $finance->dorm_id && $finance->member?->role !== 'superadmin'));
    }
}
