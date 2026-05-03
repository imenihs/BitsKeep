<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOrder extends Model
{
    protected $fillable = [
        'component_id',
        'supplier_id',
        'quantity',
        'status',
        'order_date',
        'expected_date',
        'received_date',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
    ];
    /**
     * 目的: StockOrderからcomponentへのEloquentリレーションを返す。
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
     * 目的: StockOrderからsupplierへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
    /**
     * 目的: StockOrderからcreated ByへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    /**
     * 目的: 発注のispendingを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    /**
     * 目的: 発注のisreceivedを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function isReceived(): bool
    {
        return $this->status === 'received';
    }
    /**
     * 目的: 発注のiscancelledを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
