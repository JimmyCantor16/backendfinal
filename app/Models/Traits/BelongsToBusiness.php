<?php

namespace App\Models\Traits;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        // Global scope: filtra automáticamente por business_id del usuario autenticado
        static::addGlobalScope('business', function (Builder $builder) {
            if (auth()->check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.business_id", auth()->user()->business_id);
            }
        });

        // Auto-set business_id al crear registros
        static::creating(function ($model) {
            if (auth()->check() && empty($model->business_id)) {
                $model->business_id = auth()->user()->business_id;
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
