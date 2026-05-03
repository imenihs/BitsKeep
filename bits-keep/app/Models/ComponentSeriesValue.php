<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSeriesValue extends Model
{
    protected $fillable = [
        'component_series_id',
        'value_text',
        'value_key',
        'value_numeric',
        'unit',
        'origin',
        'source_series',
        'is_enabled',
        'is_stocked',
        'materialized_component_id',
        'sort_order',
        'note',
    ];

    protected $casts = [
        'value_numeric' => 'float',
        'is_enabled' => 'boolean',
        'is_stocked' => 'boolean',
        'sort_order' => 'integer',
    ];
    /**
     * 目的: ComponentSeriesValueからcomponent SeriesへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function componentSeries(): BelongsTo
    {
        return $this->belongsTo(ComponentSeries::class);
    }
    /**
     * 目的: ComponentSeriesValueからmaterialized ComponentへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function materializedComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'materialized_component_id');
    }
}
