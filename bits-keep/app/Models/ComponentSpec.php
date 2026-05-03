<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSpec extends Model
{
    protected $fillable = [
        'component_id',
        'spec_type_id',
        'display_name',
        'value',
        'unit',
        'value_profile',
        'value_mode',
        'value_numeric',
        'value_numeric_typ',
        'value_numeric_min',
        'value_numeric_max',
        'normalized_unit',
    ];

    protected $casts = [
        'value_numeric' => 'float',
        'value_numeric_typ' => 'float',
        'value_numeric_min' => 'float',
        'value_numeric_max' => 'float',
    ];
    /**
     * 目的: ComponentSpecからcomponentへのEloquentリレーションを返す。
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
    /**
     * 目的: ComponentSpecからspec TypeへのEloquentリレーションを返す。
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
