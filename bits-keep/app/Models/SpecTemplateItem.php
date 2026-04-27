<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecTemplateItem extends Model
{
    protected $fillable = [
        'spec_template_id',
        'spec_type_id',
        'sort_order',
        'default_profile',
        'default_unit',
        'is_required',
        'note',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SpecTemplate::class, 'spec_template_id');
    }

    public function specType(): BelongsTo
    {
        return $this->belongsTo(SpecType::class);
    }
}
