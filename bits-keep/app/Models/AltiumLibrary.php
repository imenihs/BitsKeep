<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AltiumLibrary extends Model
{
    protected $fillable = [
        'name', 'type', 'path', 'component_count', 'last_synced_at', 'note',
    ];

    protected $casts = ['last_synced_at' => 'datetime'];
    /**
     * 目的: AltiumLibraryからsch LinksへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function schLinks(): HasMany
    {
        return $this->hasMany(ComponentAltiumLink::class, 'sch_library_id');
    }
    /**
     * 目的: AltiumLibraryからpcb LinksへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function pcbLinks(): HasMany
    {
        return $this->hasMany(ComponentAltiumLink::class, 'pcb_library_id');
    }
}
