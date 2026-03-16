<?php

namespace App\Services\Router;

use App\Models\Channel;
use App\Models\RequestLog;
use App\Services\ChannelAffinity\ChannelAffinityService;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\Exceptions\ProviderException;
use App\Services\Provider\ProviderManager;
use App\Services\Router\Handler\NonStreamHandler;
use App\Services\Router\Handler\StreamHandler;
use App\Services\Router\Logger\AuditLogger;
use App\Services\Router\Logger\RequestLogger;
use App\Services\Router\Logger\ResponseLogger;
use App\Services\Shared\DTO\Request as SharedRequest;
use Exception;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 代理服务器
 *
 * 核心 AI 请求代理服务，负责协调各组件完成请求处理：
 * - 协议转换（OpenAI/Anthropic 等）
 * - 渠道选择与路由
 * - 流式/非流式响应处理
 * - 日志记录与审计
 * - 自动重试与故障转移
 */
class ProxyServer
{
    protected ProtocolConverter $protocolConverter;

    protected ProviderManager $providerManager;

    protected ChannelRouterService $channelRouter;

    protected ChannelCodingStatusService $codingStatusService;

    protected ChannelAffinityService $affinityService;

    // 辅助服务
    protected RequestLogger $requestLogger;

    protected AuditLogger $auditLogger;

    protected ResponseLogger $responseLogger;

    protected ChannelSelector $channelSelector;

    protected RetryHandler $retryHandler;

    protected StreamHandler $streamHandler;

    protected NonStreamHandler $nonStreamHandler;

    protected ?string $requestId = null;

    protected float $startTime;

    protected ?Channel $selectedChannel = null;

    protected ?\App\Models\ChannelRequestLog $channelRequestLog = null;

    protected ?string $currentGroup = null;

