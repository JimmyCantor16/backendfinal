<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price_cents',
        'currency',
        'interval',
        'stripe_price_id',
        'features',
        'active',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'features'    => 'array',
        'active'      => 'boolean',
    ];

    /**
     * Precio formateado en unidades (no centavos).
     */
    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    /**
     * Scope: solo planes activos.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
