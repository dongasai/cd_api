<?php

namespace App\Services\Provider\Driver;

use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\StreamChunk;
use Illuminate\Support\Facades\Log;

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

    public function getEndpoint(Request $request): string
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

    public function buildRequestBody(Request $request): array
    {
        return $this->toOpenAIFormat($request);
    }

    public function parseResponse(array $response): Response
    {
        return $this->parseOpenAIResponse($response);
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

    /**
     * 将 Request 转换为 OpenAI 格式
     */
    protected function toOpenAIFormat(Request $request): array
    {
        $result = [
            'model' => $request->model,
            'messages' => array_map(fn ($m) => $m->toOpenAI(), $request->messages),
        ];

        if ($request->maxTokens !== null) {
            $result['max_tokens'] = $request->maxTokens;
        }
        if ($request->temperature !== null) {
            $result['temperature'] = $request->temperature;
        }
        if ($request->topP !== null) {
            $result['top_p'] = $request->topP;
        }
        if ($request->stream) {
            $result['stream'] = true;
        }
        if ($request->tools !== null) {
            $result['tools'] = $request->tools;
        }
        if ($request->toolChoice !== null) {
            $result['tool_choice'] = $request->toolChoice;
        }
        if ($request->user !== null) {
            $result['user'] = $request->user;
        }

        return array_merge($result, $request->additionalParams);
    }

    /**
     * 解析 OpenAI 响应
     */
    protected function parseOpenAIResponse(array $response): Response
    {
        $choices = [];
        foreach ($response['choices'] ?? [] as $choice) {
            $choices[] = [
                'index' => $choice['index'] ?? 0,
                'message' => $choice['message'] ?? [],
                'finish_reason' => $choice['finish_reason'] ?? null,
            ];
        }

        $usage = null;
        if (isset($response['usage'])) {
            $usage = \App\Services\Shared\DTO\Usage::fromOpenAI($response['usage']);
        }

        $finishReason = null;
        if (isset($response['choices'][0]['finish_reason'])) {
            $finishReason = \App\Services\Shared\Enums\FinishReason::fromOpenAI($response['choices'][0]['finish_reason']);
        }

        return new Response(
            id: $response['id'] ?? '',
            model: $response['model'] ?? '',
            choices: $choices,
            usage: $usage,
            finishReason: $finishReason,
            systemFingerprint: $response['system_fingerprint'] ?? null,
            created: $response['created'] ?? 0,
        );
    }

    /**
     * 解析 OpenAI 流式响应块
     */
    protected function parseOpenAIStreamChunk(string $rawChunk): ?\App\Services\Shared\DTO\StreamChunk
    {
        Log::debug("parseOpenAIStreamChunk \n".$rawChunk);

        // 处理 "data: " 前缀
        if (str_starts_with($rawChunk, 'data: ')) {
            $rawChunk = substr($rawChunk, 6);
        }

        // 跳过空行和 "[DONE]"
        if (trim($rawChunk) === '' || trim($rawChunk) === '[DONE]') {
            return null;
        }

        $data = json_decode($rawChunk, true);
        if ($data === null) {
            return null;
        }

        $id = $data['id'] ?? '';
        $model = $data['model'] ?? '';
        $choices = $data['choices'] ?? [];
        $choice = $choices[0] ?? [];

        $delta = $choice['delta'] ?? [];
        $finishReason = isset($choice['finish_reason']) && $choice['finish_reason'] !== null
            ? \App\Services\Shared\Enums\FinishReason::fromOpenAI($choice['finish_reason'])
            : null;

        $contentDelta = $delta['content'] ?? null;
        $reasoningDelta = $delta['reasoning_content'] ?? null;
        $toolCalls = $delta['tool_calls'] ?? null;

        $usage = null;
        if (isset($data['usage'])) {
            $usage = \App\Services\Shared\DTO\Usage::fromOpenAI($data['usage']);
        }

        return new \App\Services\Shared\DTO\StreamChunk(
            id: $id,
            model: $model,
            contentDelta: $contentDelta,
            finishReason: $finishReason,
            index: $choice['index'] ?? 0,
            usage: $usage,
            event: '',
            data: $data,
            delta: $contentDelta ?? '',
            toolCalls: $toolCalls,
            reasoningDelta: $reasoningDelta,
        );
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
