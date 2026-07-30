<?php

namespace App\Models;

use App\Services\Router\Logger\ResponseLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 响应日志模型
 *
 * 记录 AI API 请求的完整响应数据，包括响应头、响应体、生成内容、错误信息和上游详情。
 * 与 AuditLog 和 RequestLog 形成完整的请求-响应链路追踪。
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ 字段说明                                                                      │
 * ├─────────────────────────────────────────────────────────────────────────────┤
 * │ 关联信息                                                                      │
 * │   audit_log_id      bigint      关联审计日志 ID                             │
 * │   request_id        varchar(50) 请求唯一标识                                │
 * │   request_log_id    bigint      关联请求日志 ID                             │
 * │                                                                              │
 * │ 响应基础信息                                                                  │
 * │   status_code       smallint    HTTP 状态码                                 │
 * │   status_message    varchar(100)状态消息                                     │
 * │                                                                              │
 * │ 响应体                                                                        │
 * │   content_type      varchar(100)Content-Type                                 │
 * │   content_length    int         响应体长度（字节）                          │
 * │   body_text         longtext    响应体文本内容                             │
 * │   body_binary       blob        响应体二进制内容                           │
 * │                                                                              │
 * │ 响应内容解析                                                                  │
 * │   response_type     varchar(50) 响应类型: chat/completion/embedding/error    │
 * │   finish_reason     varchar(50) 完成原因                                     │
 * │                                                                              │
 * │ 生成内容                                                                      │
 * │   generated_text    longtext    生成的文本内容（聚合后的完整内容）        │
 * │   generated_chunks  json        流式响应的分块内容数组                     │
 * │                                                                              │
 * │ 使用量                                                                        │
 * │   usage             json        Token 使用量详情                           │
 * │                                                                              │
 * │ 错误信息（当 response_type=error 时使用）                                   │
 * │   error_type        varchar(100)错误类型                                     │
 * │   error_code        varchar(50) 错误代码                                     │
 * │   error_message     text        错误消息                                   │
 * │   error_details     json        错误详情                                   │
 * │                                                                              │
 * │ 上游信息                                                                      │
 * │   upstream_provider varchar(50) 上游提供商                                   │
 * │   upstream_model    varchar(100)上游实际模型                               │
 * │   upstream_latency_ms int       上游响应延迟（毫秒）                       │
 * │                                                                              │
 * │ 元数据                                                                        │
 * │   headers           json        响应头                                     │
 * │   metadata          json        额外元数据                                 │
 * │   created_at        timestamp   创建时间                                   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * 索引说明：
 * - PRIMARY KEY (id)
 * - INDEX (audit_log_id)
 * - INDEX (request_log_id)
 * - INDEX (request_id)
 * - INDEX (status_code, created_at)         -- 用于按状态码统计
 * - INDEX (upstream_provider, created_at)   -- 用于按提供商统计
 *
 * 迁移历史：
 * - 2026_03_07_083346: 创建 response_logs 表
 *
 * 核心功能：
 * 1. 响应类型常量定义（RESPONSE_TYPE_CHAT/COMPLETION/EMBEDDING/ERROR）
 * 2. 关联 AuditLog 和 RequestLog 模型
 * 3. 响应类型标签转换（getResponseTypeLabel）
 *
 * @property int $id 主键ID
 * @property int $audit_log_id 关联审计日志ID
 * @property string $request_id 请求唯一标识
 * @property int $request_log_id 关联请求日志ID
 * @property int $status_code HTTP状态码
 * @property string|null $status_message 状态消息
 * @property array|null $headers 响应头
 * @property string|null $content_type Content-Type
 * @property int $content_length 响应体长度
 * @property string|null $body_text 响应体文本内容
 * @property string|null $body_binary 响应体二进制内容
 * @property string|null $response_type 响应类型
 * @property string|null $finish_reason 完成原因
 * @property string|null $generated_text 生成的文本内容
 * @property array|null $generated_chunks 流式响应分块内容
 * @property array|null $usage Token使用量详情
 * @property string|null $error_type 错误类型
 * @property string|null $error_code 错误代码
 * @property string|null $error_message 错误消息
 * @property array|null $error_details 错误详情
 * @property string|null $upstream_provider 上游提供商
 * @property string|null $upstream_model 上游实际模型
 * @property int $upstream_latency_ms 上游响应延迟（毫秒）
 * @property array|null $metadata 额外元数据
 * @property Carbon $created_at 创建时间
 * @property-read AuditLog $auditLog 关联审计日志
 * @property-read RequestLog $requestLog 关联请求日志
 *
 * @see AuditLog
 * @see RequestLog
 * @see ResponseLogger
 */
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

    /**
     * 关联审计日志
     *
     * @see AuditLog
     */
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }

    /**
     * 关联请求日志
     *
     * @see RequestLog
     */
    public function requestLog(): BelongsTo
    {
        return $this->belongsTo(RequestLog::class, 'request_log_id');
    }

    /**
     * 获取响应类型映射列表
     *
     * @return array<string, string>
     */
    public static function getResponseTypes(): array
    {
        return [
            self::RESPONSE_TYPE_CHAT => '聊天',
            self::RESPONSE_TYPE_COMPLETION => '补全',
            self::RESPONSE_TYPE_EMBEDDING => '嵌入',
            self::RESPONSE_TYPE_ERROR => '错误',
        ];
    }

    /**
     * 获取响应类型的中文标签
     *
     * @return string 响应类型中文标签，未知类型返回 '未知'
     */
    public function getResponseTypeLabel(): string
    {
        return self::getResponseTypes()[$this->response_type] ?? '未知';
    }
}
