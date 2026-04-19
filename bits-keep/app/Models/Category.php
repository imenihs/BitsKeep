<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'color', 'sort_order'];

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_category');
    }
}
