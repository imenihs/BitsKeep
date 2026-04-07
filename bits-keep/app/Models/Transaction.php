<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'component_id', 'inventory_block_id', 'user_id',
        'type', 'quantity', 'quantity_before', 'quantity_after',
        'project_id', 'note',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'quantity_before' => 'integer',
        'quantity_after'  => 'integer',
    ];

    public function component(): BelongsTo   { return $this->belongsTo(Component::class); }
    public function inventoryBlock(): BelongsTo { return $this->belongsTo(InventoryBlock::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function project(): BelongsTo     { return $this->belongsTo(Project::class); }
}
