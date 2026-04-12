<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComponentSupplier extends Model
{
    protected $fillable = [
        'component_id', 'supplier_id', 'supplier_part_number',
        'product_url', 'purchase_unit', 'unit_price', 'price_updated_at', 'is_preferred',
    ];

    protected $casts = ['is_preferred' => 'boolean', 'price_updated_at' => 'date'];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function priceBreaks(): HasMany
    {
        return $this->hasMany(SupplierPriceBreak::class)->orderBy('min_qty');
    }
}
