<?php

namespace App\Models;

use App\Services\Router\Logger\ChannelRequestLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道请求日志模型
 *
 * 记录每次渠道请求的完整信息，包括请求/响应详情、性能指标、错误信息等。
 * 是请求链路中的核心日志表，一个 AuditLog 可对应多条 ChannelRequestLog（重试场景）。
 *
 * 数据表结构 (channel_request_logs):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ audit_log_id             │ bigint unsigned       │ 关联审计日志 ID（可空，重试时无审计）       │
 * │ request_log_id           │ bigint unsigned       │ 关联请求日志 ID                            │
 * │ request_id               │ varchar(50)           │ 请求唯一标识                              │
 * │ channel_id               │ bigint unsigned       │ 渠道 ID                                  │
 * │ channel_name             │ varchar(100)          │ 渠道名称（冗余）                          │
 * │ provider                 │ varchar(50)           │ 渠道提供商                                │
 * │ method                   │ varchar(10)           │ HTTP 方法（默认 POST）                    │
 * │ path                     │ varchar(500)          │ 请求路径                                  │
 * │ base_url                 │ varchar(500)          │ 渠道 Base URL                             │
 * │ full_url                 │ varchar(1000)         │ 完整请求 URL                              │
 * │ request_headers          │ json                  │ 请求头                                    │
 * │ request_body             │ longtext              │ 请求体内容                                │
 * │ request_size             │ int unsigned          │ 请求体大小（字节，默认0）                  │
 * │ response_status          │ smallint unsigned     │ 响应状态码                                │
 * │ response_headers         │ json                  │ 响应头                                    │
 * │ response_body            │ longtext              │ 响应体内容                                │
 * │ response_body_chunks     │ longtext              │ 流式响应块内容                            │
 * │ response_size            │ int unsigned          │ 响应体大小（字节，默认0）                  │
 * │ latency_ms               │ int unsigned          │ 请求延迟（毫秒，默认0）                    │
 * │ ttfb_ms                  │ int unsigned          │ 首字节时间（毫秒，默认0）                  │
 * │ is_success               │ tinyint(1)            │ 是否成功（默认0）                          │
 * │ error_type               │ varchar(100)          │ 错误类型                                  │
 * │ error_message            │ text                  │ 错误消息                                  │
 * │ usage                    │ json                  │ Token 使用详情                            │
 * │ metadata                 │ json                  │ 额外元数据                                │
 * │ sent_at                  │ timestamp             │ 发送时间                                  │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (audit_log_id) - 按审计日志查询
 * - INDEX (request_log_id) - 按请求日志查询
 * - INDEX (request_id) - 按请求标识查询
 * - INDEX (channel_id) - 按渠道查询
 * - INDEX (channel_id, created_at) - 按渠道+时间范围查询
 * - INDEX (is_success, created_at) - 按成功/失败+时间范围查询
 * - INDEX (sent_at) - 按发送时间查询
 *
 * 迁移历史：
 * - 2026_03_10_000000: 初始创建表
 * - 2026_03_10_142455: audit_log_id 改为可空（支持重试场景无审计日志）
 * - 2026_03_15_005447: 新增 response_body_chunks 字段（流式响应块记录）
 *
 * 核心功能：
 * 1. 请求追踪：记录每次渠道请求的完整生命周期
 * 2. 性能监控：latency_ms、ttfb_ms 指标用于渠道性能分析
 * 3. 错误诊断：error_type + error_message 记录失败原因
 * 4. 用量统计：usage 字段记录 Token 消耗详情
 * 5. 重试支持：一个请求可产生多条记录（每次重试一条）
 *
 * @property int $id 主键
 * @property int|null $audit_log_id 关联审计日志 ID
 * @property int|null $request_log_id 关联请求日志 ID
 * @property string $request_id 请求唯一标识
 * @property int $channel_id 渠道 ID
 * @property string|null $channel_name 渠道名称（冗余）
 * @property string|null $provider 渠道提供商
 * @property string $method HTTP 方法
 * @property string $path 请求路径
 * @property string|null $base_url 渠道 Base URL
 * @property string|null $full_url 完整请求 URL
 * @property array|null $request_headers 请求头
 * @property array|null $request_body 请求体内容
 * @property int $request_size 请求体大小（字节）
 * @property int|null $response_status 响应状态码
 * @property array|null $response_headers 响应头
 * @property array|null $response_body 响应体内容
 * @property array|null $response_body_chunks 流式响应块内容
 * @property int $response_size 响应体大小（字节）
 * @property int $latency_ms 请求延迟（毫秒）
 * @property int $ttfb_ms 首字节时间（毫秒）
 * @property bool $is_success 是否成功
 * @property string|null $error_type 错误类型
 * @property string|null $error_message 错误消息
 * @property array|null $usage Token 使用详情
 * @property array|null $metadata 额外元数据
 * @property Carbon|null $sent_at 发送时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read AuditLog|null $auditLog 关联的审计日志
 * @property-read RequestLog|null $requestLog 关联的请求日志
 * @property-read Channel|null $channel 关联的渠道
 *
 * @see AuditLog 审计日志模型
 * @see RequestLog 请求日志模型
 * @see Channel 渠道模型
 * @see ChannelRequestLogger 渠道请求日志记录器
 */
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
        'response_body_chunks',
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
            'response_body_chunks' => 'array',
            'usage' => 'array',
            'metadata' => 'array',
            'is_success' => 'boolean',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 关联的审计日志
     *
     * 一个渠道请求日志属于一个审计日志（重试场景下可为空）
     *
     * @see AuditLog
     */
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }

    /**
     * 关联的请求日志
     *
     * @see RequestLog
     */
    public function requestLog(): BelongsTo
    {
        return $this->belongsTo(RequestLog::class, 'request_log_id');
    }

    /**
     * 关联的渠道
     *
     * @see Channel
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
