<?php

namespace Tests\Concerns;

use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait CreatesAuthenticatedUser
{
    protected Business $business;
    protected User $user;

    /**
     * Crea negocio + usuario + rol admin y autentica vía Sanctum.
     */
    protected function authenticateAsAdmin(): User
    {
        return $this->authenticateWithRole('admin');
    }

    protected function authenticateWithRole(string $roleName = 'admin'): User
    {
        $this->business = Business::factory()->create();

        $this->user = User::factory()->create([
            'business_id' => $this->business->id,
            'password'    => 'secret-password',
        ]);

        $role = Role::firstOrCreate(['name' => $roleName]);
        $this->user->roles()->attach($role->id);

        Sanctum::actingAs($this->user->fresh());

        return $this->user;
    }

    /**
     * Abre una caja para el usuario autenticado (necesario para crear órdenes POS).
     */
    protected function openCashRegisterFor(User $user, float $opening = 50000): CashRegister
    {
        return CashRegister::factory()->create([
            'business_id'    => $user->business_id,
            'user_id'        => $user->id,
            'opening_amount' => $opening,
            'status'         => 'open',
        ]);
    }
}
