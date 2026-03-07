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
    protected string $providerName;

    protected array $customHeaders = [];

    protected ?string $authHeader = null;

    protected ?string $authPrefix = null;

    protected array $supportedModels = [];

    protected array $forwardHeaders = [];

    protected array $clientHeaders = [];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->providerName = $config['name'] ?? $config['provider_name'] ?? 'openai-compatible';
        $this->customHeaders = $config['headers'] ?? [];
        $this->authHeader = $config['auth_header'] ?? 'Authorization';
        $this->authPrefix = $config['auth_prefix'] ?? 'Bearer';
        $this->supportedModels = $config['models'] ?? [];
        $this->forwardHeaders = $config['forward_headers'] ?? [];
        $this->clientHeaders = $config['client_headers'] ?? [];
    }

    public function getDefaultBaseUrl(): string
    {
        return $this->config['base_url'] ?? '';
    }

    public function getEndpoint(ProviderRequest $request): string
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

        $forwardedHeaders = $this->buildForwardedHeaders();
        foreach ($forwardedHeaders as $key => $value) {
            if (! isset($headers[$key])) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    protected function buildForwardedHeaders(): array
    {
        if (empty($this->forwardHeaders) || empty($this->clientHeaders)) {
            return [];
        }

        $result = [];
        $clientHeadersFlat = $this->flattenHeaders($this->clientHeaders);

        foreach ($this->forwardHeaders as $pattern) {
            $pattern = strtolower(trim($pattern));
            if (empty($pattern)) {
                continue;
            }

            foreach ($clientHeadersFlat as $headerName => $headerValue) {
                $headerNameLower = strtolower($headerName);

                if ($this->matchHeaderPattern($pattern, $headerNameLower)) {
                    $result[$headerName] = $headerValue;
                }
            }
        }

        return $result;
    }

    protected function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                $flat[$key] = reset($value);
            } else {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }

    protected function matchHeaderPattern(string $pattern, string $headerName): bool
    {
        if (str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);

            return str_starts_with($headerName, $prefix);
        }

        if (str_starts_with($pattern, '*')) {
            $suffix = substr($pattern, 1);

            return str_ends_with($headerName, $suffix);
        }

        return $pattern === $headerName;
    }

    public function buildRequestBody(ProviderRequest $request): array
    {
        return $request->toOpenAIFormat();
    }

    public function parseResponse(array $response): ProviderResponse
    {
        return ProviderResponse::fromOpenAI($response);
    }

    public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk
    {
        return ProviderStreamChunk::fromOpenAI($rawChunk);
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
