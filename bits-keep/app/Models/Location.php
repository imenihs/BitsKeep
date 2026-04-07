<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = ['code', 'name', 'group', 'parent_id', 'qr_code', 'description', 'sort_order'];

    public function parent(): BelongsTo   { return $this->belongsTo(Location::class, 'parent_id'); }
    public function children(): HasMany  { return $this->hasMany(Location::class, 'parent_id'); }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_location');
    }

    public function inventoryBlocks(): HasMany
    {
        return $this->hasMany(InventoryBlock::class);
    }
}
