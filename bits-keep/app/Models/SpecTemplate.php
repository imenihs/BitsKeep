<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'spec_group_id',
        'name',
        'description',
        'sort_order',
    ];

    public function specGroup(): BelongsTo
    {
        return $this->belongsTo(SpecGroup::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SpecTemplateItem::class)->orderBy('sort_order');
    }
}
