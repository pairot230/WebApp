<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->role === 'superadmin'
            || ($user->role === 'admin' && $user->dorm_id !== null));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user) && ($user->role === 'superadmin' || $user->dorm_id === (int) $activity->dorm_id);
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->view($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->view($user, $activity);
    }

    public function checkIn(User $user, Activity $activity): bool
    {
        return $this->view($user, $activity);
    }
}
