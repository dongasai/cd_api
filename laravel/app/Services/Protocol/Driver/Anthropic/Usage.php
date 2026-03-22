<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\Usage as SharedUsage;

/**
 * Anthropic Token 使用量结构体
 *
 * @see https://docs.anthropic.com/en/api/messages#response-usage
 */
class Usage
{
    use Convertible;
    use JsonSerializiable;

    /**
     * @param  int  $input_tokens  输入 token 数（必需）
     * @param  int  $output_tokens  输出 token 数（必需）
     * @param  int|null  $cache_creation_input_tokens  缓存创建输入 token 数
     * @param  int|null  $cache_read_input_tokens  缓存读取输入 token 数
     * @param  CacheCreation|null  $cache_creation  缓存创建详情
     * @param  string|null  $inference_geo  推理地理位置
     * @param  array|null  $server_tool_use  服务端工具使用信息
     * @param  string|null  $service_tier  服务层级
     */
    public function __construct(
        public int $input_tokens = 0,
        public int $output_tokens = 0,
        public ?int $cache_creation_input_tokens = null,
        public ?int $cache_read_input_tokens = null,
        public ?CacheCreation $cache_creation = null,
        public ?string $inference_geo = null,
        public ?array $server_tool_use = null,
        public ?string $service_tier = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'input_tokens' => 'required|integer|min:0',
            'output_tokens' => 'required|integer|min:0',
            'cache_creation_input_tokens' => 'nullable|integer|min:0',
            'cache_read_input_tokens' => 'nullable|integer|min:0',
            'cache_creation' => 'nullable|array',
            'inference_geo' => 'nullable|string',
            'server_tool_use' => 'nullable|array',
            'service_tier' => 'nullable|string|in:priority,standard,batch',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 解析 cache_creation
        $cacheCreation = null;
        if (isset($data['cache_creation']) && is_array($data['cache_creation'])) {
            $cacheCreation = CacheCreation::fromArray($data['cache_creation']);
        }

        return new self(
            input_tokens: $data['input_tokens'] ?? 0,
            output_tokens: $data['output_tokens'] ?? 0,
            cache_creation_input_tokens: $data['cache_creation_input_tokens'] ?? null,
            cache_read_input_tokens: $data['cache_read_input_tokens'] ?? null,
            cache_creation: $cacheCreation,
            inference_geo: $data['inference_geo'] ?? null,
            server_tool_use: $data['server_tool_use'] ?? null,
            service_tier: $data['service_tier'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
        ];

        if ($this->cache_creation_input_tokens !== null) {
            $result['cache_creation_input_tokens'] = $this->cache_creation_input_tokens;
        }

        if ($this->cache_read_input_tokens !== null) {
            $result['cache_read_input_tokens'] = $this->cache_read_input_tokens;
        }

        if ($this->cache_creation !== null) {
            $result['cache_creation'] = $this->cache_creation->toArray();
        }

        if ($this->inference_geo !== null) {
            $result['inference_geo'] = $this->inference_geo;
        }

        if ($this->server_tool_use !== null) {
            $result['server_tool_use'] = $this->server_tool_use;
        }

        if ($this->service_tier !== null) {
            $result['service_tier'] = $this->service_tier;
        }

        return $result;
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedUsage
    {
        return new SharedUsage(
            inputTokens: $this->input_tokens,
            outputTokens: $this->output_tokens,
            totalTokens: $this->input_tokens + $this->output_tokens,
            cacheReadInputTokens: $this->cache_read_input_tokens,
            cacheCreationInputTokens: $this->cache_creation_input_tokens,
            cacheCreation: $this->cache_creation?->toArray(),
            inferenceGeo: $this->inference_geo,
            serverToolUse: $this->server_tool_use,
            serviceTier: $this->service_tier,
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        $cacheCreation = null;
        if (isset($dto->cacheCreation) && is_array($dto->cacheCreation)) {
            $cacheCreation = CacheCreation::fromArray($dto->cacheCreation);
        }

        return new self(
            input_tokens: $dto->inputTokens ?? 0,
            output_tokens: $dto->outputTokens ?? 0,
            cache_creation_input_tokens: $dto->cacheCreationInputTokens ?? null,
            cache_read_input_tokens: $dto->cacheReadInputTokens ?? null,
            cache_creation: $cacheCreation,
            inference_geo: $dto->inferenceGeo ?? null,
            server_tool_use: $dto->serverToolUse ?? null,
            service_tier: $dto->serviceTier ?? null,
        );
    }
}
