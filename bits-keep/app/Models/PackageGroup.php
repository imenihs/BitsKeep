<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sort_order',
    ];

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class)->orderBy('sort_order')->orderBy('name');
    }
}
