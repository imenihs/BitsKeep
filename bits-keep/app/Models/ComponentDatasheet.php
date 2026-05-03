<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentDatasheet extends Model
{
    protected $fillable = [
        'component_id',
        'file_path',
        'original_name',
        'sort_order',
        'note',
    ];
    /**
     * 目的: ComponentDatasheetからcomponentへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
