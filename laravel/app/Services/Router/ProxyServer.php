<?php

namespace App\Services\Router;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\ChannelRequestLog;
use App\Models\RequestLog;
use App\Models\ResponseLog;
use App\Services\ChannelAffinity\ChannelAffinityService;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\Protocol\DTO\StandardRequest;
use App\Services\Protocol\DTO\StandardResponse;
use App\Services\Protocol\DTO\StandardToolCall;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\ProviderManager;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 代理服务器
 *
 * 核心 AI 请求代理服务，负责：
 * - 接收客户端请求
 * - 协议转换（OpenAI/Anthropic 等）
 * - 渠道选择与路由
 * - 流式/非流式响应处理
 * - 日志记录与审计
 * - 自动重试与故障转移
 * - 渠道亲和性路由
 */
class ProxyServer
{
    /**
     * 协议转换器
     */
    protected ProtocolConverter $protocolConverter;

    /**
     * 供应商管理器
     */
    protected ProviderManager $providerManager;

    /**
     * 渠道路由服务
     */
    protected ChannelRouterService $channelRouter;

    /**
     * 编码状态服务
     */
    protected ChannelCodingStatusService $codingStatusService;

    /**
     * 渠道亲和性服务
     */
    protected ChannelAffinityService $affinityService;

    /**
     * 当前请求 ID
     */
    protected ?string $requestId = null;

    /**
     * 请求开始时间
     */
    protected float $startTime;

    /**
     * 首个 Token 延迟（毫秒）
     */
    protected ?int $firstTokenMs = null;

    /**
     * 当前选中的渠道
     */
    protected ?Channel $selectedChannel = null;

    /**
     * 当前分组名称
     */
    protected ?string $currentGroup = null;

    /**
     * 已失败的渠道列表（用于故障转移）
     */
    protected array $failedChannels = [];

    /**
     * 最大重试次数
     */
    protected int $maxRetries;

    /**
     * 可重试的 HTTP 状态码
     */
    protected array $retryableStatusCodes = [429, 500, 502, 503, 504];

    /**
     * 是否启用故障转移
     */
    protected bool $enableFailover;

    /**
     * 当前审计日志实例
     */
    protected ?AuditLog $auditLog = null;

    /**
     * 当前渠道请求日志实例
     */
    protected ?ChannelRequestLog $channelRequestLog = null;

    /**
     * 构造函数
     *
     * @param  ProtocolConverter  $protocolConverter  协议转换器
     * @param  ProviderManager  $providerManager  供应商管理器
     * @param  ChannelRouterService  $channelRouter  渠道路由服务
     * @param  ChannelCodingStatusService  $codingStatusService  编码状态服务
     * @param  ChannelAffinityService  $affinityService  渠道亲和性服务
     */
    public function __construct(
        ProtocolConverter $protocolConverter,
        ProviderManager $providerManager,
        ChannelRouterService $channelRouter,
        ChannelCodingStatusService $codingStatusService,
        ChannelAffinityService $affinityService
    ) {
        $this->protocolConverter = $protocolConverter;
        $this->providerManager = $providerManager;
        $this->channelRouter = $channelRouter;
        $this->codingStatusService = $codingStatusService;
        $this->affinityService = $affinityService;
        $this->startTime = microtime(true);
        $this->maxRetries = config('router.max_retry', 3);
        $this->enableFailover = config('router.enable_failover', true);
    }

