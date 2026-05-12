<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    /**
     * Determine whether the user can view any purchase orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the purchase order.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (!$this->sameBusiness($user, $purchaseOrder)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create purchase orders.
     * Only admins manage purchase orders.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the purchase order.
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (!$this->sameBusiness($user, $purchaseOrder)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete/cancel the purchase order.
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (!$this->sameBusiness($user, $purchaseOrder)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Tenant isolation: user must belong to the same business as the resource.
     */
    protected function sameBusiness(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return (int) $user->business_id === (int) $purchaseOrder->business_id;
    }
}
