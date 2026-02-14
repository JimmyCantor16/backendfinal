<?php

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = ['business_id', 'name', 'description'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
