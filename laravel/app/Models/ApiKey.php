<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'key_hash',
        'key_prefix',
        'permissions',
        'allowed_models',
        'model_mappings',
        'allowed_channels',
        'not_allowed_channels',
        'rate_limit',
        'expires_at',
        'last_used_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'allowed_models' => 'array',
            'model_mappings' => 'array',
            'allowed_channels' => 'array',
            'not_allowed_channels' => 'array',
            'rate_limit' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

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

    public function isExpired(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    public function getMaskedKey(): string
    {
        if (empty($this->key_prefix)) {
            return '未设置';
        }

        return $this->key_prefix.'...';
    }

    /**
     * 获取模型映射配置
     */
    public function getModelMappings(): array
    {
        return $this->model_mappings ?? [];
    }

    /**
     * 解析模型名称，如果映射存在则返回映射后的模型
     *
     * @param  string  $model  原始模型名称
     * @return string 映射后的模型名称
     */
    public function resolveModel(string $model): string
    {
        $mappings = $this->getModelMappings();

        return $mappings[$model] ?? $model;
    }

    public function allowedChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'api_key_channel', 'api_key_id', 'channel_id')
            ->withTimestamps();
    }

    public function getAllowedChannelIds(): array
    {
        return $this->allowed_channels ?? [];
    }

    public function getNotAllowedChannelIds(): array
    {
        return $this->not_allowed_channels ?? [];
    }

    public function hasChannelWhitelist(): bool
    {
        return ! empty($this->allowed_channels);
    }

    public function hasChannelBlacklist(): bool
    {
        return ! empty($this->not_allowed_channels);
    }

    public function hasChannelRestriction(): bool
    {
        return $this->hasChannelWhitelist() || $this->hasChannelBlacklist();
    }

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

    public function getAllowedChannels()
    {
        $query = Channel::where('status', 'active');

        if ($this->hasChannelBlacklist()) {
            $query->whereNotIn('id', $this->getNotAllowedChannelIds());
        }

        if ($this->hasChannelWhitelist()) {
            $query->whereIn('id', $this->getAllowedChannelIds());
        }

        return $query->get();
    }
}
