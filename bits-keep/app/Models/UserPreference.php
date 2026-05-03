<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    // valueはJSON自動キャスト
    protected $casts = [
        'value' => 'array',
    ];
    /**
     * 目的: UserPreferenceからuserへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
