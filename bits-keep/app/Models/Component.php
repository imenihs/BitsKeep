<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Component extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'manufacturer', 'part_number', 'common_name', 'description',
        'procurement_status',
        'quantity_new', 'quantity_used',
        'threshold_new', 'threshold_used',
        'image_path', 'datasheet_path',
        'primary_location_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity_new'   => 'integer',
        'quantity_used'  => 'integer',
        'threshold_new'  => 'integer',
        'threshold_used' => 'integer',
    ];

    // 分類（多対多）
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'component_category');
    }

    // パッケージ（多対多）
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'component_package');
    }

    // スペック値
    public function specs(): HasMany
    {
        return $this->hasMany(ComponentSpec::class);
    }

    // 自由属性
    public function attributes(): HasMany
    {
        return $this->hasMany(ComponentAttribute::class);
    }

    public function datasheets(): HasMany
    {
        return $this->hasMany(ComponentDatasheet::class)->orderBy('sort_order');
    }

    // 保管棚（多対多）
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'component_location');
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    // 仕入先情報
    public function componentSuppliers(): HasMany
    {
        return $this->hasMany(ComponentSupplier::class);
    }

    // 商社（多対多 through component_suppliers）
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'component_suppliers')
                    ->withPivot('supplier_part_number', 'product_url', 'unit_price', 'price_updated_at', 'is_preferred')
                    ->withTimestamps();
    }

    // 在庫ブロック
    public function inventoryBlocks(): HasMany
    {
        return $this->hasMany(InventoryBlock::class);
    }

    // 入出庫履歴
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // プロジェクト（多対多）
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'component_project')
                    ->withPivot('required_qty');
    }

    // Altium連携
    public function altiumLink(): HasOne
    {
        return $this->hasOne(ComponentAltiumLink::class);
    }

    // 登録者・更新者
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // 在庫警告スコープ: 発注点を下回っている部品
    public function scopeNeedsReorder($query)
    {
        return $query->where(function ($q) {
            $q->whereRaw('quantity_new < threshold_new')
              ->orWhereRaw('quantity_used < threshold_used');
        });
    }
}
