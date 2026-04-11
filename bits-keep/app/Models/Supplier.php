<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'url', 'color', 'lead_days', 'free_shipping_threshold', 'note',
    ];

    public function componentSuppliers(): HasMany
    {
        return $this->hasMany(ComponentSupplier::class);
    }

    public function shippingRules(): HasMany
    {
        return $this->hasMany(ShippingRule::class);
    }
}
