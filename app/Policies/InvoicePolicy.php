<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (!$this->sameBusiness($user, $invoice)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can create invoices.
     * Admins and cashiers can create invoices.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('cashier');
    }

    /**
     * Determine whether the user can update the invoice (e.g. cancel).
     * Only admins can update/cancel invoices.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if (!$this->sameBusiness($user, $invoice)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the invoice.
     * Only admins can delete invoices.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        if (!$this->sameBusiness($user, $invoice)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Tenant isolation: user must belong to the same business as the resource.
     */
    protected function sameBusiness(User $user, Invoice $invoice): bool
    {
        return (int) $user->business_id === (int) $invoice->business_id;
    }
}
