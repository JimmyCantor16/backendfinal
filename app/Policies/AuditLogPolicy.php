<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view any audit logs.
     * Only admins may inspect audit logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the audit log entry.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        if (!$this->sameBusiness($user, $auditLog)) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Audit logs are written by the system; no direct creation by users.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Audit logs are immutable.
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Audit logs are immutable; never deletable through the API.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Tenant isolation: user must belong to the same business as the log entry.
     */
    protected function sameBusiness(User $user, AuditLog $auditLog): bool
    {
        return (int) $user->business_id === (int) $auditLog->business_id;
    }
}
