<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_log_id',
        'request_log_id',
        'request_id',
        'channel_id',
        'channel_name',
        'provider',
        'method',
        'path',
        'base_url',
        'full_url',
        'request_headers',
        'request_body',
        'request_size',
        'response_status',
        'response_headers',
        'response_body',
        'response_size',
        'latency_ms',
        'ttfb_ms',
        'is_success',
        'error_type',
        'error_message',
        'usage',
        'metadata',
        'sent_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'response_headers' => 'array',
            'request_body' => 'array',
            'response_body' => 'array',
            'usage' => 'array',
            'metadata' => 'array',
            'is_success' => 'boolean',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }

    public function requestLog(): BelongsTo
    {
        return $this->belongsTo(RequestLog::class, 'request_log_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
