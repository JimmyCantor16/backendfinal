<?php

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'user_id',
        'opening_amount',
        'closing_amount',
        'total_cash',
        'total_card',
        'total_transfer',
        'total_qr',
        'total_sales',
        'orders_closed',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_card' => 'decimal:2',
        'total_transfer' => 'decimal:2',
        'total_qr' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
