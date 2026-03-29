<?php

namespace App\Services\Provider\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Shared\DTO\StreamChunk;

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
    protected string $providerName;

    protected array $customHeaders = [];

    protected ?string $authHeader = null;

    protected ?string $authPrefix = null;

    protected array $supportedModels = [];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->providerName = $config['name'] ?? $config['provider_name'] ?? 'openai-compatible';
        $this->customHeaders = $config['headers'] ?? [];
        $this->authHeader = $config['auth_header'] ?? 'Authorization';
        $this->authPrefix = $config['auth_prefix'] ?? 'Bearer';
        $this->supportedModels = $config['models'] ?? [];
    }

    public function getDefaultBaseUrl(): string
    {
        return $this->config['base_url'] ?? '';
    }

    public function getEndpoint(ProtocolRequest $request): string
    {
        $baseUrl = $this->baseUrl ?? '';
        if (str_ends_with($baseUrl, '/v1')) {
            return '/chat/completions';
        }

        return '/v1/chat/completions';
    }

    public function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (! empty($this->apiKey)) {
            $authValue = $this->authPrefix
                ? $this->authPrefix.' '.$this->apiKey
                : $this->apiKey;
            $headers[$this->authHeader] = $authValue;
        }

        foreach ($this->customHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        return $this->mergeForwardedHeaders($headers);
    }

    public function buildRequestBody(ProtocolRequest $request): array
    {
        // 如果是 OpenAI 协议请求，直接转数组
        if ($request instanceof ChatCompletionRequest) {
            $body = $request->toArray();

            // 检查渠道配置：是否强制附加 stream_options
            $forceStreamOptions = $this->config['force_stream_options'] ?? false;

            // 流式请求时，根据配置决定是否附加 stream_options
            if ($forceStreamOptions && ($body['stream'] ?? false) === true) {
                if (! isset($body['stream_options'])) {
                    $body['stream_options'] = ['include_usage' => true];
                } elseif (! isset($body['stream_options']['include_usage'])) {
                    $body['stream_options']['include_usage'] = true;
                }
            }

            return $body;
        }

        // 其他协议需要转换
        throw new \InvalidArgumentException('OpenAICompatibleProvider requires ChatCompletionRequest');
    }

    public function parseResponse(array $response): ProtocolResponse
    {
        return ChatCompletionResponse::fromArray($response);
    }

    public function parseStreamChunk(string $rawChunk): ?StreamChunk
    {
        return $this->parseOpenAIStreamChunk($rawChunk);
    }

    public function getModels(): array
    {
        if (! empty($this->supportedModels)) {
            return $this->supportedModels;
        }

        return [];
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

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

    public static function createLocal(string $baseUrl, string $apiKey = ''): self
    {
        return new self([
            'name' => 'local',
            'base_url' => rtrim($baseUrl, '/'),
            'api_key' => $apiKey,
        ]);
    }

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
