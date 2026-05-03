<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBlock extends Model
{
    protected $fillable = [
        'component_id', 'location_id', 'stock_type', 'condition',
        'quantity', 'lot_number', 'reel_code', 'note',
    ];

    protected $casts = ['quantity' => 'integer'];
    /**
     * 目的: InventoryBlockからcomponentへのEloquentリレーションを返す。
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
     * 目的: InventoryBlockからlocationへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
    /**
     * 目的: InventoryBlockからtransactionsへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
