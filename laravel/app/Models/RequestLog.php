<?php

namespace App\Models;

use App\Services\Router\ProxyServer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 请求日志模型
 *
 * 记录客户端发起的 HTTP 请求详情，包含请求头、请求体、模型参数等。
 * 是请求链路追踪的重要组成部分，与 AuditLog、ChannelRequestLog 形成完整的请求追踪链条。
 *
 * ┌─────────────────┬──────────────────┬──────────────────────────────────────────┐
 * │ 字段名          │ 类型             │ 说明                                     │
 * ├─────────────────┼──────────────────┼──────────────────────────────────────────┤
 * │ id              │ bigint unsigned  │ 主键 ID                                  │
 * │ audit_log_id    │ bigint unsigned  │ 关联审计日志 ID (nullable)               │
 * │ request_id      │ varchar(50)      │ 请求唯一标识                             │
 * │ run_unid        │ varchar(50)      │ 运行唯一标识                             │
 * │ channel_id      │ bigint unsigned  │ 渠道 ID (nullable)                       │
 * │ channel_name    │ varchar(100)      │ 渠道名称                                 │
 * │ method          │ varchar(10)      │ HTTP 方法 (GET/POST等)                   │
 * │ path            │ varchar(500)     │ 请求路径                                 │
 * │ query_string    │ text             │ URL 查询参数 (nullable)                  │
 * │ headers         │ json             │ 请求头 (脱敏处理, nullable)              │
 * │ content_type    │ varchar(100)     │ Content-Type (nullable)                  │
 * │ content_length  │ int unsigned     │ 请求体长度 (字节)                        │
 * │ body_text       │ longtext         │ 请求体文本内容 (nullable)                │
 * │ body_binary     │ blob             │ 请求体二进制内容 (nullable)              │
 * │ model           │ varchar(100)     │ 请求模型 (nullable)                      │
 * │ upstream_model  │ varchar(100)     │ 上游实际使用的模型 (nullable)            │
 * │ model_params    │ json             │ 模型参数 (nullable)                      │
 * │ messages        │ json             │ 聊天消息列表 (nullable)                  │
 * │ prompt          │ text             │ 提示词 (nullable)                        │
 * │ sensitive_fields│ json             │ 已脱敏的字段列表 (nullable)              │
 * │ has_sensitive   │ tinyint(1)       │ 是否包含敏感信息 (默认0)                 │
 * │ metadata        │ json             │ 额外元数据 (nullable)                    │
 * │ created_at      │ timestamp        │ 创建时间                                 │
 * └─────────────────┴──────────────────┴──────────────────────────────────────────┘
 *
 * 索引说明：
 * - PRIMARY: id
 * - request_logs_audit_log_id_index: audit_log_id
 * - request_logs_channel_id_index: channel_id
 * - request_logs_model_created_at_index: model + created_at (复合索引)
 * - request_logs_request_id_index: request_id
 * - request_logs_run_unid_index: run_unid
 *
 * 迁移历史：
 * - 2026_03_07_083345: 创建 request_logs 表
 * - 2026_03_07_174337: 修改 audit_log_id 为 nullable
 *
 * 核心功能：
 * 1. 记录客户端请求的完整信息（请求头、请求体）
 * 2. 敏感信息自动脱敏存储（API Key、Authorization 等）
 * 3. 支持文本和二进制请求体存储
 * 4. 模型参数和消息列表 JSON 序列化存储
 * 5. 与审计日志关联，形成完整的请求追踪链路
 *
 * @property int $id 主键 ID
 * @property int|null $audit_log_id 关联审计日志 ID
 * @property string $request_id 请求唯一标识
 * @property string|null $run_unid 运行唯一标识
 * @property int|null $channel_id 渠道 ID
 * @property string|null $channel_name 渠道名称
 * @property string $method HTTP 方法
 * @property string $path 请求路径
 * @property string|null $query_string URL 查询参数
 * @property array|null $headers 请求头 (脱敏处理)
 * @property string|null $content_type Content-Type
 * @property int $content_length 请求体长度
 * @property string|null $body_text 请求体文本内容
 * @property string|null $body_binary 请求体二进制内容
 * @property string|null $model 请求模型
 * @property string|null $upstream_model 上游实际使用的模型
 * @property array|null $model_params 模型参数
 * @property array|null $messages 聊天消息列表
 * @property string|null $prompt 提示词
 * @property array|null $sensitive_fields 已脱敏的字段列表
 * @property bool $has_sensitive 是否包含敏感信息
 * @property array|null $metadata 额外元数据
 * @property Carbon $created_at 创建时间
 * @property-read AuditLog|null $auditLog 关联的审计日志
 *
 * @see AuditLog 审计日志模型
 * @see ChannelRequestLog 渠道请求日志模型
 * @see ProxyServer 请求代理服务
 */
class RequestLog extends Model
{
    use HasFactory;

    /**
     * 不自动管理时间戳
     *
     * @see Model::$timestamps
     */
    public $timestamps = false;

    /**
     * 可填充字段
     *
     * @see Model::$fillable
     */
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

    /**
     * 字段类型转换定义
     *
     * @see Model::casts()
     */
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

    /**
     * 关联审计日志
     *
     * 建立与 AuditLog 模型的 belongsTo 关联，用于追踪请求对应的审计记录。
     * audit_log_id 可为 null，表示该请求尚未关联到审计日志。
     *
     * @return BelongsTo<AuditLog, $this>
     *
     * @see AuditLog
     */
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }
}
