<?php

namespace App\Models;

use App\Enums\ChannelHealthStatus;
use App\Enums\ChannelStatus;
use App\Http\Middleware\AuthenticateApiKey;
use App\Services\Router\ChannelRouterService;
use App\Services\Router\ProxyServer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * API Key 模型
 *
 * 管理 API 密钥配置和权限控制，提供模型映射、渠道访问控制等功能。
 *
 * 数据表结构 (api_keys):
 * ┌──────────────────────────┬─────────────┬─────────────────────────────────────────────────┐
 * │ 字段名                    │ 类型        │ 说明                                             │
 * ├──────────────────────────┼─────────────┼─────────────────────────────────────────────────┤
 * │ id                       │ bigint      │ 主键，自增                                        │
 * │ name                     │ varchar(255)│ API 密钥名称                                      │
 * │ key                      │ varchar(100)│ API 密钥（明文）                                   │
 * │ permissions              │ json        │ 权限配置（暂未使用）                                │
 * │ allowed_models           │ json        │ 允许访问的模型列表                                 │
 * │ model_mappings           │ json        │ 模型映射配置（Key级别别名映射）                      │
 * │ allowed_channels         │ json        │ 允许的渠道ID列表（白名单）                          │
 * │ not_allowed_channels     │ json        │ 禁止的渠道ID列表（黑名单）                          │
 * │ allowed_groups           │ json        │ 允许的分组标识列表（白名单）                         │
 * │ not_allowed_groups       │ json        │ 禁止的分组标识列表（黑名单）                         │
 * │ allowed_tags             │ json        │ 允许的标签名称列表（白名单）                         │
 * │ not_allowed_tags         │ json        │ 禁止的标签名称列表（黑名单）                         │
 * │ rate_limit               │ json        │ 速率限制配置                                      │
 * │ expires_at               │ timestamp   │ 过期时间                                          │
 * │ last_used_at             │ timestamp   │ 最后使用时间                                       │
 * │ status                   │ enum        │ 状态：active/revoked/expired                      │
 * │ created_at               │ timestamp   │ 创建时间                                          │
 * │ updated_at               │ timestamp   │ 更新时间                                          │
 * │ deleted_at               │ timestamp   │ 软删除时间                                        │
 * └──────────────────────────┴─────────────┴─────────────────────────────────────────────────┘
 *
 * 迁移历史：
 * - 2026_03_07_000001: 初始创建表（含 key_hash, key_prefix）
 * - 2026_03_07_100009: 添加 key 字段（明文存储）
 * - 2026_03_09_064759: 添加渠道访问控制字段
 * - 2026_03_09_100001: 添加模型映射字段
 * - 2026_03_16_000001: 移除 key_hash 字段
 * - 2026_03_16_000002: 移除 key_prefix 字段
 * - 2026_07_29_000001: 添加分组/标签访问控制字段
 *
 * 核心功能：
 * 1. 模型映射：将请求的模型名称映射到实际模型（resolveModel）
 * 2. 渠道控制：白名单/黑名单机制限制 Key 可访问的渠道
 * 3. 状态管理：检查 Key 是否有效（状态+过期时间）
 *
 * @property int $id 主键
 * @property string $name API 密钥名称
 * @property string|null $key API 密钥（明文）
 * @property array|null $permissions 权限配置
 * @property array|null $allowed_models 允许的模型列表
 * @property array|null $model_mappings 模型映射配置
 * @property array|null $allowed_channels 允许的渠道ID列表（白名单）
 * @property array|null $not_allowed_channels 禁止的渠道ID列表（黑名单）
 * @property array|null $allowed_groups 允许的分组标识列表（白名单）
 * @property array|null $not_allowed_groups 禁止的分组标识列表（黑名单）
 * @property array|null $allowed_tags 允许的标签名称列表（白名单）
 * @property array|null $not_allowed_tags 禁止的标签名称列表（黑名单）
 * @property array|null $rate_limit 速率限制配置
 * @property Carbon|null $expires_at 过期时间
 * @property Carbon|null $last_used_at 最后使用时间
 * @property string $status 状态：active/revoked/expired
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 *
 * @see ProxyServer API 代理核心服务
 * @see AuthenticateApiKey 认证中间件
 */
