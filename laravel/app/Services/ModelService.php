<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\ModelList;
use Illuminate\Support\Facades\Cache;

/**
 * 模型服务
 *
 * 提供模型可用性检查和模型列表获取功能
 * 支持 API Key 级别的模型限制和别名映射
 */
class ModelService
{
    /**
     * 缓存时间（秒）
     */
    private const CACHE_TTL = 30;

    /**
     * 获取可用的模型列表
     *
     * @param  ApiKey|null  $apiKey  API密钥，为空时返回全局启用模型
     * @return array 模型列表数组，格式: [['id' => string, 'object' => string, 'created' => int, 'owned_by' => string], ...]
     */
    public static function getAvailableModels(?ApiKey $apiKey = null): array
    {
        $cacheKey = self::getCacheKey('models', $apiKey?->id);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($apiKey) {
            $modelNames = collect();

            // 如果提供了 API Key，基于渠道权限获取可用模型
            if ($apiKey) {
                // 获取 API Key 可访问的活跃渠道
                $channels = $apiKey->getAllowedChannels();

                // 从这些渠道中收集启用的模型
                foreach ($channels as $channel) {
                    $enabledModels = $channel->enabledModels()->get(['model_name']);
                    $modelNames = $modelNames->merge($enabledModels->pluck('model_name'));
                }

                // 去重
                $modelNames = $modelNames->unique()->values();

                // 如果 API Key 有 allowed_models 限制，进一步过滤
                $allowedModels = $apiKey->allowed_models;
                if (! empty($allowedModels) && is_array($allowedModels)) {
                    $modelNames = $modelNames->filter(function ($modelName) use ($allowedModels) {
                        return in_array($modelName, $allowedModels, true);
                    })->values();
                }
            } else {
                // 没有 API Key 时，返回所有全局启用的模型
                $modelNames = ModelList::where('is_enabled', true)
                    ->pluck('model_name');
            }

            // 从 model_lists 表获取模型详细信息
            $modelLists = ModelList::whereIn('model_name', $modelNames)
                ->where('is_enabled', true)
                ->get();

            // 构建模型数据
            $data = $modelLists->map(function ($modelList) {
                return [
                    'id' => $modelList->model_name,
                    'object' => 'model',
                    'created' => $modelList->created_at?->timestamp ?? time(),
                    'owned_by' => $modelList->provider ?? 'system',
                ];
            })->values()->toArray();

            // 添加映射的模型别名
            if ($apiKey && ! empty($apiKey->model_mappings)) {
                foreach ($apiKey->model_mappings as $alias => $actualModel) {
                    $data[] = [
                        'id' => $alias,
                        'object' => 'model',
                        'created' => time(),
                        'owned_by' => 'cdapi',
                    ];
                }
            }

            return $data;
        });
    }

    /**
     * 检查模型是否可用
     *
     * @param  string  $model  模型名称
     * @param  ApiKey|null  $apiKey  API密钥，为空时检查全局可用性
     * @return bool 模型是否可用
     */
    public static function isModelAvailable(string $model, ?ApiKey $apiKey = null): bool
    {
        $cacheKey = self::getCacheKey('availability', $apiKey?->id, $model);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($model, $apiKey) {
            // 1. 检查 API Key 的模型映射（别名）
            if ($apiKey && ! empty($apiKey->model_mappings)) {
                $mappings = $apiKey->model_mappings;
                if (isset($mappings[$model])) {
                    return true;
                }
            }

            // 2. 检查 API Key 的允许模型列表
            if ($apiKey && ! empty($apiKey->allowed_models)) {
                return in_array($model, $apiKey->allowed_models, true);
            }

            // 3. 检查全局模型列表（支持别名匹配）
            // 先尝试精确匹配
            if (ModelList::where('model_name', $model)->where('is_enabled', true)->exists()) {
                return true;
            }

            // 如果精确匹配失败，尝试通过别名查找
            return self::findModelByAnyName($model) !== null;
        });
    }

    /**
     * 获取模型解析后的实际名称
     *
     * 如果模型是别名，则返回映射后的实际模型名称
     *
     * @param  string  $model  模型名称
     * @param  ApiKey|null  $apiKey  API密钥
     * @return string 实际模型名称
     */
    public static function resolveModel(string $model, ?ApiKey $apiKey = null): string
    {
        if ($apiKey && ! empty($apiKey->model_mappings)) {
            $mappings = $apiKey->model_mappings;
            if (isset($mappings[$model])) {
                return $mappings[$model];
            }
        }

        return $model;
    }

    /**
     * 解析模型并返回所有关联名称（用于路由降级）
     *
     * 通过别名查找模型，返回该模型的所有名称（包括自身和别名）
     * 如果模型不存在，返回空数组
     *
     * @param  string  $model  模型名称或别名
     * @return array 关联名称数组，格式: ['glm-5', 'GLM-5', 'z-ai/glm-5', ...]
     */
    public static function resolveModelWithAliases(string $model): array
    {
        $cacheKey = self::getCacheKey('aliases', null, $model);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($model) {
            // 查找模型（通过名称或别名）
            $modelList = self::findModelByAnyName($model);

            if ($modelList === null) {
                return [];
            }

            return $modelList->getAllNames();
        });
    }

    /**
     * 通过任一名称查找模型（包括别名）
     *
     * 先尝试精确匹配 model_name，再尝试在 aliases JSON 中查找
     *
     * @param  string  $name  模型名称或别名
     * @return ModelList|null 模型对象，未找到则返回 null
     */
    public static function findModelByAnyName(string $name): ?ModelList
    {
        $cacheKey = self::getCacheKey('find', null, $name);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($name) {
            // 1. 先尝试精确匹配 model_name
            $model = ModelList::where('model_name', $name)
                ->where('is_enabled', true)
                ->first();

            if ($model !== null) {
                return $model;
            }

            // 2. 尝试在 aliases JSON 字段中查找
            // 使用 Laravel JSON 查询方法
            $model = ModelList::where('is_enabled', true)
                ->whereJsonContains('aliases', $name)
                ->first();

            return $model;
        });
    }

    /**
     * 获取 API Key 可用的渠道模型详情
     *
     * 用于后台展示，返回按渠道分组的可用模型列表
     *
     * @param  ApiKey  $apiKey  API密钥
     * @return array 渠道模型列表，格式: [['channel' => Channel, 'models' => Collection], ...]
     */
    public static function getAvailableChannelModels(ApiKey $apiKey): array
    {
        $cacheKey = self::getCacheKey('channel_models', $apiKey->id);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($apiKey) {
            // 获取允许的渠道ID
            $allowedChannels = $apiKey->allowed_channels;
            if (is_string($allowedChannels)) {
                $allowedChannels = json_decode($allowedChannels, true);
            }

            // 获取禁止的渠道ID
            $notAllowedChannels = $apiKey->not_allowed_channels;
            if (is_string($notAllowedChannels)) {
                $notAllowedChannels = json_decode($notAllowedChannels, true);
            }

            // 构建查询
            $query = Channel::where('status', 'active');

            // 如果有禁止的渠道，排除它们
            if (! empty($notAllowedChannels) && is_array($notAllowedChannels)) {
                $query->whereNotIn('id', array_map('intval', $notAllowedChannels));
            }

            // 如果有允许的渠道，只查询这些渠道
            if (! empty($allowedChannels) && is_array($allowedChannels)) {
                $query->whereIn('id', array_map('intval', $allowedChannels));
            }

            $channels = $query->get();

            $result = [];
            foreach ($channels as $channel) {
                $enabledModels = $channel->enabledModels()->get();
                if ($enabledModels->isNotEmpty()) {
                    $result[] = [
                        'channel' => $channel,
                        'models' => $enabledModels,
                    ];
                }
            }

            return $result;
        });
    }

    /**
     * 清除模型缓存
     *
     * @param  int|null  $apiKeyId  API密钥ID，为空时清除所有缓存
     */
    public static function clearCache(?int $apiKeyId = null): void
    {
        if ($apiKeyId === null) {
            // 清除所有模型相关的缓存
            $keys = Cache::get('model_service_cache_keys', []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
            Cache::forget('model_service_cache_keys');
        } else {
            // 清除特定 API Key 的缓存
            $patterns = [
                self::getCacheKey('models', $apiKeyId),
                self::getCacheKey('availability', $apiKeyId, '*'),
            ];

            foreach ($patterns as $pattern) {
                if (str_contains($pattern, '*')) {
                    // 通配符模式，需要从记录的键中匹配
                    $keys = Cache::get('model_service_cache_keys', []);
                    foreach ($keys as $key) {
                        if (str_starts_with($key, dirname($pattern))) {
                            Cache::forget($key);
                        }
                    }
                } else {
                    Cache::forget($pattern);
                }
            }
        }
    }

    /**
     * 生成缓存键
     *
     * @param  string  $type  缓存类型
     * @param  int|null  $apiKeyId  API密钥ID
     * @param  string|null  $model  模型名称
     * @return string 缓存键
     */
    private static function getCacheKey(string $type, ?int $apiKeyId = null, ?string $model = null): string
    {
        $key = "model_service:{$type}";

        if ($apiKeyId !== null) {
            $key .= ":key:{$apiKeyId}";
        }

        if ($model !== null) {
            $key .= ":model:{$model}";
        }

        // 记录缓存键以便清理
        $keys = Cache::get('model_service_cache_keys', []);
        if (! in_array($key, $keys)) {
            $keys[] = $key;
            Cache::put('model_service_cache_keys', $keys, self::CACHE_TTL * 2);
        }

        return $key;
    }
}
