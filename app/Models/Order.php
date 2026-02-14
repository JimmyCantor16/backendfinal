<?php

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'user_id',
        'order_number',
        'status',
        'total',
        'payment_method',
        'cash_register_id',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function recalculateTotal(): void
    {
        $this->update([
            'total' => $this->items()->sum('subtotal'),
        ]);
    }
}
