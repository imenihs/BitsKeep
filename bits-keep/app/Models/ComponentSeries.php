<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComponentSeries extends Model
{
    use SoftDeletes;

    protected $table = 'component_series';

    protected $fillable = [
        'spec_group_id',
        'value_spec_type_id',
        'package_id',
        'manufacturer',
        'name',
        'description',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function specGroup(): BelongsTo
    {
        return $this->belongsTo(SpecGroup::class);
    }

    public function valueSpecType(): BelongsTo
    {
        return $this->belongsTo(SpecType::class, 'value_spec_type_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function policy(): HasOne
    {
        return $this->hasOne(ComponentSeriesValuePolicy::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ComponentSeriesValue::class)->orderBy('sort_order')->orderBy('value_numeric')->orderBy('value_text');
    }

    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
