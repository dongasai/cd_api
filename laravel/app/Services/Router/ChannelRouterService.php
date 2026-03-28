<?php

namespace App\Services\Router;

use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\ChannelGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 渠道路由服务
 *
 * 负责根据模型选择合适的渠道，实现负载均衡和故障转移
 */
class ChannelRouterService
{
    /**
     * 服务配置
     */
    protected array $config;

    /**
     * 构造函数
     *
     * @param  array  $config  服务配置
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'cache_ttl' => 60,
            'max_retry' => 3,
            'enable_failover' => true,
        ], $config);
    }

    /**
     * 为指定模型选择渠道
     *
     * @param  string  $model  模型名称
     * @param  array  $context  上下文信息（可包含负载均衡算法、API Key、源协议等）
     * @return Channel 选中的渠道
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 当没有可用渠道时
     */
    public function selectChannel(string $model, array $context = []): Channel
    {
        // 获取所有关联的模型名称（模型名 + 别名）
        $modelNames = $this->getModelNamesWithAliases($model);

        // 使用所有名称搜索渠道
        $channels = $this->getAvailableChannelsForModels($modelNames);

        // 模型不存在：没有配置支持该模型的渠道
        if ($channels->isEmpty()) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException("Model '{$model}' is not available");
        }

        // 应用 API Key 的渠道限制
        $apiKey = $context['api_key'] ?? null;
        $channelsAfterKeyRestriction = $this->applyApiKeyChannelRestrictions($channels, $apiKey);

