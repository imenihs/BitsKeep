<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSpec extends Model
{
    protected $fillable = ['component_id', 'spec_type_id', 'value', 'unit', 'value_numeric'];

    protected $casts = ['value_numeric' => 'float'];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function specType(): BelongsTo
    {
        return $this->belongsTo(SpecType::class);
    }
}
