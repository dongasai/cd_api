<?php

namespace App\Services\Router\Handler;

use App\Models\RequestLog;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\ProviderManager;
use App\Services\Router\Logger\AuditLogger;
use App\Services\Router\Logger\ResponseLogger;
use App\Services\Shared\DTO\Request;
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
        Request $standardRequest,
        Request $providerRequest,
        $provider,
        string $sourceProtocol,
        string $targetProtocol,
        RequestLog $requestLog,
        float $startTime,  // 保持浮点数精度
        $auditLog = null,  // 接收已创建的审计日志
        $selectedChannel = null  // 接收选中的渠道
    ): array {
        $this->selectedChannel = $selectedChannel;  // 保存渠道引用
        $providerResponse = $provider->send($providerRequest);

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // 使用传入的审计日志（如果已创建），否则创建新的
        if ($auditLog === null) {
            $auditLog = $this->auditLogger->createInitial($httpRequest, $standardRequest->model, false);
        }

        // 更新审计日志
        $auditLog->update([
            'status_code' => 200,
            'latency_ms' => $latencyMs,
            'first_token_ms' => $latencyMs,  // 非流式请求，首字延迟=总延迟
            'finish_reason' => $providerResponse->finishReason?->value,
        ]);

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
            $providerResponse->usage,
            $providerResponse->finishReason,
            $providerResponse->getContent()
        );

        // 记录渠道亲和性（成功请求后更新缓存）
        $this->recordAffinity($httpRequest, $standardRequest->model);

        return $response;
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
