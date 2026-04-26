<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSpec extends Model
{
    protected $fillable = [
        'component_id',
        'spec_type_id',
        'display_name',
        'value',
        'unit',
        'value_profile',
        'value_mode',
        'value_numeric',
        'value_numeric_typ',
        'value_numeric_min',
        'value_numeric_max',
        'normalized_unit',
    ];

    protected $casts = [
        'value_numeric' => 'float',
        'value_numeric_typ' => 'float',
        'value_numeric_min' => 'float',
        'value_numeric_max' => 'float',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function specType(): BelongsTo
    {
        return $this->belongsTo(SpecType::class);
    }
}
