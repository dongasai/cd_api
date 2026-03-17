<?php

namespace App\Services\Router;

use App\Models\ApiKey;
use App\Models\Channel;
use App\Services\ChannelAffinity\ChannelAffinityService;
use Illuminate\Support\Facades\Log;

/**
 * 渠道选择服务
 */
class ChannelSelector
{
    protected ChannelRouterService $channelRouter;

    protected ChannelAffinityService $affinityService;

    protected array $failedChannels = [];

    public function __construct(
        ChannelRouterService $channelRouter,
        ChannelAffinityService $affinityService
    ) {
        $this->channelRouter = $channelRouter;
        $this->affinityService = $affinityService;
    }

    /**
     * 选择渠道（支持故障转移）
     */
    public function select(string $model, ?ApiKey $apiKey, ?string $group, array $failedChannels = [], string $sourceProtocol = 'openai'): ?Channel
    {
        $this->failedChannels = $failedChannels;

        // 优先使用亲和性路由
        if (empty($this->failedChannels)) {
            $affinityResult = $this->affinityService->getPreferredChannel(
                request(),
                $model,
                $group
            );

            if ($affinityResult->isHit && $affinityResult->channel) {
                // 检查亲和性渠道是否符合透传协议匹配
                if ($affinityResult->channel->shouldPassthroughBody()) {
                    $channelProtocol = $this->getChannelProtocol($affinityResult->channel);
                    if ($channelProtocol !== $sourceProtocol) {
                        Log::warning('Affinity channel excluded due to passthrough protocol mismatch', [
                            'channel_id' => $affinityResult->channel->id,
                            'channel_name' => $affinityResult->channel->name,
                            'channel_protocol' => $channelProtocol,
                            'source_protocol' => $sourceProtocol,
                            'rule_id' => $affinityResult->rule?->id,
                        ]);
                    } else {
                        Log::info('Using affinity channel', [
                            'channel_id' => $affinityResult->channel->id,
                            'rule_id' => $affinityResult->rule?->id,
                            'key_hash' => $affinityResult->keyHash,
                        ]);

                        return $affinityResult->channel;
                    }
                } else {
                    Log::info('Using affinity channel', [
                        'channel_id' => $affinityResult->channel->id,
                        'rule_id' => $affinityResult->rule?->id,
                        'key_hash' => $affinityResult->keyHash,
                    ]);

                    return $affinityResult->channel;
                }
            }
        }

        return $this->channelRouter->selectChannel($model, [
            'api_key' => $apiKey,
            'exclude_channels' => $this->failedChannels,
            'source_protocol' => $sourceProtocol,
        ]);
    }

    /**
     * 获取渠道的协议类型
     */
    protected function getChannelProtocol(Channel $channel): string
    {
        $provider = $channel->provider;

        if (in_array($provider, ['anthropic', 'claude'])) {
            return 'anthropic';
        }

        return 'openai';
    }

    /**
     * 标记渠道失败
     */
    public function markFailed(Channel $channel, string $error): void
    {
        $this->failedChannels[] = $channel->id;
        $this->channelRouter->markChannelFailed($channel, $error);
    }

    /**
     * 获取失败的渠道列表
     */
    public function getFailedChannels(): array
    {
        return $this->failedChannels;
    }

    /**
     * 重置失败渠道列表
     */
    public function reset(): void
    {
        $this->failedChannels = [];
    }
}
