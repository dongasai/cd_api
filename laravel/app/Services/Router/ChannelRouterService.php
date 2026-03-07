<?php

namespace App\Services\Router;

use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\ModelMapping;
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
     * @param  array  $context  上下文信息（可包含负载均衡算法等）
     * @return Channel 选中的渠道
     *
     * @throws \RuntimeException 当没有可用渠道时
     */
    public function selectChannel(string $model, array $context = []): Channel
    {
        $channels = $this->getAvailableChannels($model);

        if ($channels->isEmpty()) {
            throw new \RuntimeException("No available channel for model: {$model}");
        }

        $channel = $this->applyLoadBalancing($channels, $context);

        Log::info('Channel selected', [
            'model' => $model,
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'provider' => $channel->provider,
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
                return Channel::where('status', 'active')
                    ->where('health_status', 'healthy')
                    ->whereNotNull('api_key')
                    ->orderBy('priority', 'desc')
                    ->orderBy('weight', 'desc')
                    ->get();
            }

            return Channel::whereIn('id', $channelIds)
                ->where('status', 'active')
                ->where('health_status', 'healthy')
                ->orderBy('priority', 'desc')
                ->orderBy('weight', 'desc')
                ->get();
        });
    }

    /**
     * 获取支持指定模型的渠道 ID 列表
     *
     * @param  string  $model  模型名称
     * @return int[] 渠道 ID 数组
     */
    protected function getChannelIdsForModel(string $model): array
    {
        // 查找指定了具体渠道的映射
        $specificMappings = ModelMapping::where('alias', $model)
            ->where('enabled', true)
            ->whereNotNull('channel_id')
            ->pluck('channel_id')
            ->toArray();

        if (! empty($specificMappings)) {
            return $specificMappings;
        }

        // 查找未指定渠道的映射，获取实际的模型名称
        $generalMapping = ModelMapping::where('alias', $model)
            ->where('enabled', true)
            ->whereNull('channel_id')
            ->first();

        $actualModel = $generalMapping?->actual_model ?? $model;

        // 使用实际模型名称在所有渠道中查找
        return \App\Models\ChannelModel::where('model_name', $actualModel)
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
     * @return Channel|null 备选渠道，无可用渠道时返回 null
     */
    public function getFallbackChannel(Channel $failedChannel, string $model): ?Channel
    {
        if (! $this->config['enable_failover']) {
            return null;
        }

        $channels = $this->getAvailableChannels($model);

        return $channels->where('id', '!=', $failedChannel->id)->first();
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
            ->where('health_status', 'healthy')
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
            ->where('health_status', 'healthy')
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

        $mapping = ModelMapping::where('alias', $model)
            ->where('enabled', true)
            ->first();

        if ($mapping) {
            return $mapping->actual_model;
        }

        return $model;
    }

    /**
     * 标记渠道失败
     *
     * 记录失败信息并检查渠道健康状态
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

        $this->checkChannelHealth($channel);
    }

    /**
     * 标记渠道成功
     *
     * 更新成功计数和健康状态
     *
     * @param  Channel  $channel  成功的渠道
     */
    public function markChannelSuccess(Channel $channel): void
    {
        $channel->increment('success_count');
        $channel->update([
            'last_success_at' => now(),
        ]);

        if ($channel->health_status !== 'healthy') {
            $channel->update(['health_status' => 'healthy']);
        }
    }

    /**
     * 检查渠道健康状态
     *
     * 根据失败率自动标记不健康的渠道
     *
     * @param  Channel  $channel  要检查的渠道
     */
    protected function checkChannelHealth(Channel $channel): void
    {
        $totalRequests = $channel->success_count + $channel->failure_count;
        if ($totalRequests < 10) {
            return;
        }

        $failureRate = $channel->failure_count / $totalRequests;

        if ($failureRate > 0.5) {
            $channel->update(['health_status' => 'unhealthy']);

            Log::error('Channel marked as unhealthy', [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'failure_rate' => $failureRate,
            ]);
        }
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
            'health_status' => $channel->health_status,
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
            $models = ModelMapping::distinct()->pluck('alias');
            foreach ($models as $model) {
                Cache::forget("channels:available:{$model}");
            }
        }
    }
}
