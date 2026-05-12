<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        if (!$this->sameBusiness($user, $product)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        if (!$this->sameBusiness($user, $product)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        if (!$this->sameBusiness($user, $product)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Tenant isolation: user must belong to the same business as the resource.
     */
    protected function sameBusiness(User $user, Product $product): bool
    {
        return (int) $user->business_id === (int) $product->business_id;
    }
}
