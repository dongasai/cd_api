<?php

namespace App\Services\Router;

use App\Models\Channel;
use App\Models\RequestLog;
use App\Services\ChannelAffinity\ChannelAffinityService;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\CodingStatus\ChannelErrorHandlingService;
use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\Exceptions\ProviderException;
use App\Services\Provider\ProviderManager;
use App\Services\Router\Handler\NonStreamHandler;
use App\Services\Router\Handler\StreamHandler;
use App\Services\Router\Logger\AuditLogger;
use App\Services\Router\Logger\RequestLogger;
use App\Services\Router\Logger\ResponseLogger;
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

    protected ChannelErrorHandlingService $errorHandlingService;

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

    protected ?Channel $lastSuccessfulChannel = null;  // 保存最后一次成功选择的渠道（用于错误记录）

    /**
     * 构造函数
     */
    public function __construct(
        ProtocolConverter $protocolConverter,
        ProviderManager $providerManager,
        ChannelRouterService $channelRouter,
        ChannelCodingStatusService $codingStatusService,
        ChannelErrorHandlingService $errorHandlingService,
        ChannelAffinityService $affinityService
    ) {
        $this->protocolConverter = $protocolConverter;
        $this->providerManager = $providerManager;
        $this->channelRouter = $channelRouter;
        $this->codingStatusService = $codingStatusService;
        $this->errorHandlingService = $errorHandlingService;
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
     * @param  string  $protocol  源协议类型（openai_chat_completions/anthropic_messages）
     * @return array|Generator 响应数组或流式生成器
     *
     * @throws \Exception
     */
    public function proxy(Request $request, string $protocol = 'openai_chat_completions'): array|Generator
    {
        $this->requestId = $request->attributes->get('request_id', Str::uuid()->toString());
        $request->attributes->set('request_id', $this->requestId);
        $this->startTime = microtime(true);
        $this->channelSelector->reset();
        $this->selectedChannel = null;

        $rawRequest = $request->all();
        $apiKey = $request->attributes->get('api_key');

        // 对于 Responses 协议，注入 apiKeyId 用于状态管理
        if ($protocol === 'openai_responses' && $apiKey !== null) {
            $rawRequest['_api_key_id'] = $apiKey->id;
        }

        // DEBUG: 记录原始请求中的 tools
        if (isset($rawRequest['tools'])) {
            Log::debug('ProxyServer: Raw request tools', [
                'tools_count' => count($rawRequest['tools']),
                'tools_preview' => array_slice($rawRequest['tools'], 0, 2),
            ]);
        }

        $isStream = $rawRequest['stream'] ?? false;
        $rawBodyString = $request->getContent();

        // 创建初始审计日志
        $auditLog = $this->auditLogger->createInitial($request, $rawRequest['model'] ?? null, $isStream, $protocol);

        // 创建请求日志
        $requestLog = $this->requestLogger->create($request, $rawRequest, $protocol, $auditLog->id);

        // 保存协议上下文（在协议转换前提取，避免转换过程中丢失）
        $protocolContext = null;
        if ($protocol === 'openai_responses') {
            $tempRequest = $this->protocolConverter->normalizeRequest($rawRequest, $protocol);
            $sharedDto = $tempRequest->toSharedDTO();
            $protocolContext = $sharedDto->protocolContext ?? null;
        }

        $lastException = null;
        $attempt = 0;

        while ($attempt <= $this->retryHandler->getMaxRetries()) {
            try {
                // 解析为协议请求结构体
                $protocolRequest = $this->protocolConverter->normalizeRequest($rawRequest, $protocol);

                // 获取模型名称
                $modelName = $protocolRequest->getModel();

                $this->requestLogger->updateModel($requestLog, $protocolRequest);

                // 验证模型
                $this->validateModel($modelName, $request);

                // 应用 Key 级别模型映射
                if ($apiKey && method_exists($apiKey, 'resolveModel')) {
                    $resolvedModel = $apiKey->resolveModel($modelName);
                    if ($resolvedModel !== $modelName) {
                        $protocolRequest->setModel($resolvedModel);
                        $modelName = $resolvedModel;
                    }
                }

                // 选择渠道
                if ($this->selectedChannel === null || $attempt > 0) {
                    $this->selectedChannel = $this->channelSelector->select(
                        $modelName,
                        $apiKey,
                        $this->currentGroup,
                        $this->channelSelector->getFailedChannels(),
                        $protocol  // 传入源协议
                    );

                    // 保存最后一次成功选择的渠道（用于错误记录）
                    if ($this->selectedChannel !== null) {
                        $this->lastSuccessfulChannel = $this->selectedChannel;
                    }
                }

                if ($this->selectedChannel === null) {
                    throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException('No available channel for model: '.$modelName);
                }

                // 解析实际模型名称
                $actualModel = $this->channelRouter->resolveModel($modelName, $this->selectedChannel);

                // 获取渠道协议
                $channelProtocol = $this->getChannelProtocol($this->selectedChannel);

                // 获取参与匹配的模型列表（别名扩展结果）
                $matchedModels = $this->channelRouter->getModelNamesWithAliases($modelName);

                // 构建 apply_data（记录模型流转过程数据）
                $applyData = [
                    'matched_models' => $matchedModels,  // 参与匹配的所有模型名
                    'channel_request_model' => $actualModel,  // 发送给渠道的模型名
                ];

                // 更新审计日志渠道信息和实际模型
                $this->auditLogger->update($auditLog, [
                    'channel_id' => $this->selectedChannel?->id,
                    'channel_name' => $this->selectedChannel?->name,
                    'model' => $modelName,
                    'actual_model' => $actualModel,  // 添加实际模型
                    'target_protocol' => $channelProtocol,  // 添加目标协议
                    'channel_affinity' => $this->affinityService->getAffinityInfo($request),  // 记录渠道亲和性信息
                    'metadata' => $rawRequest['metadata'] ?? null,  // 记录请求元数据
                    'apply_data' => $applyData,  // 新增：记录应用数据
                ]);

                // 更新实际模型
                if ($actualModel !== $modelName) {
                    $protocolRequest->setModel($actualModel);
                }

                // 检查是否开启 body_passthrough（透传模式）
                if ($this->selectedChannel->shouldPassthroughBody()) {
                    // 透传模式：直接使用原始请求体，跳过协议转换和过滤
                    $protocolRequest->setRawBodyString($rawBodyString);
                } else {
                    // 正常模式：进行协议转换和过滤

                    // 判断是否需要协议转换
                    if ($channelProtocol !== $protocol) {
                        $protocolRequest = $this->protocolConverter->convertRequest($protocolRequest, $channelProtocol);

                        // DEBUG: 记录协议转换后的 tools
                        if (method_exists($protocolRequest, 'toArray')) {
                            $convertedArray = $protocolRequest->toArray();
                            Log::debug('ProxyServer: After protocol conversion', [
                                'source_protocol' => $protocol,
                                'target_protocol' => $channelProtocol,
                                'has_tools' => isset($convertedArray['tools']),
                                'tools_count' => isset($convertedArray['tools']) ? count($convertedArray['tools']) : 0,
                                'tool_choice' => $convertedArray['tool_choice'] ?? null,
                            ]);
                        }
                    }

                    // 应用渠道配置：过滤请求中的 thinking 内容块
                    if ($this->selectedChannel->shouldFilterRequestThinking()) {
                        if (method_exists($protocolRequest, 'filterRequestThinking')) {
                            $protocolRequest->filterRequestThinking(true);
                        }
                    }
                }

                // 更新请求日志渠道信息（提前更新，确保即使后续异常也能记录渠道）
                $this->requestLogger->updateForChannel($requestLog, $this->selectedChannel, $actualModel);

                $provider = $this->providerManager->getForChannel($this->selectedChannel, $request->headers->all());

                // 创建渠道请求日志（提前创建，确保即使后续异常也有记录）
                try {
                    $this->createChannelRequestLog($requestLog, $this->selectedChannel, $protocolRequest, $channelProtocol, $provider, $auditLog);
                } catch (\Exception $logException) {
                    // 日志创建失败不应影响主流程，记录错误即可
                    Log::error('Failed to create channel request log', [
                        'request_id' => $this->requestId,
                        'channel_id' => $this->selectedChannel->id,
                        'error' => $logException->getMessage(),
                    ]);
                }

                // 根据是否流式请求分别处理
                if ($isStream) {
                    return $this->streamHandler->handle(
                        $request,
                        $protocolRequest,
                        $provider,
                        $protocol,
                        $channelProtocol,
                        $requestLog,
                        $this->startTime,  // 保持浮点数精度
                        $auditLog,  // 传递已创建的审计日志
                        $this->selectedChannel,  // 传递选中的渠道
                        $protocolContext  // 传递协议上下文（状态管理）
                    );
                }

                return $this->nonStreamHandler->handle(
                    $request,
                    $protocolRequest,
                    $provider,
                    $protocol,
                    $channelProtocol,
                    $requestLog,
                    $this->startTime,  // 保持浮点数精度
                    $auditLog,  // 传递已创建的审计日志
                    $this->selectedChannel,  // 传递选中的渠道
                    $protocolContext  // 传递协议上下文（状态管理）
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

        // 更新审计日志（使用最后一次成功选择的渠道）
        $this->auditLogger->update($auditLog, [
            'channel_id' => $this->lastSuccessfulChannel?->id ?? $this->selectedChannel?->id,
            'channel_name' => $this->lastSuccessfulChannel?->name ?? $this->selectedChannel?->name,
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

            // 触发错误处理（仅当渠道绑定了 CodingAccount 时）
            $channel = $this->lastSuccessfulChannel ?? $this->selectedChannel;
            if ($channel && $channel->hasCodingAccount()) {
                try {
                    $this->errorHandlingService->handleRequestError($channel, $this->channelRequestLog);
                } catch (\Exception $handlingError) {
                    // 错误处理失败不应影响主流程
                    Log::error('Error handling failed', [
                        'request_id' => $this->requestId,
                        'channel_id' => $channel->id,
                        'error' => $handlingError->getMessage(),
                    ]);
                }
            }
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
            return 'anthropic_messages';
        }

        return 'openai_chat_completions';
    }

    /**
     * 创建渠道请求日志
     */
    protected function createChannelRequestLog(
        RequestLog $requestLog,
        Channel $channel,
        ProtocolRequest $protocolRequest,
        string $channelProtocol,
        $provider,
        $auditLog = null
    ): void {
        $baseUrl = rtrim($channel->base_url, '/');
        $path = $this->buildEndpointPath($channelProtocol);
        $fullUrl = $baseUrl.$path;

        $headers = $this->getProviderHeaders($provider);

        // 使用 Provider 的 buildRequestBody 方法获取最终请求体（包含渠道配置）
        $requestBody = $provider->buildRequestBody($protocolRequest);

        $this->channelRequestLog = \App\Models\ChannelRequestLog::create([
            'request_log_id' => $requestLog->id,
            'request_id' => $this->requestId,
            'audit_log_id' => $auditLog?->id,
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
        if ($protocol === 'anthropic_messages') {
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
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("Model '{$model}' is not in the allowed models list for this API key");
            }

            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException("Model '{$model}' is not available");
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
