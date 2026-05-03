<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecTemplateItem extends Model
{
    protected $fillable = [
        'spec_template_id',
        'spec_type_id',
        'sort_order',
        'default_profile',
        'default_unit',
        'is_required',
        'note',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];
    /**
     * 目的: SpecTemplateItemからtemplateへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SpecTemplate::class, 'spec_template_id');
    }
    /**
     * 目的: SpecTemplateItemからspec TypeへのEloquentリレーションを返す。
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
