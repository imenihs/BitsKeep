<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecUnit extends Model
{
    public $timestamps = false;
    protected $fillable = ['spec_type_id', 'unit', 'factor', 'sort_order'];

    public function specType(): BelongsTo
    {
        return $this->belongsTo(SpecType::class);
    }
}
