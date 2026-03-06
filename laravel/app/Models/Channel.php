<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $coding_account_id
 * @property array|null $coding_status_override
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
        'models',
        'default_model',
        'weight',
        'priority',
        'status',
        'health_status',
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
        'coding_account_id',
        'coding_status_override',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'models' => 'array',
            'config' => 'array',
            'coding_status_override' => 'array',
            'last_check_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'last_success_at' => 'datetime',
            'total_cost' => 'decimal:6',
            'success_rate' => 'decimal:4',
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
        return $this->status === 'active';
    }

    /**
     * 检查渠道是否健康
     */
    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy';
    }

    /**
     * Coding账户
     */
    public function codingAccount(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'coding_account_id');
    }

    /**
     * 获取Coding状态覆盖配置
     */
    public function getCodingStatusOverride(): array
    {
        return $this->coding_status_override ?? [
            'auto_disable' => true,
            'auto_enable' => true,
            'disable_threshold' => 0.95,
            'warning_threshold' => 0.80,
            'priority' => 1,
            'fallback_channel_id' => null,
        ];
    }

    /**
     * 检查是否绑定Coding账户
     */
    public function hasCodingAccount(): bool
    {
        return $this->coding_account_id !== null;
    }

    /**
     * 检查是否允许自动禁用
     */
    public function allowsAutoDisable(): bool
    {
        $override = $this->getCodingStatusOverride();
        return $override['auto_disable'] ?? true;
    }

    /**
     * 检查是否允许自动启用
     */
    public function allowsAutoEnable(): bool
    {
        $override = $this->getCodingStatusOverride();
        return $override['auto_enable'] ?? true;
    }
}
