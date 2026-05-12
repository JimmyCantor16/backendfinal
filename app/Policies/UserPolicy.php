<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     * Only admins can list users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the target user.
     * Admins (same business) or the user viewing themselves.
     */
    public function view(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        if (!$this->sameBusiness($user, $target)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create users.
     * Only admins can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the target user.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        if (!$this->sameBusiness($user, $target)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the target user.
     * Only admins, and never themselves.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        if (!$this->sameBusiness($user, $target)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Tenant isolation: both users must belong to the same business.
     */
    protected function sameBusiness(User $user, User $target): bool
    {
        return (int) $user->business_id === (int) $target->business_id;
    }
}
