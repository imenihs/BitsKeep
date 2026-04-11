<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentDatasheet extends Model
{
    protected $fillable = [
        'component_id',
        'file_path',
        'original_name',
        'sort_order',
        'note',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
