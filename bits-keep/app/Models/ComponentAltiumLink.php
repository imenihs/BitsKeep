<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentAltiumLink extends Model
{
    protected $fillable = [
        'component_id', 'sch_library_id', 'sch_symbol', 'pcb_library_id', 'pcb_footprint',
    ];
    /**
     * 目的: ComponentAltiumLinkからcomponentへのEloquentリレーションを返す。
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
     * 目的: ComponentAltiumLinkからsch LibraryへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function schLibrary(): BelongsTo
    {
        return $this->belongsTo(AltiumLibrary::class, 'sch_library_id');
    }
    /**
     * 目的: ComponentAltiumLinkからpcb LibraryへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function pcbLibrary(): BelongsTo
    {
        return $this->belongsTo(AltiumLibrary::class, 'pcb_library_id');
    }
}
