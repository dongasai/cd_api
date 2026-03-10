<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'audit_log_id',
        'request_id',
        'run_unid',
        'channel_id',
        'channel_name',
        'method',
        'path',
        'query_string',
        'headers',
        'content_type',
        'content_length',
        'body_text',
        'to_request_body',
        'body_binary',
        'model',
        'upstream_model',
        'model_params',
        'messages',
        'prompt',
        'sensitive_fields',
        'has_sensitive',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'model_params' => 'array',
            'messages' => 'array',
            'sensitive_fields' => 'array',
            'has_sensitive' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }
}
