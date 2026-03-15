<?php

namespace App\Services\Provider\Driver;

use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
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
    public function getEndpoint(Request $request): string
    {
        // 使用部署名称或请求中的模型名称
        $deployment = $this->deploymentName ?: $request->model;

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
    public function buildRequestBody(Request $request): array
    {
        return $this->toOpenAIFormat($request);
    }

    /**
     * 解析响应
     */
    public function parseResponse(array $response): Response
    {
        return $this->parseOpenAIResponse($response);
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
    protected function executeRequest(Request $request): Response
    {
        // 检查是否开启了 body 透传
        if ($request->rawBodyString !== null) {
            $body = $request->rawBodyString;
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
}
