<?php

namespace App\Services\Router;

use App\Models\Channel;
use App\Models\ModelMapping;
use App\Models\ChannelGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChannelRouterService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'cache_ttl' => 60,
            'max_retry' => 3,
            'enable_failover' => true,
        ], $config);
    }

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

    protected function getChannelIdsForModel(string $model): array
    {
        // 查找指定了具体渠道的映射
        $specificMappings = ModelMapping::where('alias', $model)
            ->where('enabled', true)
            ->whereNotNull('channel_id')
            ->pluck('channel_id')
            ->toArray();

        if (!empty($specificMappings)) {
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

    protected function applyLoadBalancing($channels, array $context = []): Channel
    {
        $algorithm = $context['lb_algorithm'] ?? 'weighted_round_robin';

        return match ($algorithm) {
            'least_connections' => $this->leastConnectionsBalance($channels),
            'response_time' => $this->responseTimeBalance($channels),
            default => $this->weightedRoundRobinBalance($channels),
        };
    }

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

    protected function leastConnectionsBalance($channels): Channel
    {
        return $channels->sortBy('total_requests')->first();
    }

    protected function responseTimeBalance($channels): Channel
    {
        return $channels->sortBy('avg_latency_ms')->first();
    }

    public function getFallbackChannel(Channel $failedChannel, string $model): ?Channel
    {
        if (!$this->config['enable_failover']) {
            return null;
        }

        $channels = $this->getAvailableChannels($model);

        return $channels->where('id', '!=', $failedChannel->id)->first();
    }

    public function getChannelsByGroup(string $groupSlug): \Illuminate\Database\Eloquent\Collection
    {
        $group = ChannelGroup::where('slug', $groupSlug)->first();

        if (!$group) {
            return collect();
        }

        return $group->channels()
            ->where('status', 'active')
            ->where('health_status', 'healthy')
            ->orderByPivot('priority', 'desc')
            ->get();
    }

    public function getChannelsByTag(string $tagName): \Illuminate\Database\Eloquent\Collection
    {
        return Channel::whereHas('tags', function ($query) use ($tagName) {
            $query->where('name', $tagName);
        })
            ->where('status', 'active')
            ->where('health_status', 'healthy')
            ->get();
    }

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
