<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComponentAttribute extends Model
{
    protected $fillable = ['component_id', 'key', 'value'];
}
