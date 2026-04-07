<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;     // created_atのみ手動セット

    protected $fillable = [
        'user_id', 'action', 'resource_type', 'resource_id',
        'diff', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = ['diff' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
