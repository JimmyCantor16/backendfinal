<?php

namespace App\Policies;

use App\Models\CashRegister;
use App\Models\User;

class CashRegisterPolicy
{
    /**
     * Determine whether the user can view any cash registers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can view the cash register.
     */
    public function view(User $user, CashRegister $cashRegister): bool
    {
        if (!$this->sameBusiness($user, $cashRegister)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can create (open) cash registers.
     * Cashiers can open their own cash register; admins can too.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can update (close, report) the cash register.
     */
    public function update(User $user, CashRegister $cashRegister): bool
    {
        if (!$this->sameBusiness($user, $cashRegister)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can delete the cash register.
     * Only admins can delete cash registers.
     */
    public function delete(User $user, CashRegister $cashRegister): bool
    {
        if (!$this->sameBusiness($user, $cashRegister)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Tenant isolation: user must belong to the same business as the resource.
     */
    protected function sameBusiness(User $user, CashRegister $cashRegister): bool
    {
        return (int) $user->business_id === (int) $cashRegister->business_id;
    }
}
