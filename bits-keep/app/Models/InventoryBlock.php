<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBlock extends Model
{
    protected $fillable = [
        'component_id', 'location_id', 'stock_type', 'condition',
        'quantity', 'lot_number', 'reel_code', 'note',
    ];

    protected $casts = ['quantity' => 'integer'];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
