<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecType extends Model
{
    use SoftDeletes;

    public const SCOPE_COMMON = 'common';

    public const SCOPE_GROUP_LOCAL = 'group_local';

    public const KIND_NORMAL = 'normal';

    public const KIND_TOLERANCE = 'tolerance';

    protected $fillable = [
        'name',
        'name_ja',
        'name_en',
        'symbol',
        'suggest_prefixes',
        'display_prefixes',
        'spec_scope',
        'owner_spec_group_id',
        'spec_kind',
        'tolerance_settings',
        'base_unit',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'suggest_prefixes' => 'array',
        'display_prefixes' => 'array',
        'tolerance_settings' => 'array',
    ];

    // 単位候補
    public function units(): HasMany
    {
        return $this->hasMany(SpecUnit::class)->orderBy('sort_order');
    }

    // このスペック詳細を持つ部品スペック
    public function componentSpecs(): HasMany
    {
        return $this->hasMany(ComponentSpec::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(SpecTypeAlias::class)->orderBy('sort_order');
    }

    public function ownerSpecGroup(): BelongsTo
    {
        return $this->belongsTo(SpecGroup::class, 'owner_spec_group_id');
    }

    public function specGroups(): BelongsToMany
    {
        return $this->belongsToMany(SpecGroup::class, 'spec_group_spec_type')
            ->withPivot(['sort_order', 'is_required', 'is_recommended', 'default_profile', 'default_unit', 'note'])
            ->withTimestamps()
            ->orderBy('spec_groups.sort_order')
            ->orderBy('spec_groups.name');
    }

    public function templateItems(): HasMany
    {
        return $this->hasMany(SpecTemplateItem::class);
    }
}
