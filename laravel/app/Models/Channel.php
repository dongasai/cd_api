<?php

namespace App\Models;

use App\Enums\ChannelHealthStatus;
use App\Enums\ChannelStatus;
use App\Enums\InheritMode;
use App\Services\ChannelInheritance\ChannelInheritanceResolver;
use App\Services\Provider\ProviderManager;
use App\Services\Router\ChannelRouterService;
use App\Services\Router\ProxyServer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 渠道模型
 *
 * 管理上游 AI 服务提供商的渠道配置，支持继承、负载均衡、健康监控等功能。
 *
 * 数据表结构 (channels):
 * ┌──────────────────────────┬──────────────────┬─────────────────────────────────────────────┐
 * │ 字段名                    │ 类型              │ 说明                                         │
 * ├──────────────────────────┼──────────────────┼─────────────────────────────────────────────┤
 * │ id                       │ bigint unsigned   │ 主键，自增                                    │
 * │ parent_id                │ bigint unsigned   │ 父渠道 ID（继承关系）                          │
 * │ inherit_mode             │ enum              │ 继承模式：merge/override                      │
 * │ name                     │ varchar(255)      │ 渠道名称                                      │
 * │ slug                     │ varchar(100)      │ 渠道标识                                      │
 * │ provider                 │ varchar(50)       │ 提供商类型（openai/anthropic/azure等）         │
 * │ base_url                 │ varchar(500)      │ API 基础 URL                                  │
 * │ api_key                  │ text              │ 加密的 API Key                                │
 * │ api_key_hash             │ varchar(64)       │ API Key 指纹（SHA256前8位）                    │
 * │ weight                   │ int unsigned      │ 负载均衡权重 (1-100)                           │
 * │ priority                 │ int unsigned      │ 优先级（越小越优先）                           │
 * │ status                   │ tinyint unsigned  │ 运营状态: 0=禁用, 1=启用                       │
 * │ status2                  │ enum              │ 健康状态：normal/disabled                      │
 * │ status2_remark           │ text              │ 健康状态备注                                   │
 * │ failure_count            │ int unsigned      │ 连续失败次数                                   │
 * │ success_count            │ int unsigned      │ 连续成功次数                                   │
 * │ last_check_at            │ timestamp         │ 最后健康检查时间                               │
 * │ last_failure_at          │ timestamp         │ 最后失败时间                                   │
 * │ last_success_at          │ timestamp         │ 最后成功时间                                   │
 * │ total_requests           │ bigint unsigned   │ 总请求数                                      │
 * │ total_tokens             │ bigint unsigned   │ 总 Token 数                                   │
 * │ total_cost               │ decimal(12,6)     │ 总成本                                        │
 * │ avg_latency_ms           │ int unsigned      │ 平均延迟（毫秒）                               │
 * │ success_rate             │ decimal(5,4)      │ 成功率（0.0000-1.0000）                        │
 * │ config                   │ json              │ 额外配置（filter_thinking/body_passthrough等）│
 * │ forward_headers          │ json              │ 转发到上游的请求头配置                          │
 * │ coding_account_id        │ bigint unsigned   │ 关联 Coding 账户 ID                           │
 * │ description              │ text              │ 渠道描述                                      │
 * │ has_user_agent_restriction│ tinyint(1)       │ 是否有 User-Agent 限制                         │
 * │ created_at               │ timestamp         │ 创建时间                                      │
 * │ updated_at               │ timestamp         │ 更新时间                                      │
 * │ deleted_at               │ timestamp         │ 软删除时间                                    │
 * └──────────────────────────┴──────────────────┴─────────────────────────────────────────────┘
 *
 * 迁移历史：
 * - 2026_03_07_083340: 初始创建表（含继承关系、状态管理、统计信息）
 * - 2026_03_07_100001: 添加 coding_account_id 和 coding_status_override
 * - 2026_03_07_100005: 创建 channel_models 表（模型配置独立）
 * - 2026_03_07_100006: 迁移 models 数据到 channel_models 表
 * - 2026_03_07_201642: 添加 forward_headers（请求头转发配置）
 * - 2026_03_09_012009: 添加 coding_last_check_at（已移除）
 * - 2026_03_09_160238: 移除 health_status 字段
 * - 2026_03_15_174851: 在 config 中添加 body_passthrough 配置
 * - 2026_03_17_150000: 添加 has_user_agent_restriction 标志
 * - 2026_07_31_083024: 移除 models、default_model 字段（已迁移到 channel_models 表）
 * - 2026_03_17_234843: 添加 status2 和 status2_remark（健康状态）
 * - 2026_03_24_013632: 将 status 从 enum 改为 tinyint
 *
 * 核心功能：
 * 1. 渠道继承：支持父子渠道继承配置（merge/override）
 * 2. 负载均衡：基于权重和优先级的智能路由
 * 3. 健康监控：失败/成功计数、自动禁用、状态管理
 * 4. 模型管理：通过 channel_models 表管理支持的模型列表
 * 5. 请求头转发：可配置转发特定请求头到上游
 * 6. User-Agent 限制：支持基于 UA 的访问控制
 * 7. Coding 账户绑定：关联 Coding 账户进行配额管理
 *
 * @property int $id 主键
 * @property int|null $parent_id 父渠道 ID
 * @property string $inherit_mode 继承模式：merge/override
 * @property string $name 渠道名称
 * @property string|null $slug 渠道标识
 * @property string $provider 提供商类型
 * @property string|null $base_url API 基础 URL
 * @property string|null $api_key 加密的 API Key
 * @property string|null $api_key_hash API Key 指纹
 * @property int $weight 负载均衡权重
 * @property int $priority 优先级
 * @property ChannelStatus $status 运营状态
 * @property ChannelHealthStatus $status2 健康状态
 * @property string|null $status2_remark 健康状态备注
 * @property int $failure_count 连续失败次数
 * @property int $success_count 连续成功次数
 * @property Carbon|null $last_check_at 最后健康检查时间
 * @property Carbon|null $last_failure_at 最后失败时间
 * @property Carbon|null $last_success_at 最后成功时间
 * @property int $total_requests 总请求数
 * @property int $total_tokens 总 Token 数
 * @property string $total_cost 总成本
 * @property int $avg_latency_ms 平均延迟
 * @property string $success_rate 成功率
 * @property array|null $config 额外配置
 * @property array|null $forward_headers 转发请求头配置
 * @property int|null $coding_account_id 关联 Coding 账户 ID
 * @property string|null $description 渠道描述
 * @property bool $has_user_agent_restriction 是否有 UA 限制
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 * @property-read Channel|null $parent 父渠道
 * @property-read Collection|Channel[] $children 子渠道
 * @property-read Collection|ChannelGroup[] $groups 所属分组
 * @property-read Collection|ChannelTag[] $tags 所属标签
 * @property-read CodingAccount|null $codingAccount Coding 账户
 * @property-read Collection|ChannelModel[] $channelModels 渠道模型列表
 * @property-read Collection|UserAgent[] $allowedUserAgents 允许的 UA 规则
 *
 * @see ChannelRouterService 渠道路由服务
 * @see ProviderManager 供应商管理器
 */