class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'allowed_models',
        'model_mappings',
        'allowed_channels',
        'not_allowed_channels',
        'allowed_groups',
        'not_allowed_groups',
        'allowed_tags',
        'not_allowed_tags',
        'rate_limit',
        'expires_at',
        'last_used_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'allowed_models' => 'array',
            'model_mappings' => 'array',
            'allowed_channels' => 'array',
            'not_allowed_channels' => 'array',
            'allowed_groups' => 'array',
            'not_allowed_groups' => 'array',
            'allowed_tags' => 'array',
            'not_allowed_tags' => 'array',
            'rate_limit' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * 检查 Key 是否有效
     *
     * 判断标准：
     * 1. 状态必须为 active
     * 2. 未过期（expires_at 为空或未到过期时间）
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * 检查 Key 是否已过期
     */
    public function isExpired(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * 获取脱敏后的 Key（仅显示前10位）
     */
    public function getMaskedKey(): string
    {
        if (empty($this->key)) {
            return '未设置';
        }

        return substr($this->key, 0, 10).'...';
    }

    /**
     * 获取模型映射配置
     */
    public function getModelMappings(): array
    {
        return $this->model_mappings ?? [];
    }

    /**
     * 解析模型名称（核心功能）
     *
     * 实现模型别名映射：如果请求的模型在 model_mappings 中有映射，
     * 则返回映射后的实际模型名称，否则返回原模型名称。
     *
     * 示例：
     * - model_mappings: ['cd-coding-latest' => 'gpt-4-turbo']
     * - 请求模型: 'cd-coding-latest'
     * - 返回: 'gpt-4-turbo'
     *
     * @param  string  $model  原始模型名称
     * @return string 映射后的模型名称
     *
     * @see ProxyServer::handle() 使用此方法进行模型映射
     */
    public function resolveModel(string $model): string
    {
        $mappings = $this->getModelMappings();

        return $mappings[$model] ?? $model;
    }

    /**
     * 获取允许的渠道ID列表（白名单）
     *
     * @return int[]
     */
    public function getAllowedChannelIds(): array
    {
        $ids = $this->allowed_channels ?? [];

        return array_map('intval', $ids);
    }

    /**
     * 获取禁止的渠道ID列表（黑名单）
     *
     * @return int[]
     */
    public function getNotAllowedChannelIds(): array
    {
        $ids = $this->not_allowed_channels ?? [];

        return array_map('intval', $ids);
    }

    /**
     * 检查是否配置了渠道白名单
     */
    public function hasChannelWhitelist(): bool
    {
        return ! empty($this->allowed_channels);
    }

    /**
     * 检查是否配置了渠道黑名单
     */
    public function hasChannelBlacklist(): bool
    {
        return ! empty($this->not_allowed_channels);
    }

    /**
     * 检查是否配置了渠道访问限制
     */
    public function hasChannelRestriction(): bool
    {
        return $this->hasChannelWhitelist() || $this->hasChannelBlacklist();
    }

    /**
     * 检查指定渠道是否允许访问
     *
     * 判断逻辑：
     * 1. 如果配置了黑名单且渠道在黑名单中 → 禁止访问
     * 2. 如果配置了白名单且渠道在白名单中 → 允许访问
     * 3. 如果配置了白名单但渠道不在白名单中 → 禁止访问
     * 4. 如果没有任何限制 → 允许访问
     *
     * @param  int  $channelId  渠道ID
     *
     * @see ChannelRouterService::filterChannelsByApiKey() 使用此方法过滤渠道
     */
    public function isChannelAllowed(int $channelId): bool
    {
        if ($this->hasChannelBlacklist()) {
            if (in_array($channelId, $this->getNotAllowedChannelIds(), true)) {
                return false;
            }
        }

        if ($this->hasChannelWhitelist()) {
            return in_array($channelId, $this->getAllowedChannelIds(), true);
        }

        return true;
    }

    /**
     * 根据渠道 Slug 检查是否允许访问
     *
     * @param  string  $channelSlug  渠道标识
     */
    public function isChannelAllowedBySlug(string $channelSlug): bool
    {
        if (! $this->hasChannelRestriction()) {
            return true;
        }

        $channel = Channel::where('slug', $channelSlug)->first();

        if (! $channel) {
            return false;
        }

        return $this->isChannelAllowed($channel->id);
    }

    /**
     * 获取此 Key 可访问的活跃渠道列表
     *
     * 查询逻辑：
     * 1. 仅返回状态为 active 的渠道
     * 2. 应用黑名单过滤（排除黑名单中的渠道）
     * 3. 应用白名单过滤（仅保留白名单中的渠道）
     * 4. 应用分组过滤
     * 5. 应用标签过滤
     *
     * @return Collection
     */
    public function getAllowedChannels()
    {
        $query = Channel::query()
            ->where('status', ChannelStatus::ACTIVE)
            ->where('status2', ChannelHealthStatus::NORMAL);

        // 渠道 ID 过滤
        if ($this->hasChannelBlacklist()) {
            $query->whereNotIn('id', $this->getNotAllowedChannelIds());
        }

        if ($this->hasChannelWhitelist()) {
            $query->whereIn('id', $this->getAllowedChannelIds());
        }

        // 分组过滤
        if ($this->hasGroupBlacklist()) {
            $query->whereDoesntHave('groups', fn($q) => $q->whereIn('slug', $this->getNotAllowedGroupSlugs()));
        }

        if ($this->hasGroupWhitelist()) {
            $query->whereHas('groups', fn($q) => $q->whereIn('slug', $this->getAllowedGroupSlugs()));
        }

        // 标签过滤
        if ($this->hasTagBlacklist()) {
            $query->whereDoesntHave('tags', fn($q) => $q->whereIn('name', $this->getNotAllowedTagNames()));
        }

        if ($this->hasTagWhitelist()) {
            $query->whereHas('tags', fn($q) => $q->whereIn('name', $this->getAllowedTagNames()));
        }

        return $query->get();
    }

    // ─── 分组访问控制 ────────────────────────────────────────────────

    /**
     * 获取允许的分组标识列表（白名单）
     *
     * @return string[]
     */
    public function getAllowedGroupSlugs(): array
    {
        $slugs = $this->allowed_groups ?? [];

        return array_map('strval', $slugs);
    }

    /**
     * 获取禁止的分组标识列表（黑名单）
     *
     * @return string[]
     */
    public function getNotAllowedGroupSlugs(): array
    {
        $slugs = $this->not_allowed_groups ?? [];

        return array_map('strval', $slugs);
    }

    /**
     * 检查是否配置了分组白名单
     */
    public function hasGroupWhitelist(): bool
    {
        return ! empty($this->allowed_groups);
    }

    /**
     * 检查是否配置了分组黑名单
     */
    public function hasGroupBlacklist(): bool
    {
        return ! empty($this->not_allowed_groups);
    }

    /**
     * 检查是否配置了分组访问限制
     */
    public function hasGroupRestriction(): bool
    {
        return $this->hasGroupWhitelist() || $this->hasGroupBlacklist();
    }

    /**
     * 检查指定分组是否允许访问
     *
     * 判断逻辑：
     * 1. 如果配置了黑名单且分组在黑名单中 → 禁止访问
     * 2. 如果配置了白名单且分组在白名单中 → 允许访问
     * 3. 如果配置了白名单但分组不在白名单中 → 禁止访问
     * 4. 如果没有任何限制 → 允许访问
     *
     * @param  string  $slug  分组标识
     */
    public function isGroupAllowed(string $slug): bool
    {
        if ($this->hasGroupBlacklist()) {
            if (in_array($slug, $this->getNotAllowedGroupSlugs(), true)) {
                return false;
            }
        }

        if ($this->hasGroupWhitelist()) {
            return in_array($slug, $this->getAllowedGroupSlugs(), true);
        }

        return true;
    }

    // ─── 标签访问控制 ────────────────────────────────────────────────

    /**
     * 获取允许的标签名称列表（白名单）
     *
     * @return string[]
     */
    public function getAllowedTagNames(): array
    {
        $names = $this->allowed_tags ?? [];

        return array_map('strval', $names);
    }

    /**
     * 获取禁止的标签名称列表（黑名单）
     *
     * @return string[]
     */
    public function getNotAllowedTagNames(): array
    {
        $names = $this->not_allowed_tags ?? [];

        return array_map('strval', $names);
    }

    /**
     * 检查是否配置了标签白名单
     */
    public function hasTagWhitelist(): bool
    {
        return ! empty($this->allowed_tags);
    }

    /**
     * 检查是否配置了标签黑名单
     */
    public function hasTagBlacklist(): bool
    {
        return ! empty($this->not_allowed_tags);
    }

    /**
     * 检查是否配置了标签访问限制
     */
    public function hasTagRestriction(): bool
    {
        return $this->hasTagWhitelist() || $this->hasTagBlacklist();
    }

    /**
     * 检查指定标签是否允许访问
     *
     * 判断逻辑同分组：黑名单优先，白名单次之，无限制则放行
     *
     * @param  string  $name  标签名称
     */
    public function isTagAllowed(string $name): bool
    {
        if ($this->hasTagBlacklist()) {
            if (in_array($name, $this->getNotAllowedTagNames(), true)) {
                return false;
            }
        }

        if ($this->hasTagWhitelist()) {
            return in_array($name, $this->getAllowedTagNames(), true);
        }

        return true;
    }

    // ─── 综合限制检查 ────────────────────────────────────────────────

    /**
     * 综合检查渠道 ID + 分组 + 标签是否允许
     *
     * 用于 ChannelAffinityService 等需要完整检查的场景
     */
    public function isChannelAllowedWithGroupsAndTags(Channel $channel): bool
    {
        // 1. 渠道 ID 检查
        if (! $this->isChannelAllowed($channel->id)) {
            return false;
        }

        // 2. 分组检查
        if (! $this->isGroupAllowedForChannel($channel)) {
            return false;
        }

        // 3. 标签检查
        if (! $this->isTagAllowedForChannel($channel)) {
            return false;
        }

        return true;
    }

    /**
     * 检查渠道的分组是否允许
     */
    protected function isGroupAllowedForChannel(Channel $channel): bool
    {
        // 黑名单优先
        if ($this->hasGroupBlacklist()) {
            $notAllowedSlugs = $this->getNotAllowedGroupSlugs();
            $channelSlugs = $channel->groups->pluck('slug')->toArray();
            if (! empty(array_intersect($channelSlugs, $notAllowedSlugs))) {
                return false;
            }
        }

        // 白名单检查
        if ($this->hasGroupWhitelist()) {
            $allowedSlugs = $this->getAllowedGroupSlugs();
            $channelSlugs = $channel->groups->pluck('slug')->toArray();
            if (empty(array_intersect($channelSlugs, $allowedSlugs))) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查渠道的标签是否允许
     */
    protected function isTagAllowedForChannel(Channel $channel): bool
    {
        // 黑名单优先
        if ($this->hasTagBlacklist()) {
            $notAllowedNames = $this->getNotAllowedTagNames();
            $channelNames = $channel->tags->pluck('name')->toArray();
            if (! empty(array_intersect($channelNames, $notAllowedNames))) {
                return false;
            }
        }

        // 白名单检查
        if ($this->hasTagWhitelist()) {
            $allowedNames = $this->getAllowedTagNames();
            $channelNames = $channel->tags->pluck('name')->toArray();
            if (empty(array_intersect($channelNames, $allowedNames))) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查是否配置了任意访问限制（渠道+分组+标签）
     */
    public function hasAnyRestriction(): bool
    {
        return $this->hasChannelRestriction()
            || $this->hasGroupRestriction()
            || $this->hasTagRestriction();
    }
}