    /**
     * 构造函数
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

        // 初始化辅助服务
        $this->requestLogger = new RequestLogger;
        $this->auditLogger = new AuditLogger;
        $this->responseLogger = new ResponseLogger;
        $this->channelSelector = new ChannelSelector($channelRouter, $affinityService);
        $this->retryHandler = new RetryHandler;
        $this->streamHandler = new StreamHandler(
            $protocolConverter,
            $providerManager,
            $this->auditLogger,
            $this->responseLogger,
            $affinityService  // 注入亲和性服务
        );
        $this->nonStreamHandler = new NonStreamHandler(
            $protocolConverter,
            $providerManager,
            $this->auditLogger,
            $this->responseLogger,
            $affinityService  // 注入亲和性服务
        );

        $this->startTime = microtime(true);
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
        $request->attributes->set('request_id', $this->requestId);
        $this->startTime = microtime(true);
        $this->channelSelector->reset();
        $this->selectedChannel = null;

        $rawRequest = $request->all();
        $isStream = $rawRequest['stream'] ?? false;
        $rawBodyString = $request->getContent();

        // 创建初始审计日志
        $auditLog = $this->auditLogger->createInitial($request, $rawRequest['model'] ?? null, $isStream);

        // 创建请求日志
        $requestLog = $this->requestLogger->create($request, $rawRequest, $protocol, $auditLog->id);

        $lastException = null;
        $attempt = 0;
        $apiKey = $request->attributes->get('api_key');

        while ($attempt <= $this->retryHandler->getMaxRetries()) {
            try {
                // 标准化请求
                $standardRequest = $this->protocolConverter->normalizeRequest($rawRequest, $protocol);

                $this->requestLogger->updateModel($requestLog, $standardRequest);

                // 验证模型
                $this->validateModel($standardRequest->model, $request);

                // 应用 Key 级别模型映射
                if ($apiKey && method_exists($apiKey, 'resolveModel')) {
                    $standardRequest->model = $apiKey->resolveModel($standardRequest->model);
                }

                // 选择渠道
                if ($this->selectedChannel === null || $attempt > 0) {
                    $this->selectedChannel = $this->channelSelector->select(
                        $standardRequest->model,
                        $apiKey,
                        $this->currentGroup,
                        $this->channelSelector->getFailedChannels()
                    );
                }

                if ($this->selectedChannel === null) {
                    throw new \RuntimeException('No available channel for model: '.$standardRequest->model);
                }

                // 解析实际模型名称
                $actualModel = $this->channelRouter->resolveModel($standardRequest->model, $this->selectedChannel);

                // 获取渠道协议
                $channelProtocol = $this->getChannelProtocol($this->selectedChannel);

                // 更新审计日志渠道信息和实际模型
                $this->auditLogger->update($auditLog, [
                    'channel_id' => $this->selectedChannel?->id,
                    'channel_name' => $this->selectedChannel?->name,
                    'model' => $standardRequest->model,
                    'actual_model' => $actualModel,  // 添加实际模型
                    'channel_affinity' => $this->affinityService->getAffinityInfo($request),  // 记录渠道亲和性信息
                    'metadata' => $rawRequest['metadata'] ?? null,  // 记录请求元数据
                ]);

                // 构建供应商请求
                $providerRequest = $this->buildProviderRequest(
                    $standardRequest,
                    $this->selectedChannel,
                    $channelProtocol,
                    $actualModel,
                    $rawBodyString
                );

                // 更新请求日志渠道信息
                $this->requestLogger->updateForChannel($requestLog, $this->selectedChannel, $actualModel);

                $provider = $this->providerManager->getForChannel($this->selectedChannel, $request->headers->all());

                // 创建渠道请求日志
                $this->createChannelRequestLog($requestLog, $this->selectedChannel, $providerRequest, $channelProtocol, $provider, $auditLog);

                // 根据是否流式请求分别处理
                if ($isStream) {
                    return $this->streamHandler->handle(
                        $request,
                        $standardRequest,
                        $providerRequest,
                        $provider,
                        $protocol,
                        $channelProtocol,
                        $requestLog,
                        $this->startTime,  // 保持浮点数精度
                        $auditLog,  // 传递已创建的审计日志
                        $this->selectedChannel  // 传递选中的渠道
                    );
                }

                return $this->nonStreamHandler->handle(
                    $request,
                    $standardRequest,
                    $providerRequest,
                    $provider,
                    $protocol,
                    $channelProtocol,
                    $requestLog,
                    $this->startTime,  // 保持浮点数精度
                    $auditLog,  // 传递已创建的审计日志
                    $this->selectedChannel  // 传递选中的渠道
                );
            } catch (Exception $e) {
                $lastException = $e;

                // 检查是否应该重试
                if (! $this->retryHandler->shouldRetry($e, $attempt)) {
                    break;
                }

                // 标记当前渠道失败
                if ($this->selectedChannel) {
                    $this->channelSelector->markFailed($this->selectedChannel, $e->getMessage());
                    Log::warning('Channel failed, will retry', [
                        'request_id' => $this->requestId,
                        'channel_id' => $this->selectedChannel->id,
                        'attempt' => $attempt + 1,
                        'error' => $e->getMessage(),
                    ]);
                    $this->selectedChannel = null;
                }

                $attempt++;
                $this->retryHandler->wait($attempt);
            }
        }

        // 所有重试都失败了
        $this->handleError($lastException, $request, $requestLog, $auditLog);

        throw $lastException;
    }

    /**
     * 处理错误
     */
    protected function handleError(Exception $e, Request $request, RequestLog $requestLog, $auditLog): void
    {
        $latencyMs = (int) ((microtime(true) - $this->startTime) * 1000);
        $statusCode = $this->getStatusCode($e);

        if ($statusCode === 0) {
            $statusCode = 500;
        }

        // 更新审计日志
        $this->auditLogger->update($auditLog, [
            'channel_id' => $this->selectedChannel?->id,
            'channel_name' => $this->selectedChannel?->name,
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
        ]);

        // 更新渠道请求日志（记录渠道返回的错误）
        if ($this->channelRequestLog) {
            $updateData = [
                'response_status' => $statusCode,
                'latency_ms' => $latencyMs,
                'is_success' => false,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
            ];

            // 如果是 ProviderException，记录原始错误信息
            if ($e instanceof \App\Services\Provider\Exceptions\ProviderException) {
                $rawError = $e->getRawError();
                if ($rawError !== null) {
                    $updateData['response_body'] = $rawError;
                }
            }

            $this->channelRequestLog->update($updateData);
        }

        Log::error('Proxy request failed', [
            'request_id' => $this->requestId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * 获取渠道协议类型
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
     */
    protected function buildProviderRequest(
        SharedRequest $standardRequest,
        Channel $channel,
        string $targetProtocol,
        string $actualModel,
        ?string $rawBodyString = null
    ): SharedRequest {
        // 检查是否开启了 body 透传
        if ($channel->shouldPassthroughBody() && $rawBodyString !== null) {
            $providerRequest = SharedRequest::fromArray([]);
            $providerRequest->rawBodyString = $rawBodyString;
            $providerRequest->queryString = request()->getQueryString();

            return $providerRequest;
        }

        if ($targetProtocol === 'anthropic') {
            $filterThinking = $channel->shouldFilterThinking();
            $filterRequestThinking = $channel->shouldFilterRequestThinking();
            $requestData = $standardRequest->toAnthropic(true, $filterThinking, $filterRequestThinking);
        } else {
            $requestData = $standardRequest->toOpenAI();
        }

        $requestData['model'] = $actualModel;

        $providerRequest = SharedRequest::fromArray($requestData);
        $providerRequest->queryString = request()->getQueryString();

        return $providerRequest;
    }

    /**
     * 创建渠道请求日志
     */
    protected function createChannelRequestLog(
        RequestLog $requestLog,
        Channel $channel,
        SharedRequest $providerRequest,
        string $channelProtocol,
        $provider,
        $auditLog = null  // 接收审计日志
    ): void {
        $baseUrl = rtrim($channel->base_url, '/');
        $path = $this->buildEndpointPath($channelProtocol);
        $fullUrl = $baseUrl.$path;

        $headers = $this->getProviderHeaders($provider);

        // 根据渠道协议使用正确的格式保存请求体
        if ($channel->shouldPassthroughBody() && $providerRequest->rawBodyString !== null) {
            $requestBody = json_decode($providerRequest->rawBodyString, true) ?? $providerRequest->rawBodyString;
        } elseif ($channelProtocol === 'anthropic') {
            $requestBody = $providerRequest->toAnthropicFormat();
        } else {
            $requestBody = $providerRequest->toOpenAIFormat();
        }

        $this->channelRequestLog = \App\Models\ChannelRequestLog::create([
            'request_log_id' => $requestLog->id,
            'request_id' => $this->requestId,
            'audit_log_id' => $auditLog?->id,  // 添加审计日志ID
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
     * 从 Provider 获取请求头
     */
    protected function getProviderHeaders($provider): array
    {
        if (method_exists($provider, 'getHeaders')) {
            return $provider->getHeaders();
        }

        return [];
    }

    /**
     * 构建渠道端点路径
     */
    protected function buildEndpointPath(string $protocol): string
    {
        if ($protocol === 'anthropic') {
            return '/messages';
        }

        return '/chat/completions';
    }

    /**
     * 过滤敏感请求头
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
     * 获取异常的 HTTP 状态码
     */
    protected function getStatusCode(Exception $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return (int) $e->getStatusCode();
        }

        if (method_exists($e, 'getCode') && $e->getCode() > 0) {
            return (int) $e->getCode();
        }

        return 500;
    }

    /**
     * 验证模型是否在允许的列表中
     */
    protected function validateModel(string $model, Request $request): void
    {
        $apiKey = $request->attributes->get('api_key');

        if (! \App\Services\ModelService::isModelAvailable($model, $apiKey)) {
            if ($apiKey && ! empty($apiKey->allowed_models)) {
                throw new \InvalidArgumentException("Model '{$model}' is not in the allowed models list for this API key");
            }

            throw new \InvalidArgumentException("Model '{$model}' is not available");
        }
    }

    /**
     * 获取当前请求 ID
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 获取当前选中的渠道
     */
    public function getSelectedChannel(): ?Channel
    {
        return $this->selectedChannel;
    }
}
