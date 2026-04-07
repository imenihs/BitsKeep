<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AltiumLibrary extends Model
{
    protected $fillable = [
        'name', 'type', 'path', 'component_count', 'last_synced_at', 'note',
    ];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function schLinks(): HasMany
    {
        return $this->hasMany(ComponentAltiumLink::class, 'sch_library_id');
    }

    public function pcbLinks(): HasMany
    {
        return $this->hasMany(ComponentAltiumLink::class, 'pcb_library_id');
    }
}
