<?php

namespace App\Models;

use App\Services\Router\ChannelRouterService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道模型配置
 *
 * 管理渠道支持的模型列表，包括模型映射、默认模型、限流等配置。
 * 从 channels 表的 models 字段迁移而来，提供更灵活的模型管理。
 *
 * 数据表结构 (channel_models):
 * ┌──────────────────┬───────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 类型          │ 说明                                     │
 * ├──────────────────┼───────────────┼─────────────────────────────────────────┤
 * │ id               │ bigint        │ 主键，自增                                │
 * │ channel_id       │ bigint        │ 关联的渠道 ID                              │
 * │ model_name       │ varchar(255)  │ 模型名称（如 gpt-4、claude-3-opus）       │
 * │ display_name     │ varchar(255)  │ 显示名称                                  │
 * │ mapped_model     │ varchar(255)  │ 映射到上游的实际模型名称                    │
 * │ is_default       │ tinyint(1)    │ 是否为默认模型                            │
 * │ is_enabled       │ tinyint(1)    │ 是否启用                                  │
 * │ rpm_limit        │ int           │ 每分钟请求限制                             │
 * │ context_length   │ int           │ 上下文长度                                │
 * │ multiplier       │ decimal(8,4)  │ 消耗倍率（默认1.0000）                     │
 * │ config           │ json          │ 额外配置                                  │
 * │ created_at       │ timestamp     │ 创建时间                                  │
 * │ updated_at       │ timestamp     │ 更新时间                                  │
 * └──────────────────┴───────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - UNIQUE (channel_id, model_name) - 渠道+模型名唯一约束
 * - INDEX (channel_id, is_enabled) - 按渠道查询启用模型
 * - INDEX (channel_id, is_default) - 按渠道查询默认模型
 *
 * 迁移历史：
 * - 2026_03_07_100005: 初始创建表
 * - 2026_03_07_100006: 从 channels.models 字段迁移数据到本表
 *
 * 核心功能：
 * 1. 模型映射：model_name → mapped_model，实现请求模型到上游模型的转换
 * 2. 默认模型：每个渠道只能有一个默认模型（通过 saving 事件保证）
 * 3. 启用/禁用：可单独控制每个模型的启用状态
 * 4. 限流配置：支持 RPM 限制和上下文长度配置
 * 5. 消耗倍率：用于计费计算
 *
 * @property int $id 主键
 * @property int $channel_id 关联的渠道 ID
 * @property string $model_name 模型名称
 * @property string|null $display_name 显示名称
 * @property string|null $mapped_model 映射到上游的实际模型名称
 * @property bool $is_default 是否为默认模型
 * @property bool $is_enabled 是否启用
 * @property int|null $rpm_limit 每分钟请求限制
 * @property int|null $context_length 上下文长度
 * @property string $multiplier 消耗倍率
 * @property array|null $config 额外配置
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read Channel $channel 所属渠道
 *
 * @see Channel 渠道模型
 * @see ChannelRouterService 渠道路由服务
 */
class ChannelModel extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'channel_models';

    /**
     * 模型保存事件
     *
     * 保证每个渠道只能有一个默认模型：
     * 当设置某模型为默认时，自动取消该渠道其他模型的默认状态
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // 如果设置为默认模型，取消该渠道其他模型的默认状态
            if ($model->is_default) {
                static::where('channel_id', $model->channel_id)
                    ->where('id', '!=', $model->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'model_name',
        'display_name',
        'mapped_model',
        'is_default',
        'is_enabled',
        'rpm_limit',
        'context_length',
        'multiplier',
        'config',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
            'multiplier' => 'decimal:4',
            'config' => 'array',
        ];
    }

    /**
     * 所属渠道
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 获取显示名称
     *
     * 优先返回 display_name，未设置则返回 model_name
     */
    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->model_name;
    }

    /**
     * 获取实际调用的模型名称（核心方法）
     *
     * 优先返回 mapped_model，未设置则返回 model_name
     * 用于将请求的模型名称映射到上游实际模型
     *
     *
     * @see Channel::getModelMappingsArray() 使用此方法获取映射
     */
    public function getMappedModel(): string
    {
        return $this->mapped_model ?? $this->model_name;
    }

    /**
     * 作用域：只查询启用的模型
     *
     * @return Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 作用域：只查询默认模型
     *
     * @return Builder
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
