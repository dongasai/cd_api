<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;

/**
 * OpenAI 兼容供应商
 *
 * 支持所有兼容 OpenAI API 格式的服务供应商，包括：
 * - DeepSeek
 * - 智谱 GLM
 * - Moonshot
 * - Ollama
 * - 其他本地部署模型
 */
class OpenAICompatibleProvider extends AbstractProvider
{
    /**
     * 供应商名称
     */
    protected string $providerName;

    /**
     * 自定义请求头
     */
    protected array $customHeaders = [];

    /**
     * 认证头字段名
     */
    protected ?string $authHeader = null;

    /**
     * 认证前缀（如 Bearer）
     */
    protected ?string $authPrefix = null;

    /**
     * 支持的模型列表
     */
    protected array $supportedModels = [];

    /**
     * 构造函数
     *
     * @param  array  $config  供应商配置
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->providerName = $config['name'] ?? $config['provider_name'] ?? 'openai-compatible';
        $this->customHeaders = $config['headers'] ?? [];
        $this->authHeader = $config['auth_header'] ?? 'Authorization';
        $this->authPrefix = $config['auth_prefix'] ?? 'Bearer';
        $this->supportedModels = $config['models'] ?? [];
    }

    /**
     * 获取默认 API 基础 URL
     */
    public function getDefaultBaseUrl(): string
    {
        return $this->config['base_url'] ?? '';
    }

    /**
     * 获取 API 端点
     */
    public function getEndpoint(ProviderRequest $request): string
    {
        $baseUrl = $this->baseUrl ?? '';
        // 如果基础 URL 已包含 /v1，则使用简短路径
        if (str_ends_with($baseUrl, '/v1')) {
            return '/chat/completions';
        }

        return '/v1/chat/completions';
    }

    /**
     * 获取请求头
     */
    public function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // 添加认证头
        if (! empty($this->apiKey)) {
            $authValue = $this->authPrefix
                ? $this->authPrefix.' '.$this->apiKey
                : $this->apiKey;
            $headers[$this->authHeader] = $authValue;
        }

        // 合并自定义请求头
        foreach ($this->customHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * 构建请求体
     */
    public function buildRequestBody(ProviderRequest $request): array
    {
        return $request->toOpenAIFormat();
    }

    /**
     * 解析响应
     */
    public function parseResponse(array $response): ProviderResponse
    {
        return ProviderResponse::fromOpenAI($response);
    }

    /**
     * 解析流式响应块
     */
    public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk
    {
        return ProviderStreamChunk::fromOpenAI($rawChunk);
    }

    /**
     * 获取支持的模型列表
     */
    public function getModels(): array
    {
        if (! empty($this->supportedModels)) {
            return $this->supportedModels;
        }

        return [];
    }

    /**
     * 获取供应商名称
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * 创建 DeepSeek 供应商实例
     */
    public static function createDeepSeek(string $apiKey): self
    {
        return new self([
            'name' => 'deepseek',
            'base_url' => 'https://api.deepseek.com',
            'api_key' => $apiKey,
            'models' => [
                'deepseek-chat',
                'deepseek-coder',
                'deepseek-reasoner',
            ],
        ]);
    }

    /**
     * 创建智谱 GLM 供应商实例
     */
    public static function createZhipu(string $apiKey): self
    {
        return new self([
            'name' => 'zhipu',
            'base_url' => 'https://open.bigmodel.cn/api/paas/v4',
            'api_key' => $apiKey,
            'auth_header' => 'Authorization',
            'auth_prefix' => 'Bearer',
            'models' => [
                'glm-4',
                'glm-4-flash',
                'glm-4-plus',
                'glm-4-air',
                'glm-4-airx',
            ],
        ]);
    }

    /**
     * 创建 Moonshot 供应商实例
     */
    public static function createMoonshot(string $apiKey): self
    {
        return new self([
            'name' => 'moonshot',
            'base_url' => 'https://api.moonshot.cn/v1',
            'api_key' => $apiKey,
            'models' => [
                'moonshot-v1-8k',
                'moonshot-v1-32k',
                'moonshot-v1-128k',
            ],
        ]);
    }

    /**
     * 创建本地模型供应商实例
     */
    public static function createLocal(string $baseUrl, string $apiKey = ''): self
    {
        return new self([
            'name' => 'local',
            'base_url' => rtrim($baseUrl, '/'),
            'api_key' => $apiKey,
        ]);
    }

    /**
     * 创建 Ollama 供应商实例
     */
    public static function createOllama(string $baseUrl = 'http://localhost:11434'): self
    {
        return new self([
            'name' => 'ollama',
            'base_url' => $baseUrl,
            'api_key' => 'ollama',
            'auth_header' => 'Authorization',
            'auth_prefix' => 'Bearer',
        ]);
    }
}
