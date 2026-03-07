<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * 请求类型常量
     */
    public const REQUEST_TYPE_CHAT = 1;
    public const REQUEST_TYPE_COMPLETION = 2;
    public const REQUEST_TYPE_EMBEDDING = 3;
    public const REQUEST_TYPE_OTHER = 4;

    /**
     * 计费来源常量
     */
    public const BILLING_SOURCE_WALLET = 'wallet';
    public const BILLING_SOURCE_QUOTA = 'quota';
    public const BILLING_SOURCE_TRIAL = 'trial';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'username',
        'api_key_id',
        'api_key_name',
        'cached_key_prefix',
        'channel_id',
        'channel_name',
        'request_id',
        'request_type',
        'model',
        'actual_model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cache_read_tokens',
        'cache_write_tokens',
        'cost',
        'quota',
        'billing_source',
        'status_code',
        'latency_ms',
        'first_token_ms',
        'is_stream',
        'finish_reason',
        'error_type',
        'error_message',
        'client_ip',
        'user_agent',
        'group_name',
        'channel_affinity',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'channel_affinity' => 'array',
            'metadata' => 'array',
            'cost' => 'decimal:6',
            'quota' => 'decimal:6',
            'is_stream' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function requestLog(): HasOne
    {
        return $this->hasOne(RequestLog::class, 'audit_log_id');
    }

    public function responseLog(): HasOne
    {
        return $this->hasOne(ResponseLog::class, 'audit_log_id');
    }

    public static function getRequestTypes(): array
    {
        return [
            self::REQUEST_TYPE_CHAT => '聊天',
            self::REQUEST_TYPE_COMPLETION => '补全',
            self::REQUEST_TYPE_EMBEDDING => '嵌入',
            self::REQUEST_TYPE_OTHER => '其他',
        ];
    }

    public static function getBillingSources(): array
    {
        return [
            self::BILLING_SOURCE_WALLET => '钱包',
            self::BILLING_SOURCE_QUOTA => '配额',
            self::BILLING_SOURCE_TRIAL => '试用',
        ];
    }

    public function getRequestTypeLabel(): string
    {
        return self::getRequestTypes()[$this->request_type] ?? '未知';
    }

    public function getBillingSourceLabel(): string
    {
        return self::getBillingSources()[$this->billing_source] ?? '未知';
    }
}
