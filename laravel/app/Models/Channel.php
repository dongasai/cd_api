<?php

namespace App\Models;

use App\Enums\ChannelHealthStatus;
use App\Enums\ChannelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $coding_account_id
 * @property ChannelStatus $status 运营状态
 * @property ChannelHealthStatus $status2 健康状态
 * @property string|null $status2_remark 健康状态备注
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
            'config' => 'array',
            'forward_headers' => 'array',
            'last_check_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'last_success_at' => 'datetime',
            'total_cost' => 'decimal:6',
            'success_rate' => 'decimal:4',
            'has_user_agent_restriction' => 'boolean',
        ];
    }

    /**
     * 父渠道
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'parent_id');
    }

    /**
     * 子渠道
     */
    public function children(): HasMany
    {
        return $this->hasMany(Channel::class, 'parent_id');
    }

    /**
     * 所属分组
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ChannelGroup::class, 'channel_group_pivot', 'channel_id', 'group_id')
            ->withPivot('priority')
            ->withTimestamps();
    }

    /**
     * 所属标签
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ChannelTag::class, 'channel_tag_pivot', 'channel_id', 'tag_id');
    }

    /**
     * 获取 API Key 的脱敏显示
     */
    public function getMaskedApiKey(): string
    {
        if (empty($this->api_key_hash)) {
            return '未设置';
        }

        return 'sk-...'.$this->api_key_hash;
    }

    /**
     * 检查渠道是否可用
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
        return $this->status2 === 'normal';
    }

    /**
     * 检查渠道是否可以参与选择
     *
     * 同时满足：运营状态为active 且 健康状态为normal
     */
    public function isAvailableForSelection(): bool
    {
        return $this->isActive() && $this->isHealthNormal();
    }

    /**
     * 禁用渠道健康状态
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
     */
    public function enableHealth(): void
    {
        $this->update([
            'status2' => ChannelHealthStatus::NORMAL,
            'status2_remark' => null,
        ]);
    }

    /**
     * Coding账户
     */
    public function codingAccount(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'coding_account_id');
    }

    /**
     * 检查是否绑定Coding账户
     */
    public function hasCodingAccount(): bool
    {
        return $this->coding_account_id !== null;
    }

    /**
     * 获取需要转发的header名称列表
     *
     * @return array header名称列表，支持通配符如 'x-*'
     */
    public function getForwardHeaderNames(): array
    {
        return $this->forward_headers ?? [];
    }

    /**
     * 获取配置项
     *
     * @return mixed 配置值，如果不存在返回默认值
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $config = $this->config ?? [];

        return $config[$key] ?? $default;
    }

    /**
     * 是否过滤 thinking 内容块
     *
     * 默认返回 false，即不过滤 thinking 块（保留 thinking 内容）
     */
    public function shouldFilterThinking(): bool
    {
        return $this->getConfig('filter_thinking', false);
    }

    /**
     * 是否过滤请求中的 thinking 内容块
     *
     * 默认返回 false，即不过滤请求中的 thinking 块（保留 thinking 内容）
     */
    public function shouldFilterRequestThinking(): bool
    {
        return $this->getConfig('filter_request_thinking', false);
    }

    /**
     * 是否透传请求体（body passthrough）
     *
     * 开启后，来自客户端的 body 将不进行任何处理直接发送给上游渠道
     * 默认返回 false，即进行正常的协议转换处理
     */
    public function shouldPassthroughBody(): bool
    {
        return $this->getConfig('body_passthrough', false);
    }

    /**
     * 渠道支持的模型列表
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
     * 默认模型
     */
    public function defaultModel(): ?ChannelModel
    {
        return $this->channelModels()->where('is_default', true)->first();
    }

    /**
     * 获取模型列表（兼容旧数据）
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
     * 获取模型映射
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
     * 允许的User-Agent规则列表
     */
    public function allowedUserAgents(): BelongsToMany
    {
        return $this->belongsToMany(UserAgent::class, 'channel_user_agent', 'channel_id', 'user_agent_id')
            ->withTimestamps()
            ->where('is_enabled', true); // 只关联启用的规则
    }

    /**
     * 检查是否有User-Agent限制
     */
    public function hasUserAgentRestriction(): bool
    {
        return (bool) $this->has_user_agent_restriction;
    }

    /**
     * 检查请求的User-Agent是否被允许
     *
     * @param  string  $userAgent  请求的User-Agent
     * @return bool true=允许, false=不允许
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
}
