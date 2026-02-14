<?php

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
