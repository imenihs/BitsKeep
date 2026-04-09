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

    /** Notion由来など編集不可案件を除くスコープ */
    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    /** source_type + source_key で一意のレコードを取得 */
    public static function findBySource(string $sourceType, string $sourceKey): ?static
    {
        return static::where('source_type', $sourceType)
                     ->where('source_key', $sourceKey)
                     ->first();
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_project')
                    ->withPivot('required_qty');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
