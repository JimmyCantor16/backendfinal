<?php

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'document_type',
        'document_number',
        'name',
        'phone',
        'email',
        'address',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
