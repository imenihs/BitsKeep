<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecUnit extends Model
{
    public $timestamps = false;
    protected $fillable = ['spec_type_id', 'unit', 'factor', 'sort_order'];
    /**
     * 目的: SpecUnitからspec TypeへのEloquentリレーションを返す。
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
