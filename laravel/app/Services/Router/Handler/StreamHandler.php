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
        float $startTime,  // 保持浮点数精度，用于计算首字延迟
        $auditLog = null,  // 接收已创建的审计日志
        $selectedChannel = null  // 接收选中的渠道
    ): Generator {
        $this->selectedChannel = $selectedChannel;  // 保存渠道引用，用于记录亲和性
        $stream = $provider->sendStream($providerRequest);

        // 使用传入的审计日志（如果已创建），否则创建新的
        if ($auditLog === null) {
            $auditLog = $this->auditLogger->createInitial($httpRequest, $standardRequest->model, true);
        }

        $firstTokenMs = null;
        $streamChunks = [];
        $collectedUsage = null;
        $collectedFinishReason = null;

        foreach ($stream as $chunk) {
            if ($chunk instanceof StreamChunk) {
                // 记录首字延迟（包括文本内容、推理内容或工具调用）
                if ($firstTokenMs === null &&
                    ($chunk->delta !== '' ||
                     $chunk->contentDelta !== null ||
                     $chunk->reasoningDelta !== null ||
                     $chunk->toolCalls !== null)) {
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

        // 注意：上游已经发送了 message_stop 事件，透传模式下不需要额外发送结束标记
        // yield $this->protocolConverter->driver($sourceProtocol)->buildStreamDone();

        // 更新审计日志（包含 token 使用信息）
        $this->updateAuditLogWithUsage($auditLog, $latencyMs, $firstTokenMs, $collectedUsage, $collectedFinishReason);

        // 记录渠道亲和性（成功请求后更新缓存）
        $this->recordAffinity($httpRequest, $standardRequest->model);

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
     * 更新审计日志（包含 token 使用信息）
     */
    protected function updateAuditLogWithUsage(
        $auditLog,
        int $latencyMs,
        ?int $firstTokenMs,
        $usage,
        $finishReason
    ): void {
        $data = [
            'status_code' => 200,
            'latency_ms' => $latencyMs,
        ];

        // 首字延迟（只有非 null 才设置）
        if ($firstTokenMs !== null) {
            $data['first_token_ms'] = $firstTokenMs;
        }

        // 完成原因
        if ($finishReason !== null) {
            $data['finish_reason'] = $finishReason->value;
        }

        $auditLog->update($data);
    }

    /**
     * 记录渠道亲和性
     */
    protected function recordAffinity(HttpRequest $request, string $model): void
    {
        if ($this->affinityService !== null) {
            $this->affinityService->recordAffinity($request, $this->selectedChannel ?? null, $model);
        }
    }
}
