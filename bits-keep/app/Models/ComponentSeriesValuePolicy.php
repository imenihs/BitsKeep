<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSeriesValuePolicy extends Model
{
    protected $fillable = [
        'component_series_id',
        'value_set_type',
        'primary_series',
        'extra_series',
        'custom_values',
        'extra_values',
        'excluded_values',
        'unit',
        'decade_min',
        'decade_max',
        'range_min',
        'range_max',
        'range_step',
        'rounding_digits',
        'generation_settings',
    ];

    protected $casts = [
        'extra_series' => 'array',
        'custom_values' => 'array',
        'extra_values' => 'array',
        'excluded_values' => 'array',
        'generation_settings' => 'array',
        'decade_min' => 'integer',
        'decade_max' => 'integer',
        'range_min' => 'float',
        'range_max' => 'float',
        'range_step' => 'float',
        'rounding_digits' => 'integer',
    ];

    public function componentSeries(): BelongsTo
    {
        return $this->belongsTo(ComponentSeries::class);
    }
}
