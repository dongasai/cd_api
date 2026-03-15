<?php

namespace App\Services\Router\Handler;

use App\Models\RequestLog;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\ProviderManager;
use App\Services\Router\Logger\AuditLogger;
use App\Services\Router\Logger\ResponseLogger;
use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\StreamChunk;
use Generator;
use Illuminate\Http\Request as HttpRequest;

/**
 * 流式请求处理器
 */
class StreamHandler
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
     * 处理流式请求
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
    ): Generator {
        $stream = $provider->sendStream($providerRequest);

        // 记录审计日志
        $auditLog = $this->auditLogger->createInitial($httpRequest, $standardRequest->model, true);

        $firstTokenMs = null;
        $streamChunks = [];
        $collectedUsage = null;
        $collectedFinishReason = null;

        foreach ($stream as $chunk) {
            if ($chunk instanceof StreamChunk) {
                // 记录首字延迟
                if ($firstTokenMs === null && ($chunk->delta !== '' || $chunk->contentDelta !== null)) {
                    $firstTokenMs = (int) ((microtime(true) - $startTime) * 1000);
                }

                // 收集流式块
                $streamChunks[] = $chunk->toArray();

                // 收集 usage（来自最后一个有 usage 的 chunk）
                if ($chunk->usage !== null) {
                    $collectedUsage = $chunk->usage;
                }

                // 收集 finishReason
                if ($chunk->finishReason !== null) {
                    $collectedFinishReason = $chunk->finishReason;
                }

                // 转换并输出
                yield $this->protocolConverter->convertStreamChunk($chunk, $sourceProtocol);
            }
        }

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // 发送结束标记
        yield $this->protocolConverter->driver($sourceProtocol)->buildStreamDone();

        // 更新审计日志（包含 token 使用信息）
        $this->updateAuditLogWithUsage($auditLog, $latencyMs, $firstTokenMs, $collectedUsage);

        // 记录响应日志
        $this->responseLogger->create(
            $requestLog,
            [],
            null,
            $latencyMs,
            $auditLog->id,
            true,
            $collectedUsage,
            $collectedFinishReason,
            null,
            $streamChunks
        );
    }

    /**
     * 更新审计日志，包含 token 使用信息
     */
    protected function updateAuditLogWithUsage(
        $auditLog,
        int $latencyMs,
        ?int $firstTokenMs,
        ?\App\Services\Shared\DTO\Usage $usage
    ): void {
        $data = [
            'status' => 'success',
            'latency_ms' => $latencyMs,
        ];

        // first_token_ms 字段不允许 null，只有非 null 才设置
        if ($firstTokenMs !== null) {
            $data['first_token_ms'] = $firstTokenMs;
        }

        // 更新 token 使用信息
        if ($usage !== null) {
            $data['prompt_tokens'] = $usage->inputTokens ?? 0;
            $data['completion_tokens'] = $usage->outputTokens ?? 0;
            $data['total_tokens'] = $usage->totalTokens ?? 0;
            $data['cache_read_tokens'] = $usage->cacheReadInputTokens ?? 0;
            $data['cache_write_tokens'] = $usage->cacheWriteInputTokens ?? 0;
        }

        $auditLog->update($data);
    }
}
