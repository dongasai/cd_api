<?php

namespace App\Services\Provider\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionRequest;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Shared\DTO\StreamChunk;

/**
 * Azure OpenAI 供应商
 *
 * Microsoft Azure OpenAI 服务供应商实现
 */
class AzureProvider extends AbstractProvider
{
    /**
     * 部署名称
     */
    protected string $deploymentName;

    /**
     * API 版本
     */
    protected string $apiVersion;

    /**
     * 支持的模型列表
     */
    protected array $supportedModels = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4',
        'gpt-4-32k',
        'gpt-35-turbo',
        'gpt-35-turbo-16k',
    ];

    /**
     * 构造函数
     *
     * @param  array  $config  供应商配置
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->deploymentName = $config['deployment_name'] ?? $config['deployment'] ?? '';
        $this->apiVersion = $config['api_version'] ?? '2024-02-15-preview';
    }

    /**
     * 获取默认 API 基础 URL
     */
    public function getDefaultBaseUrl(): string
    {
        return '';
    }

    /**
     * 获取 API 端点
     */
    public function getEndpoint(ProtocolRequest $request): string
    {
        // 获取模型名称
        $model = method_exists($request, 'getModel') ? $request->getModel() : '';
        // 使用部署名称或请求中的模型名称
        $deployment = $this->deploymentName ?: $model;

        return "/openai/deployments/{$deployment}/chat/completions";
    }

    /**
     * 获取请求头
     */
    public function getHeaders(): array
    {
        return [
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * 构建请求体
     */
    public function buildRequestBody(ProtocolRequest $request): array
    {
        // 如果是 OpenAI 协议请求，直接转数组
        if ($request instanceof ChatCompletionRequest) {
            return $request->toArray();
        }

        // 其他协议需要转换
        throw new \InvalidArgumentException('AzureProvider requires ChatCompletionRequest');
    }

    /**
     * 解析响应
     */
    public function parseResponse(array $response): ProtocolResponse
    {
        return ChatCompletionResponse::fromArray($response);
    }

    /**
     * 解析流式响应块
     */
    public function parseStreamChunk(string $rawChunk): ?StreamChunk
    {
        return $this->parseOpenAIStreamChunk($rawChunk);
    }

    /**
     * 获取支持的模型列表
     */
    public function getModels(): array
    {
        return $this->supportedModels;
    }

    /**
     * 获取供应商名称
     */
    public function getProviderName(): string
    {
        return 'azure';
    }

    /**
     * 执行 HTTP 请求
     *
     * Azure 需要特殊的 URL 格式，包含 api-version 参数
     */
    protected function executeRequest(ProtocolRequest $request): ProtocolResponse
    {
        // 检查是否开启了 body 透传
        $rawData = $request->toArray();
        if (isset($rawData['rawBodyString'])) {
            $body = $rawData['rawBodyString'];
        } else {
            $body = $this->buildRequestBody($request);
        }

        $endpoint = $this->getEndpoint($request);

        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');
        // Azure 需要在 URL 中添加 api-version 参数
        $url .= '?api-version='.$this->apiVersion;

        // 存储实际请求信息
        $this->lastRequestInfo = new \App\Services\Shared\DTO\ActualRequestInfo(
            url: $url,
            path: $endpoint,
            headers: $this->getHeaders(),
            body: is_string($body) ? json_decode($body, true) ?? $body : $body,
        );

        // 根据 body 类型选择发送方式
        if (is_string($body)) {
            // Body 透传模式：使用原始字符串作为请求体
            $response = \Illuminate\Support\Facades\Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->withBody($body, 'application/json')
                ->post($url);
        } else {
            // 正常模式：使用数组，Laravel会自动转为JSON
            $response = \Illuminate\Support\Facades\Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($url, $body);
        }

        if (! $response->ok()) {
            throw $this->createErrorFromResponse($response);
        }

        $this->recordSuccess();

        return $this->parseResponse($response->json());
    }

    /**
     * 解析 OpenAI 流式响应块
     */
    protected function parseOpenAIStreamChunk(string $rawChunk): ?StreamChunk
    {
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

        return new StreamChunk(
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
}
