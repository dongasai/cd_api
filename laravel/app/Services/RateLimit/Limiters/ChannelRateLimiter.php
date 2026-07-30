<?php

namespace App\Services\RateLimit\Limiters;

use App\Models\Channel;
use App\Models\ChannelModel;
use App\Services\RateLimit\DTO\RateLimitContext;

/**
 * 渠道限流器
 *
 * 基于渠道的 RPM 限制进行滑动窗口限流
 * 支持渠道整体级别和模型级别的限流
 */
class ChannelRateLimiter extends RedisSlidingWindowLimiter
{
    protected Channel $channel;

    protected ?string $model;

    /**
     * 创建渠道限流器
     *
     * @param  Channel  $channel  渠道模型
     * @param  string|null  $model  模型名称（可选，为 null 表示整体级别）
     */
    public function __construct(Channel $channel, ?string $model = null)
    {
        $this->channel = $channel;
        $this->model = $model;

        $config = $this->getLimitConfig();
        $maxRequests = $config['maxRequests'] ?? 0;
        $windowSeconds = $config['windowSeconds'] ?? 60;

        $name = $model ? "channel_model_{$model}" : 'channel_global';

        parent::__construct(
            name: $name,
            maxRequests: $maxRequests,
            windowSeconds: $windowSeconds,
        );
    }

    /**
     * 获取限流配置
     *
     * 优先检查模型级别限制，回退到渠道整体级别
     *
     * @return array{maxRequests: int, windowSeconds: int}|null
     */
    protected function getLimitConfig(): ?array
    {
        // 1. 优先检查模型级别限制
        if ($this->model !== null) {
            $channelModel = ChannelModel::where('channel_id', $this->channel->id)
                ->where('model_name', $this->model)
                ->first();

            if ($channelModel && $channelModel->rpm_limit !== null && $channelModel->rpm_limit > 0) {
                return [
                    'maxRequests' => $channelModel->rpm_limit,
                    'windowSeconds' => 60, // RPM 固定为 60 秒窗口
                ];
            }
        }

        // 2. 回退到渠道整体级别限制
        $rpmLimit = $this->channel->rpm_limit ?? null;

        // 3. 如果渠道未设置，使用配置默认值
        if ($rpmLimit === null || $rpmLimit <= 0) {
            $rpmLimit = config('rate_limit.channel.default_rpm_limit', 1000);
        }

        if ($rpmLimit <= 0) {
            return null;
        }

        return [
            'maxRequests' => (int) $rpmLimit,
            'windowSeconds' => 60,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function buildKey(RateLimitContext $context): string
    {
        $modelPart = $this->model ? str_replace(['/', ':'], '_', $this->model) : 'global';

        return $this->buildFullKey("channel:{$this->channel->id}:{$modelPart}");
    }

    /**
     * {@inheritDoc}
     */
    protected function getDeniedReason(RateLimitContext $context): string
    {
        if ($this->model !== null) {
            return sprintf(
                '渠道 "%s" 模型 "%s" 达到 RPM 限制（最大 %d 请求/分钟）',
                $this->channel->name,
                $this->model,
                $this->maxRequests
            );
        }

        return sprintf(
            '渠道 "%s" 达到 RPM 限制（最大 %d 请求/分钟）',
            $this->channel->name,
            $this->maxRequests
        );
    }

    /**
     * {@inheritDoc}
     */
    public function recordUsage(RateLimitContext $context, array $usage): void
    {
        // 滑动窗口已在 check 时记录，此处仅记录额外信息
    }

    /**
     * 获取渠道模型级别的限流配置
     *
     * @param  string  $model  模型名称
     * @return array{maxRequests: int, windowSeconds: int}|null
     */
    public function getModelLimitConfig(string $model): ?array
    {
        $channelModel = ChannelModel::where('channel_id', $this->channel->id)
            ->where('model_name', $model)
            ->first();

        if ($channelModel && $channelModel->rpm_limit !== null && $channelModel->rpm_limit > 0) {
            return [
                'maxRequests' => $channelModel->rpm_limit,
                'windowSeconds' => 60,
            ];
        }

        return null;
    }

    /**
     * 检查是否为模型级别限流
     */
    public function isModelLevel(): bool
    {
        return $this->model !== null;
    }

    /**
     * 获取关联的渠道
     */
    public function getChannel(): Channel
    {
        return $this->channel;
    }

    /**
     * 获取限流的模型名称
     */
    public function getModel(): ?string
    {
        return $this->model;
    }
}
