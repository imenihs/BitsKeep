<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sort_order',
    ];

    public function specTypes(): BelongsToMany
    {
        return $this->belongsToMany(SpecType::class, 'spec_group_spec_type')
            ->withPivot(['sort_order', 'is_required', 'is_recommended', 'default_profile', 'default_unit', 'note'])
            ->withTimestamps()
            ->orderBy('spec_group_spec_type.sort_order')
            ->orderBy('spec_types.name');
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_spec_group', 'spec_group_id', 'component_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(SpecTemplate::class)->orderBy('sort_order')->orderBy('name');
    }
}
