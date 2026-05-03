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
        'series_management_mode',
    ];
    /**
     * 目的: SpecGroupからspec TypesへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function specTypes(): BelongsToMany
    {
        return $this->belongsToMany(SpecType::class, 'spec_group_spec_type')
            ->withPivot(['sort_order', 'is_required', 'is_recommended', 'default_profile', 'default_unit', 'note'])
            ->withTimestamps()
            ->orderBy('spec_group_spec_type.sort_order')
            ->orderBy('spec_types.name');
    }
    /**
     * 目的: SpecGroupからowned Spec TypesへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function ownedSpecTypes(): HasMany
    {
        return $this->hasMany(SpecType::class, 'owner_spec_group_id')
            ->where('spec_scope', SpecType::SCOPE_GROUP_LOCAL)
            ->where('spec_kind', SpecType::KIND_NORMAL)
            ->orderBy('sort_order')
            ->orderBy('name');
    }
    /**
     * 目的: SpecGroupからcomponentsへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_spec_group', 'spec_group_id', 'component_id');
    }
    /**
     * 目的: SpecGroupからtemplatesへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function templates(): HasMany
    {
        return $this->hasMany(SpecTemplate::class)->orderBy('sort_order')->orderBy('name');
    }
    /**
     * 目的: SpecGroupからcomponent SeriesへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function componentSeries(): HasMany
    {
        return $this->hasMany(ComponentSeries::class);
    }
}
