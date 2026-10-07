<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && ($actor->role === 'superadmin'
            || ($actor->role === 'admin' && $actor->dorm_id !== null));
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, User $member): bool
    {
        if (! $actor->is_active) {
            return false;
        }
        if ($actor->role === 'superadmin') {
            return true;
        }
        if (! in_array($actor->role, ['admin', 'user'], true)) {
            return false;
        }

        return $actor->id === $member->id || $this->canManageMember($actor, $member);
    }

    public function update(User $actor, User $member): bool
    {
        return $actor->is_active && ($actor->role === 'superadmin' || $this->canManageMember($actor, $member));
    }

    public function delete(User $actor, User $member): bool
    {
        return $actor->id !== $member->id && $this->update($actor, $member);
    }

    public function changeRole(User $actor, User $member): bool
    {
        return $actor->is_active && $actor->role === 'superadmin'
            && $actor->id !== $member->id && in_array($member->role, ['user', 'admin'], true);
    }

    private function canManageMember(User $actor, User $member): bool
    {
        return $actor->role === 'admin' && $actor->dorm_id !== null
            && $member->role === 'user' && $actor->dorm_id === $member->dorm_id;
    }
}
