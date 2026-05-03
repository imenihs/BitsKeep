<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAuthProvider extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'provider_payload',
        'linked_at',
        'last_used_at',
    ];
    /**
     * 目的: User Auth Providerのcastsを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'linked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
    /**
     * 目的: UserAuthProviderからuserへのEloquentリレーションを返す。
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
