<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecTypeAlias extends Model
{
    protected $fillable = ['spec_type_id', 'alias', 'locale', 'kind', 'sort_order'];

    public function specType(): BelongsTo
    {
        return $this->belongsTo(SpecType::class);
    }
}