    /**
     * 执行代理请求
     *
     * @param  Request  $request  HTTP 请求
     * @param  string  $protocol  源协议类型（openai/anthropic）
     * @return array|Generator 响应数组或流式生成器
     *
     * @throws \Exception
     */
    public function proxy(Request $request, string $protocol = 'openai'): array|Generator
    {
        $this->requestId = $request->attributes->get('request_id', Str::uuid()->toString());
        $this->startTime = microtime(true);
        $this->failedChannels = [];
        $this->selectedChannel = null;
        $this->auditLog = null;

        $rawRequest = $request->all();
        $isStream = $rawRequest['stream'] ?? false;

        // 请求开始时就创建审计日志（初始状态）
        $this->createInitialAuditLog($request, $rawRequest['model'] ?? null);

        $requestLog = $this->createRequestLog($request, $rawRequest, $protocol);

        $lastException = null;
        $attempt = 0;

        // 获取 API Key
        $apiKey = $request->attributes->get('api_key');

        while ($attempt <= $this->maxRetries) {
            try {
                // 标准化请求
                $standardRequest = $this->protocolConverter->normalizeRequest($rawRequest, $protocol);

                $this->updateRequestLogModel($requestLog, $standardRequest);

                // 验证模型是否在允许的列表中
                $this->validateModel($standardRequest->model, $request);

                // 应用 Key 级别的模型映射
                if ($apiKey && method_exists($apiKey, 'resolveModel')) {
                    $standardRequest->model = $apiKey->resolveModel($standardRequest->model);
                }

                // 选择渠道（首次或故障转移时）
                if ($this->selectedChannel === null || $attempt > 0) {
                    $this->selectedChannel = $this->selectChannelWithFallback($standardRequest->model, $apiKey);
                    // 选择渠道后立即更新审计日志
                    $this->updateAuditLog([
                        'channel_id' => $this->selectedChannel?->id,
                        'channel_name' => $this->selectedChannel?->name,
                        'model' => $standardRequest->model,
                    ]);
                }

                if ($this->selectedChannel === null) {
                    throw new \RuntimeException('No available channel for model: '.$standardRequest->model);
                }

                // 解析实际模型名称
                $actualModel = $this->channelRouter->resolveModel($standardRequest->model, $this->selectedChannel);

                // 获取渠道协议
                $channelProtocol = $this->getChannelProtocol($this->selectedChannel);

                // 构建供应商请求
                $providerRequest = $this->buildProviderRequest($standardRequest, $this->selectedChannel, $channelProtocol, $actualModel);

                // 更新请求日志，记录渠道信息
                $this->updateRequestLogForChannel($requestLog, $this->selectedChannel, $actualModel);

                $provider = $this->providerManager->getForChannel($this->selectedChannel, $request->headers->all());

                // 创建渠道请求日志（记录发送到渠道的请求）
                $this->createInitialChannelRequestLog($requestLog, $this->selectedChannel, $providerRequest, $channelProtocol, $provider);

                // 根据是否流式请求分别处理
                if ($isStream) {
                    return $this->handleStreamRequest($request, $standardRequest, $providerRequest, $provider, $protocol, $channelProtocol, $requestLog);
                }

                return $this->handleNonStreamRequest($request, $standardRequest, $providerRequest, $provider, $protocol, $channelProtocol, $requestLog);
            } catch (\Exception $e) {
                $lastException = $e;
                $statusCode = $this->getStatusCode($e);

                // 检查是否应该重试
                if (! $this->shouldRetry($e, $attempt)) {
                    break;
                }

                // 标记当前渠道失败
                if ($this->selectedChannel) {
                    $this->failedChannels[] = $this->selectedChannel->id;
                    $this->channelRouter->markChannelFailed($this->selectedChannel, $e->getMessage());
                    Log::warning('Channel failed, will retry', [
                        'request_id' => $this->requestId,
                        'channel_id' => $this->selectedChannel->id,
                        'attempt' => $attempt + 1,
                        'max_retries' => $this->maxRetries,
                        'error' => $e->getMessage(),
                    ]);
                    $this->selectedChannel = null;
                }

                $attempt++;

                // 添加指数退避延迟
                if ($attempt <= $this->maxRetries) {
                    usleep(min(100000 * pow(2, $attempt - 1), 1000000)); // 最大延迟 1 秒
                }
            }
        }

        // 所有重试都失败了
        $this->handleError($lastException, $request, $requestLog);

        throw $lastException;
    }

    /**
     * 选择渠道（支持故障转移）
     *
     * @param  string  $model  模型名称
     * @param  ApiKey|null  $apiKey  API Key 实例
     * @return Channel|null 选中的渠道
     */
    protected function selectChannelWithFallback(string $model, ?ApiKey $apiKey = null): ?Channel
    {
        if (empty($this->failedChannels)) {
            $affinityResult = $this->affinityService->getPreferredChannel(
                request(),
                $model,
                $this->currentGroup ?? null
            );

            if ($affinityResult->isHit && $affinityResult->channel) {
                Log::info('Using affinity channel', [
                    'request_id' => $this->requestId,
                    'channel_id' => $affinityResult->channel->id,
                    'rule_id' => $affinityResult->rule?->id,
                    'key_hash' => $affinityResult->keyHash,
                ]);

                return $affinityResult->channel;
            }

            return $this->channelRouter->selectChannel($model, ['api_key' => $apiKey]);
        }

        if ($this->enableFailover) {
            $fallbackChannel = $this->channelRouter->getFallbackChannelForRetry($model, $this->failedChannels, $apiKey);
            if ($fallbackChannel) {
                Log::info('Using fallback channel', [
                    'request_id' => $this->requestId,
                    'channel_id' => $fallbackChannel->id,
                    'failed_channels' => $this->failedChannels,
                ]);

                return $fallbackChannel;
            }
        }

        return null;
    }

    /**
     * 检查是否应该重试
     *
     * @param  \Exception  $e  异常
     * @param  int  $attempt  当前尝试次数
     * @return bool 是否应该重试
     */
    protected function shouldRetry(\Exception $e, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        if ($this->affinityService->shouldSkipRetry(request())) {
            return false;
        }

        $statusCode = $this->getStatusCode($e);

        if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
            return false;
        }

