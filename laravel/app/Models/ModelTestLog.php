<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 模型测试日志模型
 *
 * 记录后台模型测试功能的完整测试过程，包括请求/响应、性能指标、Token 消耗等。
 * 支持两种测试类型：渠道直接测试（channel_direct）和系统 API 测试（system_api）。
 *
 * 数据表结构 (model_test_logs):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ test_type                │ enum                  │ 测试类型：channel_direct/system_api        │
 * │ channel_id               │ bigint unsigned       │ 渠道 ID                                  │
 * │ channel_name             │ varchar(100)          │ 渠道名称                                  │
 * │ model                    │ varchar(100)          │ 测试模型                                  │
 * │ actual_model             │ varchar(100)          │ 实际上游模型                              │
 * │ api_key_id               │ bigint unsigned       │ API Key ID                               │
 * │ api_key_name             │ varchar(100)          │ API Key 名称                              │
 * │ prompt_preset_id         │ bigint unsigned       │ 关联提示词 ID                             │
 * │ system_prompt            │ text                  │ 系统提示词                                │
 * │ user_message             │ text                  │ 用户消息                                  │
 * │ assistant_response       │ longtext              │ AI 响应                                   │
 * │ request_headers          │ json                  │ 请求头                                    │
 * │ is_stream                │ tinyint(1)            │ 是否流式（默认0）                          │
 * │ response_time_ms         │ int unsigned          │ 响应时间（毫秒）                           │
 * │ first_token_ms           │ int unsigned          │ 首 Token 时间（毫秒）                      │
 * │ prompt_tokens            │ int unsigned          │ 输入 Token 数                             │
 * │ completion_tokens        │ int unsigned          │ 输出 Token 数                             │
 * │ total_tokens             │ int unsigned          │ 总 Token 数                               │
 * │ status                   │ enum                  │ 状态：success/failed/timeout（默认 success）│
 * │ error_message            │ text                  │ 错误信息                                  │
 * │ metadata                 │ json                  │ 元数据                                    │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (test_type, created_at) - 按测试类型+时间查询
 * - INDEX (channel_id, created_at) - 按渠道+时间查询
 * - INDEX (api_key_id, created_at) - 按 API Key+时间查询
 * - INDEX (model, created_at) - 按模型+时间查询
 * - INDEX (status) - 按状态查询
 *
 * 迁移历史：
 * - 2026_03_14_112508: 初始创建表
 *
 * 核心功能：
 * 1. 测试记录：保存完整的测试请求/响应数据
 * 2. 性能监控：response_time_ms、first_token_ms 指标
 * 3. Token 统计：prompt_tokens、completion_tokens、total_tokens
 * 4. 测试分类：渠道直接测试 vs 系统 API 测试
 * 5. 错误诊断：status + error_message 记录失败原因
 *
 * @property int $id 主键
 * @property string $test_type 测试类型
 * @property int|null $channel_id 渠道 ID
 * @property string|null $channel_name 渠道名称
 * @property string $model 测试模型
 * @property string|null $actual_model 实际上游模型
 * @property int|null $api_key_id API Key ID
 * @property string|null $api_key_name API Key 名称
 * @property int|null $prompt_preset_id 关联提示词 ID
 * @property string|null $system_prompt 系统提示词
 * @property string|null $user_message 用户消息
 * @property string|null $assistant_response AI 响应
 * @property array|null $request_headers 请求头
 * @property bool $is_stream 是否流式
 * @property int|null $response_time_ms 响应时间（毫秒）
 * @property int|null $first_token_ms 首 Token 时间（毫秒）
 * @property int|null $prompt_tokens 输入 Token 数
 * @property int|null $completion_tokens 输出 Token 数
 * @property int|null $total_tokens 总 Token 数
 * @property string $status 状态
 * @property string|null $error_message 错误信息
 * @property array|null $metadata 元数据
 * @property Carbon $created_at 创建时间
 * @property-read Channel|null $channel 关联的渠道
 * @property-read PresetPrompt|null $presetPrompt 关联的预设提示词
 *
 * @see Channel 渠道模型
 * @see PresetPrompt 预设提示词模型
 * @see \App\Admin\Controllers\ModelTestController 后台测试控制器
 * @see \App\Services\ModelTestService 模型测试服务
 */
class ModelTestLog extends Model
{
    use HasFactory;

    /**
     * 测试类型常量：渠道直接测试
     */
    public const TEST_TYPE_CHANNEL_DIRECT = 'channel_direct';

    /**
     * 测试类型常量：系统 API 测试
     */
    public const TEST_TYPE_SYSTEM_API = 'system_api';

    /**
     * 状态常量：成功
     */
    public const STATUS_SUCCESS = 'success';

    /**
     * 状态常量：失败
     */
    public const STATUS_FAILED = 'failed';

    /**
     * 状态常量：超时
     */
    public const STATUS_TIMEOUT = 'timeout';

    /**
     * 不使用 updated_at 字段
     */
    public const UPDATED_AT = null;

    /**
     * 可批量赋值的属性
     *
     * @var list<string>
     */
    protected $fillable = [
        'test_type',
        'channel_id',
        'channel_name',
        'model',
        'actual_model',
        'api_key_id',
        'api_key_name',
        'prompt_preset_id',
        'system_prompt',
        'user_message',
        'assistant_response',
        'request_headers',
        'is_stream',
        'response_time_ms',
        'first_token_ms',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'status',
        'error_message',
        'metadata',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'metadata' => 'array',
            'is_stream' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 关联渠道
     *
     * @see Channel
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 关联预设提示词
     *
     * @see PresetPrompt
     */
    public function presetPrompt(): BelongsTo
    {
        return $this->belongsTo(PresetPrompt::class, 'prompt_preset_id');
    }

    /**
     * 作用域：按渠道筛选
     */
    public function scopeByChannel($query, int $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    /**
     * 作用域：按模型筛选
     */
    public function scopeByModel($query, string $model)
    {
        return $query->where('model', $model);
    }

    /**
     * 作用域：按测试类型筛选
     */
    public function scopeByTestType($query, string $testType)
    {
        return $query->where('test_type', $testType);
    }

    /**
     * 作用域：仅成功的记录
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * 作用域：按创建时间倒序
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 获取所有测试类型选项
     *
     * @return array<string, string>
     */
    public static function getTestTypes(): array
    {
        return [
            self::TEST_TYPE_CHANNEL_DIRECT => '渠道直接测试',
            self::TEST_TYPE_SYSTEM_API => '系统API测试',
        ];
    }

    /**
     * 获取所有状态选项
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED => '失败',
            self::STATUS_TIMEOUT => '超时',
        ];
    }

    /**
     * 获取测试类型标签
     */
    public function getTestTypeLabel(): string
    {
        return self::getTestTypes()[$this->test_type] ?? $this->test_type;
    }

    /**
     * 获取状态标签
     */
    public function getStatusLabel(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * 是否为渠道直接测试
     */
    public function isChannelDirectTest(): bool
    {
        return $this->test_type === self::TEST_TYPE_CHANNEL_DIRECT;
    }

    /**
     * 是否为系统API测试
     */
    public function isSystemApiTest(): bool
    {
        return $this->test_type === self::TEST_TYPE_SYSTEM_API;
    }

    /**
     * 是否成功
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * 获取元数据
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * 获取请求头部
     *
     * @return array<string, string>
     */
    public function getRequestHeaders(): array
    {
        return $this->request_headers ?? [];
    }
}