        // API Key 限制导致没有可用渠道
        if ($channelsAfterKeyRestriction->isEmpty()) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("No available channel for model '{$model}' with current API key restrictions");
        }

        // 应用透传协议匹配过滤
        $sourceProtocol = $context['source_protocol'] ?? 'openai';
        $channelsAfterProtocol = $this->applyPassthroughProtocolFilter($channelsAfterKeyRestriction, $sourceProtocol);

        // 协议不匹配导致没有可用渠道
        if ($channelsAfterProtocol->isEmpty()) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("No available channel for model '{$model}' with protocol '{$sourceProtocol}'");
        }

        // 应用User-Agent过滤
        $userAgent = $context['user_agent'] ?? request()->header('User-Agent', '');
        $channelsAfterUserAgent = $this->applyUserAgentFilter($channelsAfterProtocol, $userAgent);

        // User-Agent不匹配导致没有可用渠道
        if ($channelsAfterUserAgent->isEmpty()) {
            Log::warning('All candidate channels filtered by User-Agent restriction', [
                'model' => $model,
                'user_agent' => $userAgent,
                'candidate_count' => $channelsAfterProtocol->count(),
            ]);
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("No available channel for model '{$model}' with current User-Agent");
        }

        // 排除已失败的渠道
        $excludeChannels = $context['exclude_channels'] ?? [];
        if (! empty($excludeChannels)) {
            $channelsAfterUserAgent = $channelsAfterUserAgent->reject(function ($channel) use ($excludeChannels) {
                return in_array($channel->id, $excludeChannels, true);
            });
        }

        // 所有渠道都失败了
        if ($channelsAfterUserAgent->isEmpty()) {
            throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException("All channels failed for model: {$model}");
        }

        $channel = $this->applyLoadBalancing($channelsAfterUserAgent, $context);

        Log::info('Channel selected', [
            'model' => $model,
            'resolved_models' => $modelNames,
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'provider' => $channel->provider,
            'api_key_id' => $apiKey?->id,
            'source_protocol' => $sourceProtocol,
            'is_passthrough' => $channel->shouldPassthroughBody(),
        ]);

        return $channel;
    }

    /**
     * 获取指定模型的可用渠道列表
     *
     * @param  string  $model  模型名称
     * @return \Illuminate\Database\Eloquent\Collection 可用渠道集合
     */
    public function getAvailableChannels(string $model): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "channels:available:{$model}";

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () use ($model) {
            $channelIds = $this->getChannelIdsForModel($model);

            if (empty($channelIds)) {
                return collect();
            }

            return Channel::whereIn('id', $channelIds)
                ->where('status', 'active')
                ->where('status2', 'normal') // 只选择健康状态正常的渠道
                ->orderBy('priority', 'desc')
                ->orderBy('weight', 'desc')
                ->get();
        });
    }

    /**
     * 获取模型名称及其所有别名
     *
     * @param  string  $model  原始模型名称
     * @return array 模型名称数组（包含原始名称和所有别名）
     */
    protected function getModelNamesWithAliases(string $model): array
    {
        return \App\Services\ModelService::resolveModelWithAliases($model);
    }

    /**
     * 为多个模型名称获取可用渠道
     *
     * @param  array  $modelNames  模型名称数组
     * @return \Illuminate\Database\Eloquent\Collection 可用渠道集合
     */
    protected function getAvailableChannelsForModels(array $modelNames): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($modelNames)) {
            return collect();
        }

        // 获取所有模型名称对应的渠道 ID
        $channelIds = [];
        foreach ($modelNames as $modelName) {
            $ids = $this->getChannelIdsForModel($modelName);
            $channelIds = array_merge($channelIds, $ids);
        }

        // 去重
        $channelIds = array_unique($channelIds);

        if (empty($channelIds)) {
            return collect();
        }

        // 查询渠道
        return Channel::whereIn('id', $channelIds)
            ->where('status', \App\Enums\ChannelStatus::ACTIVE)
            ->where('status2', 'normal')
            ->orderBy('priority', 'desc')
            ->orderBy('weight', 'desc')
            ->get();
    }

    /**
     * 应用 API Key 的渠道限制
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $channels  渠道集合
     * @param  ApiKey|null  $apiKey  API Key 实例
     * @return \Illuminate\Database\Eloquent\Collection 过滤后的渠道集合
     */
    protected function applyApiKeyChannelRestrictions(\Illuminate\Database\Eloquent\Collection $channels, ?ApiKey $apiKey): \Illuminate\Database\Eloquent\Collection
    {
        if (! $apiKey) {
            return $channels;
        }

        // 应用黑名单限制
        if ($apiKey->hasChannelBlacklist()) {
            $notAllowedChannels = array_map('intval', $apiKey->getNotAllowedChannelIds());
            $channels = $channels->reject(function ($channel) use ($notAllowedChannels) {
                return in_array($channel->id, $notAllowedChannels, true);
            });
        }

        // 应用白名单限制
        if ($apiKey->hasChannelWhitelist()) {
            $allowedChannels = array_map('intval', $apiKey->getAllowedChannelIds());
            $channels = $channels->filter(function ($channel) use ($allowedChannels) {
                return in_array($channel->id, $allowedChannels, true);
            });
        }

        return $channels;
    }

    /**
     * 应用透传协议匹配过滤
     *
     * 当渠道开启 body 透传时，只选择与源请求协议一致的渠道
     * 例如：Anthropic 渠道开启透传后，只能处理 Anthropic 格式的请求
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $channels  渠道集合
     * @param  string  $sourceProtocol  源请求协议（openai/anthropic）
     * @return \Illuminate\Database\Eloquent\Collection 过滤后的渠道集合
     */
    protected function applyPassthroughProtocolFilter(\Illuminate\Database\Eloquent\Collection $channels, string $sourceProtocol): \Illuminate\Database\Eloquent\Collection
    {
        return $channels->filter(function ($channel) use ($sourceProtocol) {
            // 如果渠道未开启透传，则允许选择（会进行协议转换）
            if (! $channel->shouldPassthroughBody()) {
                return true;
            }

            // 如果开启透传，则检查协议是否匹配
            $channelProtocol = $this->getChannelProtocol($channel);
            $isMatch = ($channelProtocol === $sourceProtocol);

            // 记录过滤日志
            if (! $isMatch) {
                Log::debug('Channel excluded due to passthrough protocol mismatch', [
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                    'channel_protocol' => $channelProtocol,
                    'source_protocol' => $sourceProtocol,
                ]);
            }

            return $isMatch;
        });
    }

    /**
     * 应用User-Agent过滤
     *
     * 根据渠道的User-Agent限制配置，过滤不匹配的渠道
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $channels  渠道集合
     * @param  string  $userAgent  请求的User-Agent
     * @return \Illuminate\Database\Eloquent\Collection 过滤后的渠道集合
     */
    protected function applyUserAgentFilter(\Illuminate\Database\Eloquent\Collection $channels, string $userAgent): \Illuminate\Database\Eloquent\Collection
    {
        // 如果User-Agent为空，不过滤
        if (empty($userAgent)) {
            return $channels;
        }

        $userAgentFilter = app(UserAgentFilterService::class);

        return $userAgentFilter->filterChannels($channels, $userAgent);
    }

    /**
     * 获取渠道的协议类型
     *
     * @param  Channel  $channel  渠道实例
     * @return string 协议类型（openai/anthropic）
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
     * 获取支持指定模型的渠道 ID 列表
     *
     * @param  string  $model  模型名称
     * @return int[] 渠道 ID 数组
     */
    protected function getChannelIdsForModel(string $model): array
    {
        return \App\Models\ChannelModel::where('model_name', $model)
            ->where('is_enabled', true)
            ->pluck('channel_id')
            ->toArray();
    }

    /**
     * 应用负载均衡算法选择渠道
     *
     * @param  mixed  $channels  渠道集合
     * @param  array  $context  上下文信息
     * @return Channel 选中的渠道
     */
    protected function applyLoadBalancing($channels, array $context = []): Channel
    {
        $algorithm = $context['lb_algorithm'] ?? 'weighted_round_robin';

        return match ($algorithm) {
            'least_connections' => $this->leastConnectionsBalance($channels),
            'response_time' => $this->responseTimeBalance($channels),
            default => $this->weightedRoundRobinBalance($channels),
        };
    }

    /**
     * 加权轮询负载均衡
     *
     * 根据渠道权重随机选择
     */
    protected function weightedRoundRobinBalance($channels): Channel
    {
        $totalWeight = $channels->sum('weight');
        $random = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($channels as $channel) {
            $currentWeight += $channel->weight;
            if ($random <= $currentWeight) {
                return $channel;
            }
        }

        return $channels->first();
    }

    /**
     * 最少连接数负载均衡
     *
     * 选择当前请求数最少的渠道
     */
    protected function leastConnectionsBalance($channels): Channel
    {
        return $channels->sortBy('total_requests')->first();
    }

    /**
     * 响应时间负载均衡
     *
     * 选择平均响应时间最短的渠道
     */
    protected function responseTimeBalance($channels): Channel
    {
        return $channels->sortBy('avg_latency_ms')->first();
    }

    /**
     * 获取故障转移渠道
     *
     * 当主渠道失败时，获取备选渠道
     *
     * @param  Channel  $failedChannel  失败的渠道
     * @param  string  $model  模型名称
     * @param  ApiKey|null  $apiKey  API Key 实例
     * @return Channel|null 备选渠道，无可用渠道时返回 null
     */
    public function getFallbackChannel(Channel $failedChannel, string $model, ?ApiKey $apiKey = null): ?Channel
    {
        if (! $this->config['enable_failover']) {
            return null;
        }

        $channels = $this->getAvailableChannels($model);

        // 应用 API Key 的渠道限制
        $channels = $this->applyApiKeyChannelRestrictions($channels, $apiKey);

        return $channels->where('id', '!=', $failedChannel->id)->first();
    }

    /**
     * 获取故障转移渠道（用于重试）
     *
     * 排除已失败的渠道列表，获取备选渠道
     *
     * @param  string  $model  模型名称
     * @param  array  $failedChannelIds  已失败的渠道 ID 列表
     * @param  ApiKey|null  $apiKey  API Key 实例
     * @return Channel|null 备选渠道，无可用渠道时返回 null
     */
    public function getFallbackChannelForRetry(string $model, array $failedChannelIds, ?ApiKey $apiKey = null): ?Channel
    {
        if (! $this->config['enable_failover']) {
            return null;
        }

        $channels = $this->getAvailableChannels($model);

        // 应用 API Key 的渠道限制
        $channels = $this->applyApiKeyChannelRestrictions($channels, $apiKey);

        return $channels->reject(function ($channel) use ($failedChannelIds) {
            return in_array($channel->id, $failedChannelIds, true);
        })->first();
    }

    /**
     * 根据分组获取渠道列表
     *
     * @param  string  $groupSlug  分组标识
     * @return \Illuminate\Database\Eloquent\Collection 渠道集合
     */
    public function getChannelsByGroup(string $groupSlug): \Illuminate\Database\Eloquent\Collection
    {
        $group = ChannelGroup::where('slug', $groupSlug)->first();

        if (! $group) {
            return collect();
        }

        return $group->channels()
            ->where('status', 'active')
            ->where('status2', 'normal') // 只选择健康状态正常的渠道
            ->orderByPivot('priority', 'desc')
            ->get();
    }

    /**
     * 根据标签获取渠道列表
     *
     * @param  string  $tagName  标签名称
     * @return \Illuminate\Database\Eloquent\Collection 渠道集合
     */
    public function getChannelsByTag(string $tagName): \Illuminate\Database\Eloquent\Collection
    {
        return Channel::whereHas('tags', function ($query) use ($tagName) {
            $query->where('name', $tagName);
        })
            ->where('status', 'active')
            ->where('status2', 'normal') // 只选择健康状态正常的渠道
            ->get();
    }

    /**
     * 解析模型名称
     *
     * 将别名映射到实际模型名称
     *
     * @param  string  $model  模型名称或别名
     * @param  Channel|null  $channel  渠道（可选）
     * @return string 实际模型名称
     */
    public function resolveModel(string $model, ?Channel $channel = null): string
    {
        if ($channel) {
            $channelModel = \App\Models\ChannelModel::where('channel_id', $channel->id)
                ->where('model_name', $model)
                ->where('is_enabled', true)
                ->first();

            if ($channelModel && $channelModel->mapped_model) {
                return $channelModel->mapped_model;
            }
        }

        return $model;
    }

    /**
     * 标记渠道失败
     *
     * @param  Channel  $channel  失败的渠道
     * @param  string  $reason  失败原因
     */
    public function markChannelFailed(Channel $channel, string $reason = ''): void
    {
        $channel->increment('failure_count');
        $channel->update([
            'last_failure_at' => now(),
        ]);

        Log::warning('Channel marked as failed', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'reason' => $reason,
            'failure_count' => $channel->failure_count,
        ]);
    }

    /**
     * 标记渠道成功
     *
     * @param  Channel  $channel  成功的渠道
     */
    public function markChannelSuccess(Channel $channel): void
    {
        $channel->increment('success_count');
        $channel->update([
            'last_success_at' => now(),
        ]);
    }

    /**
     * 获取渠道统计信息
     *
     * @param  Channel  $channel  渠道实例
     * @return array 统计信息数组
     */
    public function getChannelStats(Channel $channel): array
    {
        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'status' => $channel->status,
            'total_requests' => $channel->total_requests,
            'success_count' => $channel->success_count,
            'failure_count' => $channel->failure_count,
            'success_rate' => $channel->total_requests > 0
                ? round($channel->success_count / $channel->total_requests * 100, 2)
                : 0,
            'avg_latency_ms' => $channel->avg_latency_ms,
            'last_success_at' => $channel->last_success_at,
            'last_failure_at' => $channel->last_failure_at,
        ];
    }

    /**
     * 清除渠道缓存
     *
     * @param  string|null  $model  指定模型名称，为 null 时清除所有模型缓存
     */
    public function clearCache(?string $model = null): void
    {
        if ($model) {
            Cache::forget("channels:available:{$model}");
        } else {
            $models = \App\Models\ChannelModel::distinct()->pluck('model_name');
            foreach ($models as $model) {
                Cache::forget("channels:available:{$model}");
            }
        }
    }
}
