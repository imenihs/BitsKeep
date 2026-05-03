<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;     // created_atのみ手動セット

    protected $fillable = [
        'user_id', 'action', 'resource_type', 'resource_id',
        'diff', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = ['diff' => 'array', 'created_at' => 'datetime'];
    /**
     * 目的: AuditLogからuserへのEloquentリレーションを返す。
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
