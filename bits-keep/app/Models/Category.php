<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'color', 'sort_order'];

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_category');
    }

    public function specGroups(): BelongsToMany
    {
        return $this->belongsToMany(SpecGroup::class, 'category_spec_group')
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderBy('category_spec_group.sort_order')
            ->orderBy('spec_groups.name');
    }
}
