<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 模型测试日志模型
 */
class ModelTestLog extends Model
{
    use HasFactory;

    // 测试类型常量
    public const TEST_TYPE_CHANNEL_DIRECT = 'channel_direct';

    public const TEST_TYPE_SYSTEM_API = 'system_api';

    // 状态常量
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

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
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 关联预设提示词
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
