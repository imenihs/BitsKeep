<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'status', 'color', 'created_by',
        // 統合案件マスタ用カラム
        'business_code', 'business_name',
        'source_type', 'source_key',
        'external_code', 'external_url',
        'is_editable', 'sync_state', 'last_synced_at',
    ];

    protected $casts = [
        'is_editable'    => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * 目的: 案件のscopeeditableを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: $query。
     * 出力: 処理結果またはなし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    /**
     * 目的: 案件のfindbysourceを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: $sourceType, $sourceKey。
     * 出力: ?staticで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public static function findBySource(string $sourceType, string $sourceKey): ?static
    {
        return static::where('source_type', $sourceType)
                     ->where('source_key', $sourceKey)
                     ->first();
    }
    /**
     * 目的: ProjectからcomponentsへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_project')
                    ->withPivot('required_qty');
    }
    /**
     * 目的: ProjectからcreatorへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
