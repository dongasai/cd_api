<?php

namespace App\Services\Router\Logger;

use App\Models\RequestLog;
use App\Models\ResponseLog;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\DTO\Usage;

/**
 * 响应日志记录服务
 */
class ResponseLogger
{
    /**
     * 创建响应日志
     */
    public function create(
        RequestLog $requestLog,
        array $response,
        ProtocolResponse|Response|null $providerResponse,
        int $latencyMs,
        ?int $auditLogId,
        bool $isStream,
        ?object $usage = null,
        $finishReason = null,
        ?string $content = null,
        ?array $streamChunks = null
    ): ResponseLog {
        $statusCode = 200;

        return ResponseLog::create([
            'audit_log_id' => $auditLogId ?? $requestLog->audit_log_id,
            'request_id' => $requestLog->request_id,
            'request_log_id' => $requestLog->id,
            'status_code' => $statusCode,
            'status_message' => $this->getStatusMessage($statusCode),
            'headers' => ['content-type' => 'application/json'],
            'content_type' => 'application/json',
            'content_length' => strlen(json_encode($response)),
            'body_text' => $this->truncateBody(json_encode($response)),
            'response_type' => $isStream ? 'stream' : 'json',
            'finish_reason' => $finishReason,
            'generated_text' => $content,
            'generated_chunks' => $streamChunks,
            'usage' => $usage ? $this->normalizeUsage($usage) : null,
            'upstream_provider' => null,
            'upstream_model' => $providerResponse?->model,
            'upstream_latency_ms' => $latencyMs,
        ]);
    }

    /**
     * 更新响应日志
     */
    public function update(ResponseLog $log, array $data): void
    {
        $log->update($data);
    }

    /**
     * 获取状态消息
     */
    protected function getStatusMessage(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'Unknown',
        };
    }

    /**
     * 截断请求体
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
     * 标准化 Usage 对象
     *
     * 兼容 Shared\DTO\Usage 和协议特定的 Usage 对象
     */
    protected function normalizeUsage(object $usage): array
    {
        // 如果有 toSharedDTO 方法，先转换
        if (method_exists($usage, 'toSharedDTO')) {
            $usage = $usage->toSharedDTO();
        }

        // 从对象属性获取值（兼容 Shared\DTO\Usage 和协议特定 Usage）
        return [
            'prompt_tokens' => $usage->inputTokens ?? $usage->prompt_tokens ?? 0,
            'completion_tokens' => $usage->outputTokens ?? $usage->completion_tokens ?? 0,
            'total_tokens' => $usage->totalTokens ?? $usage->total_tokens ?? 0,
            'cache_read_tokens' => $usage->cacheReadInputTokens ?? $usage->cache_read_tokens ?? 0,
            'cache_write_tokens' => $usage->cacheCreationInputTokens ?? $usage->cache_write_tokens ?? 0,
        ];
    }
}
