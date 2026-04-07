<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Package extends Model
{
    protected $fillable = [
        'name', 'description', 'size_x', 'size_y', 'size_z',
        'image_path', 'model_path', 'pdf_path', 'sort_order',
    ];

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'component_package');
    }
}
