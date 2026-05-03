<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Component extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'manufacturer', 'part_number', 'part_number_sort_key', 'common_name', 'description',
        'procurement_status',
        'quantity_new', 'quantity_used',
        'threshold_new', 'threshold_used',
        'image_path', 'datasheet_path',
        'primary_location_id',
        'package_id',
        'component_series_id',
        'component_series_value_id',
        'created_by', 'updated_by',
    ];

    protected $hidden = [
        'part_number_sort_key',
    ];

    protected $casts = [
        'quantity_new' => 'integer',
        'quantity_used' => 'integer',
        'threshold_new' => 'integer',
        'threshold_used' => 'integer',
    ];

    /**
     * 目的: 部品のモデル保存時の自動補完フックを登録する。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    protected static function booted(): void
    {
        static::saving(function (Component $component) {
            if ($component->isDirty('part_number') || blank($component->part_number_sort_key)) {
                $component->part_number_sort_key = self::buildPartNumberSortKey($component->part_number);
            }
        });
    }

    /**
     * 目的: 部品の生成part番号並び替えkeyを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: $partNumber。
     * 出力: stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: なし。
     */
    public static function buildPartNumberSortKey(?string $partNumber): string
    {
        $normalized = strtoupper(trim((string) $partNumber));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $sortKey = preg_replace_callback('/\d+/', function (array $matches) {
            $rawNumber = $matches[0];
            $number = ltrim($rawNumber, '0');
            $number = $number === '' ? '0' : $number;

            return '#'
                .str_pad(substr($number, -30), 30, '0', STR_PAD_LEFT)
                .':'
                .str_pad((string) strlen($rawNumber), 4, '0', STR_PAD_LEFT)
                .';';
        }, $normalized) ?? $normalized;

        return substr($sortKey, 0, 255);
    }

    /**
     * 目的: 部品分類（多対多）。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(SpecGroup::class, 'component_spec_group', 'component_id', 'spec_group_id')
            ->orderBy('spec_groups.sort_order')
            ->orderBy('spec_groups.name');
    }
    /**
     * 目的: ComponentからpackageへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
    /**
     * 目的: Componentからcomponent SeriesへのEloquentリレーションを返す。
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
     * 目的: Componentからcomponent Series ValueへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function componentSeriesValue(): BelongsTo
    {
        return $this->belongsTo(ComponentSeriesValue::class);
    }

    /**
     * 目的: 旧UI互換: 単一パッケージを1件コレクションとして扱う。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'id', 'package_id');
    }

    /**
     * 目的: スペック値。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function specs(): HasMany
    {
        return $this->hasMany(ComponentSpec::class);
    }

    /**
     * 目的: 自由属性。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function customAttributes(): HasMany
    {
        return $this->hasMany(ComponentAttribute::class);
    }
    /**
     * 目的: ComponentからdatasheetsへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function datasheets(): HasMany
    {
        return $this->hasMany(ComponentDatasheet::class)->orderBy('sort_order');
    }

    /**
     * 目的: 保管棚（多対多）。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'component_location');
    }
    /**
     * 目的: Componentからprimary LocationへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    /**
     * 目的: 仕入先情報。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function componentSuppliers(): HasMany
    {
        return $this->hasMany(ComponentSupplier::class);
    }

    /**
     * 目的: 商社（多対多 through component_suppliers）。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'component_suppliers')
            ->withPivot('supplier_part_number', 'product_url', 'unit_price', 'price_updated_at', 'is_preferred')
            ->withTimestamps();
    }

    /**
     * 目的: 在庫ブロック。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function inventoryBlocks(): HasMany
    {
        return $this->hasMany(InventoryBlock::class);
    }

    /**
     * 目的: 入出庫履歴。
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

    /**
     * 目的: プロジェクト（多対多）。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsToMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'component_project')
            ->withPivot('required_qty');
    }

    /**
     * 目的: Altium連携。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasOne リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function altiumLink(): HasOne
    {
        return $this->hasOne(ComponentAltiumLink::class);
    }

    /**
     * 目的: 登録者・更新者。
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
    /**
     * 目的: ComponentからupdaterへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: BelongsTo リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 目的: 部品のscopeneedsreorderを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: $query。
     * 出力: 処理結果またはなし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function scopeNeedsReorder($query)
    {
        return $query->where(function ($q) {
            $q->whereRaw('quantity_new < threshold_new')
                ->orWhereRaw('quantity_used < threshold_used');
        });
    }
}
