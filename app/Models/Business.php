<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Business extends Model
{
    use Billable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'nit',
        'address',
        'phone',
        'email',
        'logo',
        'owner_user_id',
        'subscription_plan',
        'subscription_status',
        'plan_limits',
    ];

    protected $casts = [
        'plan_limits' => 'array',
    ];

    /**
     * Dueño/creador del negocio.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Usuarios que pertenecen a este negocio (modelo actual: pertenencia directa
     * vía users.business_id; relación 1:N tratada como "users" para compatibilidad
     * con la API solicitada — el modelo lógico es belongsToMany pero la tabla
     * concreta es hasMany).
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Settings del negocio. En esta arquitectura los settings viven en la propia
     * tabla businesses (ver BusinessSettingsController), por lo que la "relación"
     * apunta al mismo registro. Se expone como accessor para encajar con el
     * contrato `settings()` solicitado sin introducir una tabla extra.
     */
    public function settings()
    {
        return $this;
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function cashRegisters()
    {
        return $this->hasMany(CashRegister::class);
    }
}
