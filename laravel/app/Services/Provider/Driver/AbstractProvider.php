<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ActualRequestInfo;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;
use App\Services\Provider\DTO\TokenUsage;
use App\Services\Provider\Exceptions\ProviderException;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 抽象供应商基类
 *
 * 提供供应商的公共功能实现，包括：
 * - HTTP 请求发送
 * - 重试机制
 * - 熔断器模式
 * - 流式响应处理
 */
abstract class AbstractProvider implements ProviderInterface
{
    /**
     * API 基础 URL
     */
    protected string $baseUrl;

    /**
     * API 密钥
     */
    protected string $apiKey;

    /**
     * 供应商配置
     */
    protected array $config;

    /**
     * 最后一次错误消息
     */
    protected ?string $lastErrorMessage = null;

    /**
     * 最后一次实际请求信息
     */
    protected ?ActualRequestInfo $lastRequestInfo = null;

    /**
     * 请求超时时间（秒）
     */
    protected int $timeout = 600;

    /**
     * 连接超时时间（秒）
     */
    protected int $connectTimeout = 10;

    /**
     * 最大重试次数
     */
    protected int $maxRetries = 3;

    /**
     * 重试延迟（毫秒）
     */
    protected int $retryDelay = 1000;

    /**
     * 重试延迟倍数
     */
    protected float $retryMultiplier = 2.0;

    /**
     * 熔断器失败阈值
     */
    protected int $circuitFailureThreshold = 5;

    /**
     * 熔断器重置超时（秒）
     */
    protected int $circuitResetTimeout = 60;

    /**
     * 熔断器当前失败次数
     */
    protected int $circuitFailures = 0;

    /**
     * 熔断器打开时间
     */
    protected ?int $circuitOpenTime = null;

    /**
     * 熔断器状态：closed, open, half-open
     */
    protected string $circuitState = 'closed';

    protected array $forwardHeaders = [];

    protected array $clientHeaders = [];

