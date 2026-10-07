<?php

namespace App\Policies;

use App\Models\Dorm;
use App\Models\User;

class DormPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && ($actor->role === 'superadmin' || ($actor->role === 'admin' && $actor->dorm_id !== null));
    }

    public function create(User $actor): bool
    {
        return $actor->is_active && $actor->role === 'superadmin' && ! Dorm::exists();
    }

    public function view(User $actor, Dorm $record): bool
    {
        return $actor->is_active && ($actor->role === 'superadmin' || ($actor->role === 'admin' && $actor->dorm_id !== null && $actor->dorm_id === $record->id));
    }

    public function update(User $actor, Dorm $record): bool
    {
        return $this->view($actor, $record);
    }

    public function delete(User $actor, Dorm $record): bool
    {
        return $this->update($actor, $record);
    }
}
