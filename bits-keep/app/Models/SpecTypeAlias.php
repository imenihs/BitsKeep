<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecTypeAlias extends Model
{
    protected $fillable = ['spec_type_id', 'alias', 'locale', 'kind', 'sort_order'];
    /**
     * 目的: SpecTypeAliasからspec TypeへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function specType(): BelongsTo
    {
        return $this->belongsTo(SpecType::class);
    }
}
