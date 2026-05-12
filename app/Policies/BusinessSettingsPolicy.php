<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization rules for the business settings endpoints.
 *
 * Note: BusinessSettings has no dedicated model — settings are stored on the
 * Business model itself. This policy is intentionally NOT mapped to the
 * Business model in AuthServiceProvider (the Business module is owned by a
 * separate agent). Use it explicitly from controllers, e.g.:
 *
 *     Gate::policy(BusinessSettingsPolicy::class, ...) // not used
 *     $this->authorize('view', [BusinessSettingsPolicy::class]); // not used
 *
 *     // Instead invoke directly:
 *     app(BusinessSettingsPolicy::class)->update($request->user());
 */
class BusinessSettingsPolicy
{
    /**
     * Determine whether the user can view any business settings.
     * Any authenticated user belonging to a business can see the basic
     * settings of their own business.
     */
    public function viewAny(User $user): bool
    {
        return $user->business_id !== null;
    }

    /**
     * Determine whether the user can view the business settings of a given
     * business id (passed as int for resource-agnostic check).
     */
    public function view(User $user, ?int $businessId = null): bool
    {
        if ($user->business_id === null) {
            return false;
        }

        if ($businessId !== null && (int) $user->business_id !== (int) $businessId) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create business settings.
     * Settings are created with the business itself; not used standalone.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the business settings.
     * Only admins of the same business can update settings.
     */
    public function update(User $user, ?int $businessId = null): bool
    {
        if (!$user->hasRole('admin')) {
            return false;
        }

        if ($user->business_id === null) {
            return false;
        }

        if ($businessId !== null && (int) $user->business_id !== (int) $businessId) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete business settings.
     * Deletion of settings is not a supported operation in this app —
     * always deny.
     */
    public function delete(User $user, ?int $businessId = null): bool
    {
        return false;
    }
}
