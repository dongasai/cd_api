<?php

namespace App\Models;

use App\Services\ChannelAffinity\ChannelAffinityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 渠道亲和性规则模型
 *
 * 定义渠道亲和性匹配规则，决定特定请求应该路由到哪个渠道。
 * 支持基于模型、路径、User-Agent 的多维度匹配。
 *
 * 数据表结构 (channel_affinity_rules):
 * ┌──────────────────────────┬─────────────┬───────────────────────────────────────────┐
 * │ 字段名                    │ 类型        │ 说明                                       │
 * ├──────────────────────────┼─────────────┼───────────────────────────────────────────┤
 * │ id                       │ bigint      │ 主键，自增                                  │
 * │ name                     │ varchar(100)│ 规则名称                                    │
 * │ description              │ text        │ 规则描述                                    │
 * │ model_patterns           │ text        │ 模型匹配正则表达式                           │
 * │ path_patterns            │ varchar(255)│ 请求路径匹配                                │
 * │ user_agent_patterns      │ json        │ User-Agent 包含匹配数组                     │
 * │ key_sources              │ json        │ Key 来源配置数组                            │
 * │ key_combine_strategy     │ varchar(20) │ 多 Key 组合策略: first/concat/hash          │
 * │ ttl_seconds              │ int         │ 缓存 TTL（秒）                              │
 * │ param_override_template  │ json        │ 参数覆盖模板                                │
 * │ skip_retry_on_failure    │ tinyint(1)  │ 失败后是否跳过重试                           │
 * │ include_group_in_key     │ tinyint(1)  │ 是否包含分组在 cache key 中                 │
 * │ is_enabled               │ tinyint(1)  │ 是否启用                                    │
 * │ priority                 │ int         │ 优先级（数值越大越优先）                      │
 * │ hit_count                │ bigint      │ 命中次数统计                                 │
 * │ last_hit_at              │ timestamp   │ 最后命中时间                                 │
 * │ created_at               │ timestamp   │ 创建时间                                    │
 * │ updated_at               │ timestamp   │ 更新时间                                    │
 * │ deleted_at               │ timestamp   │ 软删除时间                                  │
 * └──────────────────────────┴─────────────┴───────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX idx_enabled_priority: (is_enabled, priority) - 启用状态+优先级复合索引
 *
 * 迁移历史：
 * - 2026_03_09_174133: 初始创建表
 * - 2026_03_14_081116: 修改 model_patterns 字段类型为 text
 * - 2026_03_14_081334: 修改 path_patterns 字段类型为 varchar(255)
 *
 * 核心功能：
 * 1. 流量识别：基于模型、路径、User-Agent 的正则匹配
 * 2. 缓存策略：支持自定义 Key 组合方式（first/concat/hash）
 * 3. TTL 管理：可配置缓存过期时间
 * 4. 参数覆盖：通过模板覆盖请求参数
 * 5. 优先级控制：数值越大优先级越高
 * 6. 命中统计：记录规则命中次数和时间
 *
 * 匹配流程：
 * 1. 从请求中提取 model、path、user_agent
 * 2. 按优先级遍历启用的规则
 * 3. 检查是否匹配 model_patterns、path_patterns、user_agent_patterns
 * 4. 匹配成功则根据 key_sources 生成缓存键
 * 5. 查找或创建渠道亲和性缓存
 *
 * @property int $id 主键
 * @property string $name 规则名称
 * @property string|null $description 规则描述
 * @property string|null $model_patterns 模型匹配正则表达式
 * @property string|null $path_patterns 请求路径匹配
 * @property array|null $user_agent_patterns User-Agent 包含匹配数组
 * @property array|null $key_sources Key 来源配置数组
 * @property string $key_combine_strategy 多 Key 组合策略
 * @property int $ttl_seconds 缓存 TTL（秒）
 * @property array|null $param_override_template 参数覆盖模板
 * @property bool $skip_retry_on_failure 失败后是否跳过重试
 * @property bool $include_group_in_key 是否包含分组在 cache key 中
 * @property bool $is_enabled 是否启用
 * @property int $priority 优先级
 * @property int $hit_count 命中次数统计
 * @property Carbon|null $last_hit_at 最后命中时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 *
 * @see ChannelAffinityCache 亲和性缓存模型
 * @see ChannelAffinityService 亲和性服务
 */
class ChannelAffinityRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'model_patterns',
        'path_patterns',
        'user_agent_patterns',
        'key_sources',
        'key_combine_strategy',
        'ttl_seconds',
        'param_override_template',
        'skip_retry_on_failure',
        'include_group_in_key',
        'is_enabled',
        'priority',
        'hit_count',
        'last_hit_at',
    ];

    protected $attributes = [
        'key_combine_strategy' => 'first',
        'ttl_seconds' => 120,
        'skip_retry_on_failure' => false,
        'include_group_in_key' => false,
        'is_enabled' => true,
        'priority' => 0,
        'hit_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'user_agent_patterns' => 'array',
            'key_sources' => 'array',
            'param_override_template' => 'array',
            'skip_retry_on_failure' => 'boolean',
            'include_group_in_key' => 'boolean',
            'is_enabled' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * 记录规则命中
     *
     * 增加命中计数并更新最后命中时间
     *
     * @see ChannelAffinityService::matchRule() 匹配成功时调用
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    /**
     * 查询启用的规则
     *
     * @return Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 按优先级排序（数值越大越优先）
     *
     * @return Builder
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
