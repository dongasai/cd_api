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

    public function __construct(
        ProtocolConverter $protocolConverter,
        ProviderManager $providerManager,
        AuditLogger $auditLogger,
        ResponseLogger $responseLogger
    ) {
        $this->protocolConverter = $protocolConverter;
        $this->providerManager = $providerManager;
        $this->auditLogger = $auditLogger;
        $this->responseLogger = $responseLogger;
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
        float $startTime
    ): array {
        $providerResponse = $provider->send($providerRequest);

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // 记录审计日志
        $auditLog = $this->auditLogger->createInitial($httpRequest, $standardRequest->model, false);
        $this->auditLogger->markSuccess($auditLog, $latencyMs, $latencyMs);

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

        return $response;
    }
}
