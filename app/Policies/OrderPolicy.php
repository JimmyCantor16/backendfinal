<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        if (!$this->sameBusiness($user, $order)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can create orders.
     * Admins and cashiers can create POS orders.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can update the order.
     * Admins and cashiers can update (e.g. add items, close).
     */
    public function update(User $user, Order $order): bool
    {
        if (!$this->sameBusiness($user, $order)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can delete/cancel the order.
     * Only admins can delete; cashiers can't.
     */
    public function delete(User $user, Order $order): bool
    {
        if (!$this->sameBusiness($user, $order)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Tenant isolation: user must belong to the same business as the resource.
     */
    protected function sameBusiness(User $user, Order $order): bool
    {
        return (int) $user->business_id === (int) $order->business_id;
    }
}
