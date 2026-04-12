<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'package_group_id',
        'name', 'description', 'size_x', 'size_y', 'size_z',
        'image_path', 'model_path', 'pdf_path', 'sort_order',
    ];

    public function packageGroup(): BelongsTo
    {
        return $this->belongsTo(PackageGroup::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }
}
