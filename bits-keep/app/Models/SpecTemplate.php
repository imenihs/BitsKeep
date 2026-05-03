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
    /**
     * 目的: SpecTemplateからspec GroupへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function specGroup(): BelongsTo
    {
        return $this->belongsTo(SpecGroup::class);
    }
    /**
     * 目的: SpecTemplateからitemsへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function items(): HasMany
    {
        return $this->hasMany(SpecTemplateItem::class)->orderBy('sort_order');
    }
}
