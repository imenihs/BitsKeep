<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentAltiumLink extends Model
{
    protected $fillable = [
        'component_id', 'sch_library_id', 'sch_symbol', 'pcb_library_id', 'pcb_footprint',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function schLibrary(): BelongsTo
    {
        return $this->belongsTo(AltiumLibrary::class, 'sch_library_id');
    }

    public function pcbLibrary(): BelongsTo
    {
        return $this->belongsTo(AltiumLibrary::class, 'pcb_library_id');
    }
}
