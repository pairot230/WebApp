<?php

namespace App\Policies;

use App\Models\Building;
use App\Models\User;

class BuildingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && ($actor->role === 'superadmin' || ($actor->role === 'admin' && $actor->dorm_id !== null));
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, Building $record): bool
    {
        return $actor->is_active && ($actor->role === 'superadmin' || ($actor->role === 'admin' && $actor->dorm_id !== null && $actor->dorm_id === $record->dorm_id));
    }

    public function update(User $actor, Building $record): bool
    {
        return $this->view($actor, $record);
    }

    public function delete(User $actor, Building $record): bool
    {
        return $this->update($actor, $record);
    }
}
