<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSeriesValue extends Model
{
    protected $fillable = [
        'component_series_id',
        'value_text',
        'value_key',
        'value_numeric',
        'unit',
        'origin',
        'source_series',
        'is_enabled',
        'is_stocked',
        'materialized_component_id',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'value_numeric' => 'float',
        'is_enabled' => 'boolean',
        'is_stocked' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function componentSeries(): BelongsTo
    {
        return $this->belongsTo(ComponentSeries::class);
    }

    public function materializedComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'materialized_component_id');
    }
}