        return in_array($statusCode, $this->retryableStatusCodes, true) || $statusCode >= 500;
    }

    /**
     * 处理非流式请求
     *
     * @param  Request  $httpRequest  HTTP 请求
     * @param  StandardRequest  $standardRequest  标准化请求
     * @param  ProviderRequest  $providerRequest  供应商请求
     * @param  mixed  $provider  供应商实例
     * @param  string  $sourceProtocol  源协议
     * @param  string  $targetProtocol  目标协议
     * @param  RequestLog  $requestLog  请求日志
     * @return array 响应数组
     */
    protected function handleNonStreamRequest(
        Request $httpRequest,
        StandardRequest $standardRequest,
        ProviderRequest $providerRequest,
        $provider,
        string $sourceProtocol,
        string $targetProtocol,
        RequestLog $requestLog
    ): array {
        $providerResponse = $provider->send($providerRequest);

        // 从 Provider 获取实际请求信息并更新日志
        $this->updateChannelRequestLogFromProvider($provider);

        $latencyMs = $this->calculateLatency();

        // 非流式请求的首字时间等于总延迟
        $this->firstTokenMs = $latencyMs;

        // 更新渠道请求日志，记录响应信息（非流式请求的 TTFB 等于总延迟）
        $this->updateChannelRequestLogForResponse($providerResponse, $latencyMs, true, $latencyMs);

        $standardResponse = $this->normalizeProviderResponse($providerResponse, $targetProtocol);

        $response = $this->buildResponse($standardResponse, $sourceProtocol);

        $auditLog = $this->createAuditLog($httpRequest, $standardRequest, $standardResponse, $latencyMs, $providerResponse);

        $this->createResponseLog($requestLog, $response, $providerResponse, $latencyMs, $auditLog?->id);

        $this->recordUsage($standardRequest, $standardResponse);

        $this->affinityService->recordAffinity(
            $httpRequest,
            $this->selectedChannel,
            $standardRequest->model,
            $this->currentGroup
        );

        return $response;
    }

    /**
     * 处理流式请求
     *
     * @param  Request  $httpRequest  HTTP 请求
     * @param  StandardRequest  $standardRequest  标准化请求
     * @param  ProviderRequest  $providerRequest  供应商请求
     * @param  mixed  $provider  供应商实例
     * @param  string  $sourceProtocol  源协议
     * @param  string  $targetProtocol  目标协议
     * @param  RequestLog  $requestLog  请求日志
     * @return Generator 流式响应生成器
     */
    protected function handleStreamRequest(
        Request $httpRequest,
        StandardRequest $standardRequest,
        ProviderRequest $providerRequest,
        $provider,
        string $sourceProtocol,
        string $targetProtocol,
        RequestLog $requestLog
    ): Generator {
        $stream = $provider->sendStream($providerRequest);

        // 从 Provider 获取实际请求信息并更新日志
        $this->updateChannelRequestLogFromProvider($provider);

        $fullContent = '';
        $usage = null;
        $finishReason = null;
        $firstTokenTime = null;
        $ttfbMs = null;
        $toolCalls = [];
        $reasoningContent = '';
        Log::debug('handleStreamRequest  ', []);

        foreach ($stream as $chunk) {
            // 记录首个 Token 时间（TTFB）
            if ($firstTokenTime === null) {
                $this->firstTokenMs = (int) ((microtime(true) - $this->startTime) * 1000);
                $firstTokenTime = microtime(true);
                $ttfbMs = $this->firstTokenMs;
            }

            // 记录首个响应块时间用于 TTFB
            if ($this->channelRequestLog && $ttfbMs === null) {
                $ttfbMs = (int) ((microtime(true) - $this->startTime) * 1000);
            }

            $standardEvent = $this->parseStreamChunk($chunk, $targetProtocol);
            if ($standardEvent === null) {
                Log::info('standardEvent nullcontinue ', []);

                continue;
            }

            // 累积内容
            if ($standardEvent->contentDelta) {
                $fullContent .= $standardEvent->contentDelta;
            }

            // 累积推理内容
            if ($standardEvent->reasoningDelta) {
                $reasoningContent .= $standardEvent->reasoningDelta;
            }

            // 累积工具调用（流式响应中 tool_calls 是增量发送的）
            if ($standardEvent->toolCall) {
                $this->accumulateToolCall($toolCalls, $standardEvent->toolCall);
            }

            if ($standardEvent->usage) {
                $usage = $standardEvent->usage;
            }

            if ($standardEvent->finishReason) {
                $finishReason = $standardEvent->finishReason;
            }

            $convertedChunk = $this->convertStreamChunk($standardEvent, $sourceProtocol);

            yield $convertedChunk;
        }

        $latencyMs = $this->calculateLatency();

        // 构建完整响应用于日志记录
        $standardResponse = new StandardResponse(
            id: uniqid('chatcmpl-'),
            content: $fullContent,
            model: $standardRequest->model,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: ! empty($toolCalls) ? $toolCalls : null,
            reasoningContent: $reasoningContent ?: null,
        );

        // 更新渠道请求日志，记录响应信息
        $this->updateChannelRequestLogForResponse($standardResponse, $latencyMs, true, $ttfbMs);

        $auditLog = $this->createAuditLog($httpRequest, $standardRequest, $standardResponse, $latencyMs, null, true);

        $this->createResponseLog($requestLog, ['stream' => true, 'content' => $fullContent], null, $latencyMs, $auditLog?->id, true, $usage, $finishReason);

        $this->recordUsage($standardRequest, $standardResponse);

        $this->affinityService->recordAffinity(
            $httpRequest,
            $this->selectedChannel,
            $standardRequest->model,
            $this->currentGroup
        );
    }

    /**
     * 累积工具调用（流式响应中 tool_calls 是增量发送的）
     *
     * @param  array  $toolCalls  已累积的工具调用数组
     * @param  StandardToolCall  $newCall  新的工具调用增量
     */
    protected function accumulateToolCall(array &$toolCalls, StandardToolCall $newCall): void
    {
        $index = $newCall->index ?? count($toolCalls);

        if (! isset($toolCalls[$index])) {
            $toolCalls[$index] = $newCall;
        } else {
            // 累积 arguments 字符串
            $toolCalls[$index]->arguments .= $newCall->arguments;

            // 更新其他字段（如果新块中有值）
            if ($newCall->id) {
                $toolCalls[$index]->id = $newCall->id;
            }
            if ($newCall->name) {
                $toolCalls[$index]->name = $newCall->name;
            }
        }
    }

    /**
     * 创建请求日志
     *
     * @param  Request  $request  HTTP 请求
     * @param  array  $rawRequest  原始请求数据
     * @param  string  $protocol  协议类型
     * @return RequestLog 请求日志实例
     */
    protected function createRequestLog(Request $request, array $rawRequest, string $protocol): RequestLog
    {
        return RequestLog::create([
            'request_id' => $this->requestId,
            'run_unid' => defined('RUN_UNID') ? RUN_UNID : null,
            'method' => $request->method(),
            'path' => $request->path(),
            'query_string' => $request->getQueryString(),
            'headers' => $this->filterSensitiveHeaders($request->headers->all()),
            'content_type' => $request->header('Content-Type'),
            'content_length' => strlen($request->getContent()),
            'body_text' => $this->truncateBody($request->getContent()),
            'model' => $rawRequest['model'] ?? null,
            'model_params' => $this->extractModelParams($rawRequest),
            'messages' => $rawRequest['messages'] ?? null,
            'prompt' => $this->extractPrompt($rawRequest),
            'sensitive_fields' => [],
            'has_sensitive' => false,
            'metadata' => [
                'protocol' => $protocol,
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }

    /**
     * 更新请求日志的模型信息
     *
     * @param  RequestLog  $log  请求日志
     * @param  StandardRequest  $request  标准化请求
     */
    protected function updateRequestLogModel(RequestLog $log, StandardRequest $request): void
    {
        $log->update([
            'model' => $request->model,
            'model_params' => [
                'temperature' => $request->temperature,
                'top_p' => $request->topP,
                'max_tokens' => $request->maxTokens,
                'stream' => $request->stream,
            ],
        ]);
    }

    /**
     * 更新请求日志的渠道信息和发往渠道的请求参数
     *
     * @param  RequestLog  $log  请求日志
     * @param  Channel  $channel  渠道实例
     * @param  string  $actualModel  实际模型名称
     */
    protected function updateRequestLogForChannel(RequestLog $log, Channel $channel, string $actualModel): void
    {
        $log->update([
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'upstream_model' => $actualModel,
        ]);
    }

    /**
     * 创建初始渠道请求日志
     *
     * 记录发送到渠道的请求信息
     *
     * @param  RequestLog  $requestLog  请求日志
     * @param  Channel  $channel  渠道实例
     * @param  ProviderRequest  $providerRequest  供应商请求
     * @param  string  $channelProtocol  渠道协议
     * @param  mixed  $provider  供应商实例
     */
    protected function createInitialChannelRequestLog(RequestLog $requestLog, Channel $channel, ProviderRequest $providerRequest, string $channelProtocol, $provider): void
    {
        $baseUrl = rtrim($channel->base_url, '/');
        $path = $this->buildEndpointPath($channelProtocol);
        $fullUrl = $baseUrl.$path;

        // 从 Provider 获取实际的请求头（包含穿透的 headers）
        $headers = $this->getProviderHeaders($provider);

        // 根据渠道协议使用正确的格式保存请求体
        if ($channelProtocol === 'anthropic') {
            $requestBody = $providerRequest->toAnthropicFormat();
        } else {
            $requestBody = $providerRequest->toOpenAIFormat();
        }

        $this->channelRequestLog = ChannelRequestLog::create([
            'audit_log_id' => $this->auditLog?->id,
            'request_log_id' => $requestLog->id,
            'request_id' => $this->requestId,
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'provider' => $channel->provider,
            'method' => 'POST',
            'path' => $path,
            'base_url' => $baseUrl,
            'full_url' => $fullUrl,
            'request_headers' => $this->filterSensitiveHeaders($headers),
            'request_body' => $requestBody,
            'request_size' => strlen(json_encode($requestBody)),
            'sent_at' => now(),
        ]);
    }

    /**
     * 从 Provider 更新渠道请求日志
     *
     * 获取 Provider 实际发送的请求信息并更新日志
     *
     * @param  mixed  $provider  供应商实例
     */
    protected function updateChannelRequestLogFromProvider($provider): void
    {
        if (! $this->channelRequestLog) {
            return;
        }

        // 从 Provider 获取实际请求信息
        $requestInfo = $provider->getLastRequestInfo();
        if (! $requestInfo) {
            return;
        }

        $this->channelRequestLog->update([
            'path' => $requestInfo->path,
            'full_url' => $requestInfo->url,
            'request_headers' => $requestInfo->headers,
            'request_body' => $requestInfo->body,
            'request_size' => strlen(json_encode($requestInfo->body)),
        ]);
    }

    /**
     * 更新渠道请求日志的响应信息
     *
     * @param  ProviderResponse|StandardResponse  $response  供应商响应
     * @param  int  $latencyMs  延迟毫秒数
     * @param  bool  $isSuccess  是否成功
     * @param  int|null  $ttfbMs  首字节时间
     */
    protected function updateChannelRequestLogForResponse(ProviderResponse|StandardResponse $response, int $latencyMs, bool $isSuccess, ?int $ttfbMs = null): void
    {
        if (! $this->channelRequestLog) {
            return;
        }

        $responseData = $response instanceof ProviderResponse ? $response->rawResponse : [
            'id' => $response->id,
            'model' => $response->model,
            'content' => $response->content,
            'usage' => $response->usage,
            'finish_reason' => $response->finishReason,
            'tool_calls' => $response->toolCalls ? array_map(
                fn ($tc) => $tc->toOpenAI(),
                $response->toolCalls
            ) : null,
        ];

        $this->channelRequestLog->update([
            'response_status' => 200,
            'response_headers' => ['content-type' => 'application/json'],
            'response_body' => $responseData,
            'response_size' => strlen(json_encode($responseData)),
            'latency_ms' => $latencyMs,
            'ttfb_ms' => $ttfbMs,
            'is_success' => $isSuccess,
            'usage' => $response->usage ? [
                'prompt_tokens' => $response->usage->promptTokens ?? 0,
                'completion_tokens' => $response->usage->completionTokens ?? 0,
                'total_tokens' => $response->usage->totalTokens ?? 0,
            ] : null,
        ]);
    }

    /**
     * 创建初始审计日志
     *
     * 在请求开始时立即创建，确保即使请求失败也有记录
     *
     * @param  Request  $httpRequest  HTTP 请求
     * @param  string|null  $model  请求模型
     */
    protected function createInitialAuditLog(Request $httpRequest, ?string $model): void
    {
        $user = $httpRequest->user();
        $apiKey = $httpRequest->attributes->get('api_key');

        $this->auditLog = AuditLog::create([
            'user_id' => $user?->id,
            'username' => $user?->name,
            'api_key_id' => $apiKey?->id,
            'api_key_name' => $apiKey?->name,
            'cached_key_prefix' => $apiKey?->key_prefix,
            'request_id' => $this->requestId,
            'run_unid' => defined('RUN_UNID') ? RUN_UNID : null,
            'request_type' => AuditLog::REQUEST_TYPE_CHAT,
            'model' => $model,
            'status_code' => 0,
            'latency_ms' => 0,
            'first_token_ms' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost' => 0,
            'quota' => 0,
            'billing_source' => AuditLog::BILLING_SOURCE_QUOTA,
            'is_stream' => false,
            'client_ip' => $httpRequest->ip(),
            'user_agent' => $httpRequest->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * 更新审计日志
     *
     * @param  array  $data  要更新的数据
     */
    protected function updateAuditLog(array $data): void
    {
        if ($this->auditLog) {
            $this->auditLog->update($data);
        }
    }

    /**
     * 创建审计日志
     *
     * @param  Request  $httpRequest  HTTP 请求
     * @param  StandardRequest  $standardRequest  标准化请求
     * @param  StandardResponse  $standardResponse  标准化响应
     * @param  int  $latencyMs  延迟毫秒数
     * @param  ProviderResponse|null  $providerResponse  供应商响应
     * @param  bool  $isStream  是否流式请求
     * @return AuditLog 审计日志实例
     */
    protected function createAuditLog(
        Request $httpRequest,
        StandardRequest $standardRequest,
        StandardResponse $standardResponse,
        int $latencyMs,
        ?ProviderResponse $providerResponse,
        bool $isStream = false
    ): AuditLog {
        $user = $httpRequest->user();
        $apiKey = $httpRequest->attributes->get('api_key');

        $usage = $standardResponse->usage;
        $cost = $this->calculateCost($standardRequest->model, $usage);

        $affinityInfo = $this->affinityService->getAffinityInfo($httpRequest);

        // 更新已存在的审计日志
        $this->updateAuditLog([
            'channel_id' => $this->selectedChannel?->id,
            'channel_name' => $this->selectedChannel?->name,
            'model' => $standardRequest->model,
            'actual_model' => $standardResponse->model ?? $standardRequest->model,
            'prompt_tokens' => $usage?->promptTokens ?? 0,
            'completion_tokens' => $usage?->completionTokens ?? 0,
            'total_tokens' => $usage?->totalTokens ?? 0,
            'cost' => $cost,
            'quota' => $cost,
            'billing_source' => AuditLog::BILLING_SOURCE_QUOTA,
            'status_code' => 200,
            'latency_ms' => $latencyMs,
            'first_token_ms' => $this->firstTokenMs ?? 0,
            'is_stream' => $isStream,
            'finish_reason' => $standardResponse->finishReason,
            'channel_affinity' => $affinityInfo,
        ]);

        return $this->auditLog;
    }

    /**
     * 创建响应日志
     *
     * @param  RequestLog  $requestLog  请求日志
     * @param  array  $response  响应数据
     * @param  ProviderResponse|null  $providerResponse  供应商响应
     * @param  int  $latencyMs  延迟毫秒数
     * @param  int|null  $auditLogId  审计日志 ID
     * @param  bool  $isStream  是否流式请求
     * @param  mixed  $usage  Token 使用量
     * @param  string|null  $finishReason  结束原因
     * @return ResponseLog 响应日志实例
     */
    protected function createResponseLog(
        RequestLog $requestLog,
        array $response,
        ?ProviderResponse $providerResponse,
        int $latencyMs,
        ?int $auditLogId = null,
        bool $isStream = false,
        $usage = null,
        ?string $finishReason = null
    ): ResponseLog {
        return ResponseLog::create([
            'audit_log_id' => $auditLogId ?? $requestLog->audit_log_id,
            'request_id' => $this->requestId,
            'request_log_id' => $requestLog->id,
            'status_code' => 200,
            'headers' => ['content-type' => 'application/json'],
            'content_type' => 'application/json',
            'content_length' => strlen(json_encode($response)),
            'body_text' => $this->truncateBody(json_encode($response)),
            'response_type' => $isStream ? 'stream' : 'json',
            'finish_reason' => $finishReason,
            'usage' => $usage ? [
                'prompt_tokens' => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
                'total_tokens' => $usage->totalTokens ?? 0,
            ] : null,
            'upstream_provider' => $this->selectedChannel?->provider,
            'upstream_model' => $providerResponse?->model,
            'upstream_latency_ms' => $latencyMs,
        ]);
    }

    /**
     * 处理错误
     *
     * 记录错误日志并更新审计记录
     *
     * @param  \Exception  $e  异常实例
     * @param  Request  $request  HTTP 请求
     * @param  RequestLog  $requestLog  请求日志
     */
    protected function handleError(\Exception $e, Request $request, RequestLog $requestLog): void
    {
        $latencyMs = $this->calculateLatency();

        // 更新审计日志记录错误信息
        $this->updateAuditLog([
            'channel_id' => $this->selectedChannel?->id,
            'channel_name' => $this->selectedChannel?->name,
            'status_code' => $this->getStatusCode($e),
            'latency_ms' => $latencyMs,
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
        ]);

        // 更新渠道请求日志记录错误信息
        if ($this->channelRequestLog) {
            $this->channelRequestLog->update([
                'response_status' => $this->getStatusCode($e),
                'is_success' => false,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'latency_ms' => $latencyMs,
            ]);
        }

        Log::error('Proxy request failed', [
            'request_id' => $this->requestId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * 获取渠道协议类型
     *
     * @param  Channel  $channel  渠道实例
     * @return string 协议类型（openai/anthropic）
     */
    protected function getChannelProtocol(Channel $channel): string
    {
        $provider = $channel->provider;

        if (in_array($provider, ['anthropic', 'claude'])) {
            return 'anthropic';
        }

        return 'openai';
    }

    /**
     * 构建供应商请求
     *
     * @param  StandardRequest  $standardRequest  标准化请求
     * @param  Channel  $channel  渠道实例
     * @param  string  $targetProtocol  目标协议
     * @param  string  $actualModel  实际模型名称
     * @return ProviderRequest 供应商请求实例
     */
    protected function buildProviderRequest(StandardRequest $standardRequest, Channel $channel, string $targetProtocol, string $actualModel): ProviderRequest
    {
        if ($targetProtocol === 'anthropic') {
            // 获取渠道的 filter_thinking 配置，默认过滤 thinking 块
            $filterThinking = $channel->shouldFilterThinking();
            $requestData = $standardRequest->toAnthropic(true, $filterThinking);
        } else {
            $requestData = $standardRequest->toOpenAI();
        }

        $requestData['model'] = $actualModel;

        $providerRequest = ProviderRequest::fromArray($requestData);

        // 传递客户端请求的 query_string
        $providerRequest->queryString = request()->getQueryString();

        return $providerRequest;
    }

    /**
     * 构建 API 端点 URL
     *
     * @param  Channel  $channel  渠道实例
     * @param  StandardRequest  $request  标准化请求
     * @return string API 端点 URL
     */
    protected function buildEndpoint(Channel $channel, StandardRequest $request): string
    {
        $baseUrl = rtrim($channel->base_url, '/');

        $provider = strtolower($channel->provider);

        if (in_array($provider, ['anthropic', 'claude'])) {
            return $baseUrl.'/v1/messages';
        }

        return $baseUrl.'/v1/chat/completions';
    }

    /**
     * 从 Provider 获取请求头
     *
     * @param  mixed  $provider  供应商实例
     * @return array 请求头数组
     */
    protected function getProviderHeaders($provider): array
    {
        if (method_exists($provider, 'getHeaders')) {
            return $provider->getHeaders();
        }

        return [];
    }

    /**
     * 构建请求头
     *
     * @param  Channel  $channel  渠道实例
     * @return array 请求头数组
     */
    protected function buildHeaders(Channel $channel): array
    {
        $provider = strtolower($channel->provider);
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (in_array($provider, ['anthropic', 'claude'])) {
            $headers['x-api-key'] = $channel->api_key;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer '.$channel->api_key;
        }

        return $headers;
    }

    /**
     * 构建渠道请求头（用于日志记录）
     *
     * @param  Channel  $channel  渠道实例
     * @return array 请求头数组（已过滤敏感信息）
     */
    protected function buildChannelRequestHeaders(Channel $channel): array
    {
        $headers = $this->buildHeaders($channel);

        // 过滤敏感的 API 密钥信息
        return $this->filterSensitiveHeaders($headers);
    }

    /**
     * 构建渠道端点路径
     *
     * 注意：此方法返回的路径应与 Provider::getEndpoint() 保持一致
     * base_url 通常已包含 /v1 前缀，所以这里不应再添加
     *
     * @param  string  $protocol  协议类型
     * @return string 端点路径
     */
    protected function buildEndpointPath(string $protocol): string
    {
        if ($protocol === 'anthropic') {
            return '/messages';
        }

        return '/chat/completions';
    }

    /**
     * 标准化供应商响应
     *
     * @param  ProviderResponse  $response  供应商响应
     * @param  string  $protocol  协议类型
     * @return StandardResponse 标准化响应
     */
    protected function normalizeProviderResponse(ProviderResponse $response, string $protocol): StandardResponse
    {
        return $this->protocolConverter->normalizeResponse($response->rawResponse ?? [], $protocol);
    }

    /**
     * 构建响应
     *
     * @param  StandardResponse  $standardResponse  标准化响应
     * @param  string  $protocol  目标协议
     * @return array 响应数组
     */
    protected function buildResponse(StandardResponse $standardResponse, string $protocol): array
    {
        return $this->protocolConverter->denormalizeResponse($standardResponse, $protocol);
    }

    /**
     * 解析流式响应块
     *
     * @param  mixed  $chunk  原始响应块
     * @param  string  $protocol  协议类型
     * @return object|null 标准化流式事件
     */
    protected function parseStreamChunk($chunk, string $protocol): ?object
    {
        if ($chunk instanceof \App\Services\Provider\DTO\ProviderStreamChunk) {
            $toolCall = null;
            if (! empty($chunk->toolCalls)) {
                $tc = $chunk->toolCalls[0] ?? null;
                if ($tc) {
                    $toolCall = new \App\Services\Protocol\DTO\StandardToolCall(
                        id: $tc['id'] ?? '',
                        type: $tc['type'] ?? 'function',
                        name: $tc['function']['name'] ?? '',
                        arguments: $tc['function']['arguments'] ?? '',
                        index: $tc['index'] ?? null,
                    );
                }
            }

            // 确定事件类型
            $eventType = 'delta';
            if ($chunk->finishReason) {
                $eventType = 'finish';
            } elseif ($toolCall) {
                $eventType = 'tool_use';
            } elseif ($chunk->reasoningDelta) {
                $eventType = 'reasoning_delta';
            }

            return new \App\Services\Protocol\DTO\StandardStreamEvent(
                type: $eventType,
                id: $chunk->id ?: uniqid(),
                model: $chunk->model,
                contentDelta: $chunk->delta,
                reasoningDelta: $chunk->reasoningDelta,
                finishReason: $chunk->finishReason,
                toolCall: $toolCall,
                usage: $chunk->usage ? new \App\Services\Protocol\DTO\StandardUsage(
                    promptTokens: $chunk->usage->promptTokens,
                    completionTokens: $chunk->usage->completionTokens,
                    totalTokens: $chunk->usage->totalTokens,
                ) : null,
            );
        }

        $driver = $this->protocolConverter->driver($protocol);

        return $driver->parseStreamEvent($chunk);
    }

    /**
     * 转换流式响应块
     *
     * @param  object  $event  标准化流式事件
     * @param  string  $targetProtocol  目标协议
     * @return string 格式化的流式响应块
     */
    protected function convertStreamChunk(object $event, string $targetProtocol): string
    {
        $driver = $this->protocolConverter->driver($targetProtocol);

        return $driver->buildStreamChunk($event);
    }

    /**
     * 计算延迟时间
     *
     * @return int 延迟毫秒数
     */
    protected function calculateLatency(): int
    {
        return (int) ((microtime(true) - $this->startTime) * 1000);
    }

    /**
     * 计算请求成本
     *
     * @param  string  $model  模型名称
     * @param  mixed  $usage  Token 使用量
     * @return float 成本金额
     */
    protected function calculateCost(string $model, $usage): float
    {
        if (! $usage) {
            return 0.0;
        }

        $promptTokens = $usage->promptTokens ?? 0;
        $completionTokens = $usage->completionTokens ?? 0;

        $promptRate = 0.00001;
        $completionRate = 0.00003;

        // 根据模型类型调整费率
        if (str_contains(strtolower($model), 'gpt-4')) {
            $promptRate = 0.00003;
            $completionRate = 0.00006;
        } elseif (str_contains(strtolower($model), 'claude-3')) {
            $promptRate = 0.000015;
            $completionRate = 0.000075;
        }

        return ($promptTokens * $promptRate) + ($completionTokens * $completionRate);
    }

    /**
     * 记录使用量
     *
     * 用于编码账户的配额统计
     *
     * @param  StandardRequest  $request  标准化请求
     * @param  StandardResponse  $response  标准化响应
     */
    protected function recordUsage(StandardRequest $request, StandardResponse $response): void
    {
        if (! $this->selectedChannel || ! $this->selectedChannel->hasCodingAccount()) {
            return;
        }

        $usage = $response->usage;
        if (! $usage) {
            return;
        }

        $this->codingStatusService->recordUsage($this->selectedChannel, [
            'requests' => 1,
            'tokens_input' => $usage->promptTokens ?? 0,
            'tokens_output' => $usage->completionTokens ?? 0,
            'model' => $request->model,
        ]);
    }

    /**
     * 过滤敏感请求头
     *
     * @param  array  $headers  原始请求头
     * @return array 过滤后的请求头
     */
    protected function filterSensitiveHeaders(array $headers): array
    {
        $sensitiveKeys = [];

        return array_filter(
            $headers,
            fn ($key) => ! in_array(strtolower($key), $sensitiveKeys),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * 截断请求体
     *
     * 防止日志过大
     *
     * @param  string|null  $body  原始请求体
     * @param  int  $maxLength  最大长度
     * @return string|null 截断后的请求体
     */
    protected function truncateBody(?string $body, int $maxLength = 2097152): ?string
    {
        if (! $body) {
            return null;
        }

        if (strlen($body) <= $maxLength) {
            return $body;
        }

        return substr($body, 0, $maxLength).'...[truncated]';
    }

    /**
     * 提取模型参数
     *
     * @param  array  $request  请求数据
     * @return array 模型参数
     */
    protected function extractModelParams(array $request): array
    {
        $params = [];
        $keys = ['temperature', 'top_p', 'max_tokens', 'presence_penalty', 'frequency_penalty', 'n', 'stream'];

        foreach ($keys as $key) {
            if (isset($request[$key])) {
                $params[$key] = $request[$key];
            }
        }

        return $params;
    }

    /**
     * 提取提示词
     *
     * 从消息列表中提取文本内容
     *
     * @param  array  $request  请求数据
     * @return string|null 提示词文本
     */
    protected function extractPrompt(array $request): ?string
    {
        $messages = $request['messages'] ?? [];
        $prompt = '';

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $prompt .= "[{$role}]: {$content}\n";
            }
        }

        return $prompt ?: null;
    }

    /**
     * 获取异常的 HTTP 状态码
     *
     * @param  \Exception  $e  异常实例
     * @return int HTTP 状态码
     */
    protected function getStatusCode(\Exception $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if (method_exists($e, 'getCode') && $e->getCode() > 0) {
            return $e->getCode();
        }

        return 500;
    }

    /**
     * 验证模型是否在允许的列表中
     *
     * @param  string  $model  模型名称
     * @param  Request  $request  HTTP 请求
     *
     * @throws \InvalidArgumentException 当模型不在允许列表中时
     */
    protected function validateModel(string $model, Request $request): void
    {
        $apiKey = $request->attributes->get('api_key');

        // 检查 API Key 的模型映射（别名）
        if ($apiKey && ! empty($apiKey->model_mappings)) {
            $mappings = $apiKey->model_mappings;
            if (isset($mappings[$model])) {
                return;
            }
        }

        // 检查 API Key 的允许模型列表
        if ($apiKey && ! empty($apiKey->allowed_models)) {
            if (in_array($model, $apiKey->allowed_models, true)) {
                return;
            }

            throw new \InvalidArgumentException("Model '{$model}' is not in the allowed models list for this API key");
        }

        // 检查全局模型列表
        $exists = \App\Models\ModelList::where('model_name', $model)
            ->where('is_enabled', true)
            ->exists();

        if (! $exists) {
            throw new \InvalidArgumentException("Model '{$model}' is not available");
        }
    }

    /**
     * 获取当前请求 ID
     *
     * @return string|null 请求 ID
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 获取当前选中的渠道
     *
     * @return Channel|null 渠道实例
     */
    public function getSelectedChannel(): ?Channel
    {
        return $this->selectedChannel;
    }
}
