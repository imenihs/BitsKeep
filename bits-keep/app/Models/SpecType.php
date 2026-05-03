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

    /**
     * 目的: 単位候補。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function units(): HasMany
    {
        return $this->hasMany(SpecUnit::class)->orderBy('sort_order');
    }

    /**
     * 目的: このスペック詳細を持つ部品スペック。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function componentSpecs(): HasMany
    {
        return $this->hasMany(ComponentSpec::class);
    }
    /**
     * 目的: SpecTypeからaliasesへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(SpecTypeAlias::class)->orderBy('sort_order');
    }
    /**
     * 目的: SpecTypeからowner Spec GroupへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function ownerSpecGroup(): BelongsTo
    {
        return $this->belongsTo(SpecGroup::class, 'owner_spec_group_id');
    }
    /**
     * 目的: SpecTypeからspec GroupsへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function specGroups(): BelongsToMany
    {
        return $this->belongsToMany(SpecGroup::class, 'spec_group_spec_type')
            ->withPivot(['sort_order', 'is_required', 'is_recommended', 'default_profile', 'default_unit', 'note'])
            ->withTimestamps()
            ->orderBy('spec_groups.sort_order')
            ->orderBy('spec_groups.name');
    }
    /**
     * 目的: SpecTypeからtemplate ItemsへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function templateItems(): HasMany
    {
        return $this->hasMany(SpecTemplateItem::class);
    }
}
