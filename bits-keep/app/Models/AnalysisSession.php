<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalysisSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tool_id',
        'title',
        'verdict',
        'summary',
        'input_payload',
        'result_payload',
        'candidate_links',
        'project_id',
        'component_id',
        'bom_line_key',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'result_payload' => 'array',
        'candidate_links' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
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
