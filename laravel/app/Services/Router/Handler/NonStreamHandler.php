<?php

namespace App\Services\Router\Handler;

use App\Models\RequestLog;
use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\ProviderManager;
use App\Services\Router\Logger\AuditLogger;
use App\Services\Router\Logger\ResponseLogger;
use Illuminate\Http\Request as HttpRequest;

/**
 * 非流式请求处理器
 */
class NonStreamHandler
{
    protected ProtocolConverter $protocolConverter;

    protected ProviderManager $providerManager;

    protected AuditLogger $auditLogger;

    protected ResponseLogger $responseLogger;

    protected $affinityService = null;  // 渠道亲和性服务

    protected $selectedChannel = null;  // 当前选中的渠道

    public function __construct(
        ProtocolConverter $protocolConverter,
        ProviderManager $providerManager,
        AuditLogger $auditLogger,
        ResponseLogger $responseLogger,
        $affinityService = null
    ) {
        $this->protocolConverter = $protocolConverter;
        $this->providerManager = $providerManager;
        $this->auditLogger = $auditLogger;
        $this->responseLogger = $responseLogger;
        $this->affinityService = $affinityService ?? app(\App\Services\ChannelAffinity\ChannelAffinityService::class);
    }

    /**
     * 处理非流式请求
     */
    public function handle(
        HttpRequest $httpRequest,
        ProtocolRequest $protocolRequest,
        $provider,
        string $sourceProtocol,
        string $targetProtocol,
        RequestLog $requestLog,
        float $startTime,  // 保持浮点数精度
        $auditLog = null,  // 接收已创建的审计日志
        $selectedChannel = null  // 接收选中的渠道
    ): array {
        $this->selectedChannel = $selectedChannel;  // 保存渠道引用
        $providerResponse = $provider->send($protocolRequest);

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // 获取模型名称
        $modelName = $protocolRequest->getModel();

        // 使用传入的审计日志（如果已创建），否则创建新的
        if ($auditLog === null) {
            $auditLog = $this->auditLogger->createInitial($httpRequest, $modelName, false, $sourceProtocol);
        }

        // 更新审计日志
        $updateData = [
            'status_code' => 200,
            'latency_ms' => $latencyMs,
            'first_token_ms' => $latencyMs,  // 非流式请求，首字延迟=总延迟
            'actual_model' => $providerResponse->getModel(),  // 新增：渠道响应的模型名
        ];

        // 获取使用量
        $usage = $providerResponse->getUsage();
        if ($usage !== null) {
            $updateData['prompt_tokens'] = $usage->inputTokens ?? 0;
            $updateData['completion_tokens'] = $usage->outputTokens ?? 0;
            $updateData['total_tokens'] = ($usage->inputTokens ?? 0) + ($usage->outputTokens ?? 0);
            $updateData['cache_read_tokens'] = $usage->cacheReadInputTokens ?? 0;
            $updateData['cache_write_tokens'] = $usage->cacheCreationInputTokens ?? 0;
        }

        $auditLog->update($updateData);

        // 应用渠道配置：过滤响应中的 thinking 内容块
        if ($selectedChannel !== null && $selectedChannel->shouldFilterThinking()) {
            $providerResponse->filterThinking(true);
        }

        // 如果需要协议转换，转换响应
        if ($sourceProtocol !== $targetProtocol) {
            $providerResponse = $this->protocolConverter->convertResponse($providerResponse, $sourceProtocol);
        }

        // 构建响应
        $response = $this->protocolConverter->denormalizeResponse($providerResponse, $sourceProtocol);

        // 记录响应日志
        $this->responseLogger->create(
            $requestLog,
            $response,
            $providerResponse,
            $latencyMs,
            $auditLog->id,
            false,
            $usage,
            null,
            $this->extractContent($providerResponse)
        );

        // 记录渠道亲和性（成功请求后更新缓存）
        $this->recordAffinity($httpRequest, $modelName);

        return $response;
    }

    /**
     * 从协议响应中提取文本内容
     */
    protected function extractContent(ProtocolResponse $response): string
    {
        $sharedDTO = $response->toSharedDTO();
        $content = '';
        foreach ($sharedDTO->choices ?? [] as $choice) {
            if (isset($choice['message']['content'])) {
                $content .= $choice['message']['content'];
            }
        }

        return $content;
    }

    /**
     * 记录渠道亲和性
     */
    protected function recordAffinity(HttpRequest $request, string $model): void
    {
        if ($this->affinityService !== null && $this->selectedChannel !== null) {
            $this->affinityService->recordAffinity($request, $this->selectedChannel, $model);
        }
    }
}