class Channel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'inherit_mode',
        'name',
        'slug',
        'provider',
        'base_url',
        'api_key',
        'api_key_hash',
        'weight',
        'priority',
        'status',
        'status2',
        'status2_remark',
        'failure_count',
        'success_count',
        'last_check_at',
        'last_failure_at',
        'last_success_at',
        'total_requests',
        'total_tokens',
        'total_cost',
        'avg_latency_ms',
        'success_rate',
        'config',
        'forward_headers',
        'coding_account_id',
        'description',
        'has_user_agent_restriction',
        'period_disabled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChannelStatus::class,
            'status2' => ChannelHealthStatus::class,
            'inherit_mode' => InheritMode::class,
            'config' => 'array',
            'forward_headers' => 'array',
            'last_check_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'last_success_at' => 'datetime',
            'total_cost' => 'decimal:6',
            'success_rate' => 'decimal:4',
            'has_user_agent_restriction' => 'boolean',
            'period_disabled_at' => 'datetime',
        ];
    }

    /**
     * 父渠道（继承关系）
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'parent_id');
    }

    /**
     * 子渠道（被继承）
     */
    public function children(): HasMany
    {
        return $this->hasMany(Channel::class, 'parent_id');
    }

    /**
     * 所属分组（多对多）
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ChannelGroup::class, 'channel_group_pivot', 'channel_id', 'group_id')
            ->withPivot('priority')
            ->withTimestamps();
    }

    /**
     * 所属标签（多对多）
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ChannelTag::class, 'channel_tag_pivot', 'channel_id', 'tag_id');
    }

    /**
     * 获取 API Key 的脱敏显示
     *
     * 显示格式：sk-...{前8位hash}
     */
    public function getMaskedApiKey(): string
    {
        if (empty($this->api_key_hash)) {
            return '未设置';
        }

        return 'sk-...'.$this->api_key_hash;
    }

    /**
     * 检查渠道运营状态是否为启用
     */
    public function isActive(): bool
    {
        return $this->status === ChannelStatus::ACTIVE;
    }

    /**
     * 检查渠道健康状态是否正常
     */
    public function isHealthNormal(): bool
    {
        return $this->status2 === ChannelHealthStatus::NORMAL;
    }

    /**
     * 检查渠道是否可以参与选择
     *
     * 同时满足两个条件：
     * 1. 运营状态为 active
     * 2. 健康状态为 normal
     *
     *
     * @see ChannelRouterService::selectChannel() 渠道选择时使用
     */
    public function isAvailableForSelection(): bool
    {
        return $this->isActive() && $this->isHealthNormal();
    }

    /**
     * 禁用渠道健康状态
     *
     * 将健康状态设置为 disabled，并记录禁用原因
     *
     * @param  string  $reason  禁用原因
     */
    public function disableHealth(string $reason): void
    {
        $this->update([
            'status2' => ChannelHealthStatus::DISABLED,
            'status2_remark' => $reason,
        ]);
    }

    /**
     * 启用渠道健康状态
     *
     * 将健康状态恢复为 normal，清空备注
     */
    public function enableHealth(): void
    {
        $this->update([
            'status2' => ChannelHealthStatus::NORMAL,
            'status2_remark' => null,
        ]);
    }

    /**
     * Coding 账户关联
     */
    public function codingAccount(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'coding_account_id');
    }

    /**
     * 检查是否绑定 Coding 账户
     */
    public function hasCodingAccount(): bool
    {
        return $this->coding_account_id !== null;
    }

    /**
     * 检查是否因时段控制被禁用
     */
    public function isDisabledByPeriodControl(): bool
    {
        return $this->period_disabled_at !== null;
    }

    /**
     * 获取需要转发的 header 名称列表
     *
     * 支持通配符匹配，如 'x-*' 匹配所有 x- 开头的请求头
     *
     * @return array header 名称列表
     */
    public function getForwardHeaderNames(): array
    {
        return $this->forward_headers ?? [];
    }

    /**
     * 获取配置项
     *
     * @param  string  $key  配置键名
     * @param  mixed  $default  默认值
     * @return mixed 配置值
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $config = $this->config ?? [];

        return $config[$key] ?? $default;
    }

    /**
     * 是否过滤 thinking 内容块（响应）
     *
     * 默认 false = 保留 thinking 内容
     */
    public function shouldFilterThinking(): bool
    {
        return $this->getConfig('filter_thinking', false);
    }

    /**
     * 是否过滤请求中的 thinking 内容块
     *
     * 默认 false = 保留请求中的 thinking 内容
     */
    public function shouldFilterRequestThinking(): bool
    {
        return $this->getConfig('filter_request_thinking', false);
    }

    /**
     * 是否透传请求体（body passthrough）
     *
     * 开启后，来自客户端的 body 将不进行任何处理直接发送给上游渠道
     * 默认 false = 进行正常的协议转换处理
     *
     *
     * @see ProxyServer 发送请求时检查
     */
    public function shouldPassthroughBody(): bool
    {
        return $this->getConfig('body_passthrough', false);
    }

    /**
     * 渠道支持的模型列表（所有）
     */
    public function channelModels(): HasMany
    {
        return $this->hasMany(ChannelModel::class, 'channel_id');
    }

    /**
     * 启用的模型列表
     */
    public function enabledModels(): HasMany
    {
        return $this->channelModels()->where('is_enabled', true);
    }

    /**
     * 默认模型（用于测试）
     */
    public function defaultModel(): ?ChannelModel
    {
        return $this->channelModels()->where('is_default', true)->first();
    }

    /**
     * 获取模型列表（兼容旧数据）
     *
     * 返回格式：['model_name' => 'display_name']
     *
     * @return array<string, string>
     */
    public function getModelsArray(): array
    {
        $models = $this->enabledModels()->get();
        if ($models->isNotEmpty()) {
            $result = [];
            foreach ($models as $model) {
                $result[$model->model_name] = $model->getDisplayName();
            }

            return $result;
        }

        return [];
    }

    /**
     * 获取模型映射配置
     *
     * 仅返回配置了 mapped_model 的模型
     * 返回格式：['request_model' => 'actual_model']
     *
     * @return array<string, string>
     */
    public function getModelMappingsArray(): array
    {
        $models = $this->enabledModels()->get();
        $result = [];
        foreach ($models as $model) {
            if ($model->mapped_model) {
                $result[$model->model_name] = $model->mapped_model;
            }
        }

        return $result;
    }

    /**
     * 获取默认模型名称（兼容旧数据）
     */
    public function getDefaultModelName(): ?string
    {
        $defaultModel = $this->defaultModel();
        if ($defaultModel) {
            return $defaultModel->model_name;
        }

        return null;
    }

    /**
     * 允许的 User-Agent 规则列表
     */
    public function allowedUserAgents(): BelongsToMany
    {
        return $this->belongsToMany(UserAgent::class, 'channel_user_agent', 'channel_id', 'user_agent_id')
            ->withTimestamps()
            ->where('is_enabled', true); // 只关联启用的规则
    }

    /**
     * 检查是否有 User-Agent 限制
     */
    public function hasUserAgentRestriction(): bool
    {
        return (bool) $this->has_user_agent_restriction;
    }

    /**
     * 检查请求的 User-Agent 是否被允许
     *
     * 判断逻辑：
     * 1. 如果没有限制（has_user_agent_restriction=false）→ 允许所有
     * 2. 如果有限制但未配置任何规则 → 拒绝访问
     * 3. 匹配任意一条规则 → 允许访问并记录命中
     * 4. 未匹配任何规则 → 拒绝访问
     *
     * @param  string  $userAgent  请求的 User-Agent
     * @return bool true=允许, false=不允许
     *
     * @see ProxyServer::handle() 请求处理时检查
     */
    public function isUserAgentAllowed(string $userAgent): bool
    {
        // 如果没有限制，允许所有User-Agent
        if (! $this->hasUserAgentRestriction()) {
            return true;
        }

        // 获取关联的User-Agent规则
        $allowedPatterns = $this->allowedUserAgents;

        // 如果有限制但未配置任何规则，拒绝访问
        if ($allowedPatterns->isEmpty()) {
            return false;
        }

        // 检查是否匹配任意一条规则
        foreach ($allowedPatterns as $pattern) {
            if ($pattern->matches($userAgent)) {
                $pattern->recordHit(); // 记录命中

                return true;
            }
        }

        return false; // 没有任何规则匹配
    }

    /* ==================== 继承便捷方法 ==================== */

    /**
     * 获取继承链
     *
     * 从当前渠道开始，逐级向上遍历父渠道，返回从根到当前的完整继承链。
     *
     * @return array<Channel> 继承链数组（从根到当前）
     *
     * @see ChannelInheritanceResolver::getInheritanceChain()
     */
    public function getInheritanceChain(): array
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->getInheritanceChain($this);
    }

    /**
     * 获取解析后的完整配置
     *
     * 根据继承关系合并所有父渠道配置，返回最终生效的配置数组。
     *
     * @return array 最终配置数组，包含 base_url, api_key, provider, config, forward_headers, channel_models
     *
     * @see ChannelInheritanceResolver::resolveConfig()
     */
    public function getResolvedConfig(): array
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->resolveConfig($this);
    }

    /**
     * 获取解析后的 base_url
     *
     * 子渠道值为空时继承父渠道值。
     *
     * @return string|null 最终生效的 base_url
     */
    public function getEffectiveBaseUrl(): ?string
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->resolveScalar($this, 'base_url');
    }

    /**
     * 获取解析后的 api_key
     *
     * 子渠道值为空时继承父渠道值。
     *
     * @return string|null 最终生效的 api_key
     */
    public function getEffectiveApiKey(): ?string
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->resolveScalar($this, 'api_key');
    }

    /**
     * 获取解析后的 provider
     *
     * 子渠道值为空时继承父渠道值。
     *
     * @return string|null 最终生效的 provider
     */
    public function getEffectiveProvider(): ?string
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->resolveScalar($this, 'provider');
    }

    /**
     * 获取解析后的 forward_headers
     *
     * 根据继承模式合并父渠道和子渠道的转发请求头配置。
     *
     * @return array 最终生效的 forward_headers
     */
    public function getEffectiveForwardHeaders(): array
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->resolveArray($this, 'forward_headers');
    }

    /**
     * 获取解析后的 config 中的指定键值
     *
     * 从合并后的 config 数组中读取指定键的值。
     *
     * @param  string  $key  配置键名
     * @param  mixed  $default  默认值
     * @return mixed 最终生效的配置值
     */
    public function getEffectiveConfig(string $key, mixed $default = null): mixed
    {
        $config = $this->getEffectiveResolvedConfig();

        return $config[$key] ?? $default;
    }

    /**
     * 获取解析后的完整 config 数组
     *
     * @return array 最终生效的 config 数组
     */
    protected function getEffectiveResolvedConfig(): array
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->resolveArray($this, 'config');
    }

    /**
     * 获取解析后的模型列表
     *
     * 根据继承模式合并父子渠道的模型列表。
     *
     * @return array<array{model_name: string, display_name: string|null, is_enabled: bool, is_default: bool, mapped_model: string|null}> 模型列表
     */
    public function getEffectiveChannelModels(): array
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->resolveChannelModels($this);
    }

    /**
     * 检查是否有父渠道
     *
     * @return bool 是否存在父渠道
     */
    public function hasParent(): bool
    {
        return $this->parent_id !== null && $this->parent_id !== 0;
    }

    /**
     * 检查是否存在循环继承
     *
     * 用于表单验证和调试场景，不抛出异常。
     *
     * @return bool 是否存在循环继承
     *
     * @see ChannelInheritanceResolver::hasCircularInheritance()
     */
    public function hasCircularInheritance(): bool
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->hasCircularInheritance($this);
    }

    /**
     * 获取继承深度
     *
     * 返回渠道在继承树中的深度（根渠道深度为 0）。
     *
     * @return int 继承深度
     *
     * @see ChannelInheritanceResolver::getInheritanceDepth()
     */
    public function getInheritanceDepth(): int
    {
        /** @var ChannelInheritanceResolver $resolver */
        $resolver = app(ChannelInheritanceResolver::class);

        return $resolver->getInheritanceDepth($this);
    }
}