    /**
     * Header 黑名单（不允许穿透的 header）
     */
    protected array $headerBlacklist = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->baseUrl = $config['base_url'] ?? $this->getDefaultBaseUrl();
        $this->apiKey = $config['api_key'] ?? '';
        $this->timeout = $config['timeout'] ?? 600;
        $this->connectTimeout = $config['connect_timeout'] ?? 10;
        $this->maxRetries = $config['max_retries'] ?? 3;
        $this->retryDelay = $config['retry_delay'] ?? 1000;
        $this->retryMultiplier = $config['retry_multiplier'] ?? 2.0;
        $this->circuitFailureThreshold = $config['circuit_failure_threshold'] ?? 5;
        $this->circuitResetTimeout = $config['circuit_reset_timeout'] ?? 60;
        $this->forwardHeaders = $config['forward_headers'] ?? [];
        $this->clientHeaders = $config['client_headers'] ?? [];
        // 合并配置的黑名单到默认黑名单
        if (isset($config['header_blacklist'])) {
            $this->headerBlacklist = array_unique(
                array_merge($this->headerBlacklist, $config['header_blacklist'])
            );
        }
    }

    /**
     * 获取默认 API 基础 URL
     */
    abstract public function getDefaultBaseUrl(): string;

    /**
     * 构建请求体
     */
    abstract public function buildRequestBody(ProviderRequest $request): array;

    /**
     * 解析响应
     */
    abstract public function parseResponse(array $response): ProviderResponse;

    /**
     * 解析流式响应块
     */
    abstract public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk;

    /**
     * 获取 API 端点
     */
    abstract public function getEndpoint(ProviderRequest $request): string;

    abstract public function getHeaders(): array;

    protected function buildForwardedHeaders(): array
    {
        if (empty($this->forwardHeaders) || empty($this->clientHeaders)) {
            return [];
        }

        $result = [];
        $clientHeadersFlat = $this->flattenHeaders($this->clientHeaders);
        $blacklist = array_map('strtolower', $this->headerBlacklist);

        foreach ($this->forwardHeaders as $pattern) {
            $pattern = strtolower(trim($pattern));
            if (empty($pattern)) {
                continue;
            }

            foreach ($clientHeadersFlat as $headerName => $headerValue) {
                $headerNameLower = strtolower($headerName);

                // 检查是否在黑名单中
                if (in_array($headerNameLower, $blacklist, true)) {
                    continue;
                }

                if ($this->matchHeaderPattern($pattern, $headerNameLower)) {
                    $result[$headerName] = $headerValue;
                }
            }
        }

        return $result;
    }

    /**
     * 构建完整 URL（包含 query 参数）
     *
     * @param  string  $endpoint  API 端点路径
     * @param  string|null  $queryString  额外的 query 参数
     * @return string 完整 URL
     */
    protected function buildUrl(string $endpoint, ?string $queryString = null): string
    {
        // 解析 baseUrl，分离 query 参数
        $parsedUrl = parse_url($this->baseUrl);

        // 构建基础 URL（不包含 query 参数）
        $baseUrlWithoutQuery = ($parsedUrl['scheme'] ?? 'https').'://'.($parsedUrl['host'] ?? '');
        if (isset($parsedUrl['port'])) {
            $baseUrlWithoutQuery .= ':'.$parsedUrl['port'];
        }
        if (isset($parsedUrl['path'])) {
            $baseUrlWithoutQuery .= $parsedUrl['path'];
        }

        // 拼接 endpoint
        $url = rtrim($baseUrlWithoutQuery, '/').'/'.ltrim($endpoint, '/');

        // 收集所有 query 参数
        $queryParams = [];

        // baseUrl 中的 query 参数
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $params);
            $queryParams = array_merge($queryParams, $params);
        }

        // 额外的 query 参数
        if ($queryString !== null) {
            parse_str($queryString, $params);
            $queryParams = array_merge($queryParams, $params);
        }

        // 添加 query 参数到 URL
        if (! empty($queryParams)) {
            $url .= '?'.http_build_query($queryParams);
        }

        return $url;
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

    protected function mergeForwardedHeaders(array $headers): array
    {
        $forwardedHeaders = $this->buildForwardedHeaders();
        foreach ($forwardedHeaders as $key => $value) {
            if (! isset($headers[$key])) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * 发送同步请求
     *
     * 包含重试机制和熔断器保护
     */
    public function send(ProviderRequest $request): ProviderResponse
    {
        $this->checkCircuitBreaker();

        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->executeRequest($request);
            } catch (ProviderException $e) {
                $lastException = $e;

                // 如果错误不应重试，直接抛出
                if ($e->shouldSkipRetry()) {
                    throw $e;
                }

                // 如果还有重试机会且错误可重试
                if ($attempt < $this->maxRetries && $e->isRetryable()) {
                    // 指数退避延迟
                    $delay = (int) ($this->retryDelay * pow($this->retryMultiplier, $attempt));
                    usleep($delay * 1000);

                    continue;
                }

                throw $e;
            } catch (ConnectionException $e) {
                $lastException = ProviderException::networkError($e->getMessage(), $e);
                $this->recordFailure();

                if ($attempt < $this->maxRetries) {
                    $delay = (int) ($this->retryDelay * pow($this->retryMultiplier, $attempt));
                    usleep($delay * 1000);

                    continue;
                }
            }
        }

        $this->recordFailure();
        throw $lastException ?? ProviderException::networkError('Unknown error occurred');
    }

    /**
     * 发送流式请求
     *
     * @return Generator<ProviderStreamChunk>
     */
    public function sendStream(ProviderRequest $request): Generator
    {
        $this->checkCircuitBreaker();

        $request->stream = true;

        // 检查是否开启了 body 透传
        if ($request->rawBodyString !== null) {
            $body = $request->rawBodyString;
        } else {
            $body = $this->buildRequestBody($request);
        }

        $endpoint = $this->getEndpoint($request);
        $url = $this->buildUrl($endpoint, $request->queryString);
        $headers = $this->getHeaders();

        // 存储实际请求信息
        $this->lastRequestInfo = new ActualRequestInfo(
            url: $url,
            path: $endpoint,
            headers: $headers,
            body: is_string($body) ? json_decode($body, true) ?? $body : $body,
        );

        try {
            // 根据 body 类型选择发送方式
            if (is_string($body)) {
                // Body 透传模式：使用原始字符串作为请求体
                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->connectTimeout($this->connectTimeout)
                    ->withBody($body, 'application/json')
                    ->withOptions(['stream' => true])
                    ->post($url);
            } else {
                // 正常模式：使用数组，Laravel会自动转为JSON
                $response = Http::withHeaders($headers)
                    ->timeout($this->timeout)
                    ->connectTimeout($this->connectTimeout)
                    ->withOptions(['stream' => true])
                    ->post($url, $body);
            }

            // 检查响应状态码
            if (! $response->ok()) {
                $this->recordFailure();
                throw $this->createErrorFromResponse($response);
            }

            $this->recordSuccess();

            $buffer = '';
            $stream = $response->toPsrResponse()->getBody();

            // 检查响应是否为非流式 JSON（某些上游 API 可能忽略 stream 参数）
            $contentType = $response->header('Content-Type') ?? '';
            $isJsonResponse = str_contains($contentType, 'application/json') &&
                              ! str_contains($contentType, 'text/event-stream');

            // 记录流式响应开始
            Log::debug('Stream response started', [
                'url' => $url,
                'content_type' => $contentType,
                'is_json_response' => $isJsonResponse,
            ]);

            // 如果是非流式 JSON 响应，直接读取完整内容并转换为流式块
            if ($isJsonResponse) {
                Log::warning('Upstream returned non-stream JSON response for stream request', [
                    'content_type' => $contentType,
                ]);

                // 读取完整响应体
                while (! $stream->eof()) {
                    $buffer .= $stream->read(8192);
                }

                // 尝试解析为非流式响应并转换为流式块
                $parsed = $this->parseNonStreamAsChunk($buffer);
                if ($parsed !== null) {
                    yield $parsed;
                }

                return;
            }

            // 流式读取超时设置（秒）- 首个数据块的最大等待时间
            $streamReadTimeout = 30;
            $lastDataTime = microtime(true);
            $firstChunkReceived = false;
            $totalChunks = 0;

            // 逐块读取流式响应
            while (! $stream->eof()) {
                // 检查读取超时
                $elapsed = microtime(true) - $lastDataTime;
                if ($elapsed > $streamReadTimeout) {
                    Log::error('Stream read timeout', [
                        'url' => $url,
                        'timeout_seconds' => $streamReadTimeout,
                        'first_chunk_received' => $firstChunkReceived,
                        'total_chunks' => $totalChunks,
                        'buffer_length' => strlen($buffer),
                    ]);
                    throw ProviderException::networkError(
                        "Stream read timeout after {$streamReadTimeout} seconds without data"
                    );
                }

                $chunk = $stream->read(1024);
                $buffer .= $chunk;

                // 记录首个数据块
                if (! $firstChunkReceived && strlen($chunk) > 0) {
                    $firstChunkReceived = true;
                    Log::debug('Stream first chunk received', [
                        'url' => $url,
                        'chunk_size' => strlen($chunk),
                        'wait_time_ms' => round((microtime(true) - $lastDataTime) * 1000, 2),
                    ]);
                }

                // 按双换行符分割事件
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $rawChunk = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    $parsed = $this->parseStreamChunk($rawChunk);
                    if ($parsed !== null) {
                        $totalChunks++;
                        $lastDataTime = microtime(true); // 更新最后数据时间
                        yield $parsed;
                    }
                }
            }

            // 处理剩余的缓冲区数据
            if (! empty($buffer)) {
                $parsed = $this->parseStreamChunk($buffer);
                if ($parsed !== null) {
                    $totalChunks++;
                    yield $parsed;
                }
            }

            // 记录流式响应结束
            Log::debug('Stream response completed', [
                'url' => $url,
                'total_chunks' => $totalChunks,
                'first_chunk_received' => $firstChunkReceived,
            ]);
        } catch (ConnectionException $e) {
            $this->recordFailure();
            throw ProviderException::networkError($e->getMessage(), $e);
        }
    }

    /**
     * 将非流式响应转换为流式块
     *
     * 用于处理上游 API 忽略 stream 参数返回非流式响应的情况
     *
     * @param  string  $rawResponse  原始 JSON 响应
     */
    protected function parseNonStreamAsChunk(string $rawResponse): ?ProviderStreamChunk
    {
        $data = json_decode($rawResponse, true);
        if ($data === null) {
            return null;
        }

        // 从非流式响应提取信息
        $id = $data['id'] ?? null;
        $model = $data['model'] ?? null;
        $finishReason = null;
        $content = '';
        $usage = null;
        $toolCalls = null;

        // 提取 choices
        $choices = $data['choices'] ?? [];
        $choice = $choices[0] ?? [];

        // 提取内容（非流式响应使用 message.content）
        if (isset($choice['message'])) {
            $content = $choice['message']['content'] ?? '';
            if (isset($choice['message']['tool_calls'])) {
                $toolCalls = $choice['message']['tool_calls'];
            }
        } elseif (isset($data['content'])) {
            // 某些 API 直接返回 content 字段
            $content = $data['content'];
        }

        if (isset($choice['finish_reason'])) {
            $finishReason = $choice['finish_reason'];
        } elseif (isset($data['finish_reason'])) {
            $finishReason = $data['finish_reason'];
        }

        // 提取 usage
        if (isset($data['usage'])) {
            $usage = TokenUsage::fromOpenAI($data['usage']);
        }

        return new ProviderStreamChunk(
            event: 'done',
            data: $data,
            delta: $content,
            id: $id,
            model: $model,
            finishReason: $finishReason,
            usage: $usage,
            toolCalls: $toolCalls,
        );
    }

    /**
     * 执行 HTTP 请求
     */
    protected function executeRequest(ProviderRequest $request): ProviderResponse
    {
        // 检查是否开启了 body 透传
        if ($request->rawBodyString !== null) {
            $body = $request->rawBodyString;
        } else {
            $body = $this->buildRequestBody($request);
        }

        $endpoint = $this->getEndpoint($request);
        $url = $this->buildUrl($endpoint, $request->queryString);
        $headers = $this->getHeaders();

        // 存储实际请求信息
        $this->lastRequestInfo = new ActualRequestInfo(
            url: $url,
            path: $endpoint,
            headers: $headers,
            body: is_string($body) ? json_decode($body, true) ?? $body : $body,
        );

        // 根据 body 类型选择发送方式
        if (is_string($body)) {
            // Body 透传模式：使用原始字符串作为请求体
            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->withBody($body, 'application/json')
                ->post($url);
        } else {
            // 正常模式：使用数组，Laravel会自动转为JSON
            $response = Http::withHeaders($headers)
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
     * 从响应创建错误异常
     */
    protected function createErrorFromResponse($response): ProviderException
    {
        $statusCode = $response->status();
        $body = $response->json();

        // 尝试多种错误格式获取错误消息
        // 1. OpenAI 格式: {"error":{"message":"..."}}
        // 2. 硅基流动格式: {"code":20015,"message":"..."}
        // 3. 其他格式: {"message":"..."}
        $errorMessage = $body['error']['message']
            ?? $body['message']
            ?? $body['error']
            ?? $response->body()
            ?? "HTTP {$statusCode} error";

        // 如果响应体为空，尝试从原始响应获取
        if (empty($errorMessage) || $errorMessage === "HTTP {$statusCode} error") {
            $rawBody = $response->toPsrResponse()?->getBody()?->getContents();
            if ($rawBody) {
                $errorMessage = $rawBody;
            }
        }

        Log::error('Provider error response', [
            'status' => $statusCode,
            'body' => $body,
            'raw_body' => $response->body(),
            'error_message' => $errorMessage,
        ]);

        $this->lastErrorMessage = is_string($errorMessage) ? $errorMessage : json_encode($errorMessage);

        // 对于404错误，特别处理模型不存在的错误消息
        if ($statusCode === 404) {
            // 尝试从错误消息中提取模型名称
            $modelName = $this->extractModelNameFromErrorMessage($this->lastErrorMessage);
            if ($modelName) {
                return ProviderException::modelNotFound($modelName, $body);
            }

            // 如果无法提取模型名称，但错误消息看起来是关于模型不存在的
            // 例如包含"模型"或"Model"关键字
            if (preg_match('/模型|Model/i', $this->lastErrorMessage)) {
                // 使用"unknown"作为模型名称，避免产生奇怪的错误消息
                return ProviderException::modelNotFound('unknown', $body);
            }
        }

        return match ($statusCode) {
            401 => ProviderException::authError($this->lastErrorMessage, $body),
            403 => ProviderException::authError($this->lastErrorMessage, $body),
            429 => ProviderException::rateLimit($this->lastErrorMessage, $body),
            400 => ProviderException::invalidRequest($this->lastErrorMessage, $body),
            404 => ProviderException::modelNotFound($this->lastErrorMessage, $body),
            default => ProviderException::serverError($this->lastErrorMessage, $statusCode, $body),
        };
    }

    /**
     * 从错误消息中提取模型名称
     */
    protected function extractModelNameFromErrorMessage(string $errorMessage): ?string
    {
        // 处理中文错误消息格式："模型 stepfun-ai/Step-3.5-Flash 无效"
        if (preg_match('/模型\s+([a-zA-Z0-9_\-\.\/]+)\s+无效/', $errorMessage, $matches)) {
            return $matches[1];
        }

        // 处理英文错误消息格式："Model 'stepfun-ai/Step-3.5-Flash' not found"
        if (preg_match("/Model '([^']+)' not found/", $errorMessage, $matches)) {
            return $matches[1];
        }

        // 处理英文错误消息格式："Model does not exist"
        if (str_contains($errorMessage, 'Model does not exist')) {
            // 无法提取具体模型名称，返回null
            return null;
        }

        return null;
    }

    /**
     * 检查熔断器状态
     *
     * @throws ProviderException 当熔断器打开时
     */
    protected function checkCircuitBreaker(): void
    {
        if ($this->circuitState === 'open') {
            $elapsed = time() - ($this->circuitOpenTime ?? 0);

            // 检查是否可以尝试半开状态
            if ($elapsed >= $this->circuitResetTimeout) {
                $this->circuitState = 'half-open';
            } else {
                throw ProviderException::circuitOpen($this->getProviderName());
            }
        }
    }

    /**
     * 记录失败（用于熔断器）
     */
    protected function recordFailure(): void
    {
        $this->circuitFailures++;

        if ($this->circuitFailures >= $this->circuitFailureThreshold) {
            $this->circuitState = 'open';
            $this->circuitOpenTime = time();

            Log::warning("Circuit breaker opened for provider: {$this->getProviderName()}");
        }
    }

    /**
     * 记录成功（用于熔断器）
     */
    protected function recordSuccess(): void
    {
        $this->circuitFailures = 0;
        $this->circuitState = 'closed';
        $this->lastErrorMessage = null;
    }

    /**
     * 健康检查
     *
     * 通过获取模型列表来验证供应商是否可用
     */
    public function healthCheck(): bool
    {
        try {
            $models = $this->getModels();

            return ! empty($models);
        } catch (\Throwable $e) {
            $this->lastErrorMessage = $e->getMessage();

            return false;
        }
    }

    /**
     * 检查供应商是否可用
     */
    public function isAvailable(): bool
    {
        return $this->circuitState !== 'open' && ! empty($this->apiKey);
    }

    /**
     * 获取最后一次错误消息
     */
    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    /**
     * 获取最后一次实际请求信息
     */
    public function getLastRequestInfo(): ?ActualRequestInfo
    {
        return $this->lastRequestInfo;
    }

    /**
     * 获取配置项
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * 安全解析 JSON
     */
    protected function safeJsonDecode(string $json, ?array $default = null): ?array
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $default;
        }
    }
}
