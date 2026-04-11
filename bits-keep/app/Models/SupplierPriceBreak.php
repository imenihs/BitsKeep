<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPriceBreak extends Model
{
    protected $fillable = ['min_qty', 'unit_price'];
}
