<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'name_ja', 'name_en', 'symbol', 'suggest_prefixes', 'display_prefixes', 'base_unit', 'description', 'sort_order'];

    protected $casts = [
        'suggest_prefixes' => 'array',
        'display_prefixes' => 'array',
    ];

    // 単位候補
    public function units(): HasMany
    {
        return $this->hasMany(SpecUnit::class)->orderBy('sort_order');
    }

    // このスペック項目を持つ部品スペック
    public function componentSpecs(): HasMany
    {
        return $this->hasMany(ComponentSpec::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(SpecTypeAlias::class)->orderBy('sort_order');
    }

    public function specGroups(): BelongsToMany
    {
        return $this->belongsToMany(SpecGroup::class, 'spec_group_spec_type')
            ->withPivot(['sort_order', 'is_required', 'is_recommended', 'default_profile', 'default_unit', 'note'])
            ->withTimestamps();
    }

    public function templateItems(): HasMany
    {
        return $this->hasMany(SpecTemplateItem::class);
    }
}
