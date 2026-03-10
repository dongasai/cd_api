<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseLog extends Model
{
    use HasFactory;

    /**
     * 响应类型常量
     */
    public const RESPONSE_TYPE_CHAT = 'chat';

    public const RESPONSE_TYPE_COMPLETION = 'completion';

    public const RESPONSE_TYPE_EMBEDDING = 'embedding';

    public const RESPONSE_TYPE_ERROR = 'error';

    public $timestamps = false;

    protected $fillable = [
        'audit_log_id',
        'request_id',
        'request_log_id',
        'status_code',
        'status_message',
        'headers',
        'content_type',
        'content_length',
        'body_text',
        'body_binary',
        'response_type',
        'finish_reason',
        'generated_text',
        'generated_chunks',
        'usage',
        'error_type',
        'error_code',
        'error_message',
        'error_details',
        'upstream_provider',
        'upstream_model',
        'upstream_latency_ms',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'generated_chunks' => 'array',
            'usage' => 'array',
            'error_details' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
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

    public static function getResponseTypes(): array
    {
        return [
            self::RESPONSE_TYPE_CHAT => '聊天',
            self::RESPONSE_TYPE_COMPLETION => '补全',
            self::RESPONSE_TYPE_EMBEDDING => '嵌入',
            self::RESPONSE_TYPE_ERROR => '错误',
        ];
    }

    public function getResponseTypeLabel(): string
    {
        return self::getResponseTypes()[$this->response_type] ?? '未知';
    }
}
