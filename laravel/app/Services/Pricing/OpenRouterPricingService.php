<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * openrouter模型价格
 * 每Token/美元
 */
class OpenRouterPricingService
{
    protected const API_URL = 'https://openrouter.ai/api/v1/models';

    protected const CACHE_KEY = 'openrouter_models_data';

    protected const CACHE_TTL = 3600;

    protected ?array $modelsData = null;

    /**
     * 通过模型名称获取价格信息(每Token的价格)
     *
     * @param  string  $modelName  OpenRouter 模型 ID (如: openai/gpt-5.4-pro) 或 canonical_slug
     * @return array|null 价格数组，未找到返回 null
     */
    public function getPricingByModelName(string $modelName): ?array
    {
        $models = $this->getModelsData();

        foreach ($models as $model) {
            if ($model['id'] === $modelName || $model['canonical_slug'] === $modelName) {
                return $this->formatPricing($model);
            }
        }

        return null;
    }

    /**
     * 通过 Hugging Face ID 获取价格信息(每Token的价格)
     *
     * @param  string  $huggingFaceId  Hugging Face 模型 ID (如: meta-llama/Llama-3.1-8B-Instruct)
     * @return array|null 价格数组，未找到返回 null
     */
    public function getPricingByHuggingFaceId(string $huggingFaceId): ?array
    {
        $models = $this->getModelsData();

        foreach ($models as $model) {
            if (! empty($model['hugging_face_id']) && $model['hugging_face_id'] === $huggingFaceId) {
                return $this->formatPricing($model);
            }
        }

        return null;
    }

    /**
     * 获取模型价格信息(每Token的价格)
     *
     * 优先使用 model_name 查询，如果未找到或为空则使用 hugging_face_id 查询
     *
     * @param  string|null  $huggingFaceId  Hugging Face 模型 ID (如: meta-llama/Llama-3.1-8B-Instruct)
     * @param  string|null  $modelName  OpenRouter 模型 ID (如: openai/gpt-5.4-pro)
     * @return array|null 价格数组，未找到返回 null
     *
     * @example
     * // 通过 model_name 查询
     * $pricing = $service->getPricing(null, 'openai/gpt-5.4-pro');
     *
     * // 通过 hugging_face_id 查询
     * $pricing = $service->getPricing('meta-llama/Llama-3.1-8B-Instruct');
     *
     * // 同时提供两个参数，优先使用 model_name
     * $pricing = $service->getPricing('meta-llama/Llama-3.1-8B-Instruct', 'openai/gpt-5.4-pro');
     */
    public function getPricing(?string $huggingFaceId = null, ?string $modelName = null): ?array
    {
        if ($huggingFaceId !== null) {
            return $this->getPricingByHuggingFaceId($huggingFaceId);
        }
        if ($modelName !== null) {
            $pricing = $this->getPricingByModelName($modelName);
            if ($pricing !== null) {
                return $pricing;
            }
        }

        return null;
    }

    /**
     * 获取所有模型数据
     *
     * @return array 模型数据数组
     */
    public function getModelsData(): array
    {
        if ($this->modelsData !== null) {
            return $this->modelsData;
        }

        return $this->modelsData = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => $this->fetchModelsFromApi()
        );
    }

    /**
     * 刷新缓存
     *
     * @return bool 是否刷新成功
     */
    public function refreshCache(): bool
    {
        Cache::forget(self::CACHE_KEY);
        $this->modelsData = null;

        return ! empty($this->getModelsData());
    }

    /**
     * 从 OpenRouter API 获取模型数据
     *
     * @return array 模型数据数组，失败返回空数组
     */
    protected function fetchModelsFromApi(): array
    {
        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->get(self::API_URL);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return $data['data'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 格式化价格数据
     *
     * @param  array  $model  原始模型数据
     * @return array 格式化后的价格数组
     */
    protected function formatPricing(array $model): array
    {
        $pricing = $model['pricing'] ?? [];

        return [
            'model_id' => $model['id'] ?? null,
            'model_name' => $model['name'] ?? null,
            'hugging_face_id' => $model['hugging_face_id'] ?? null,
            'prompt' => isset($pricing['prompt']) ? (float) $pricing['prompt'] : null,
            'completion' => isset($pricing['completion']) ? (float) $pricing['completion'] : null,
            'web_search' => isset($pricing['web_search']) ? (float) $pricing['web_search'] : null,
            'input_cache_read' => isset($pricing['input_cache_read']) ? (float) $pricing['input_cache_read'] : null,
            'context_length' => $model['context_length'] ?? null,
            'raw_pricing' => $pricing,
        ];
    }
}
