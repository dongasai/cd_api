<?php

namespace App\Services\Router\Logger;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * 审计日志记录服务
 */
class AuditLogger
{
    /**
     * 创建初始审计日志
     */
    public function createInitial(Request $request, ?string $model, bool $isStream, ?string $sourceProtocol = null): AuditLog
    {
        $apiKey = $request->attributes->get('api_key');

        return AuditLog::create([
            'request_id' => $request->attributes->get('request_id'),
            'api_key_id' => $apiKey?->id,
            'api_key_name' => $apiKey?->name,
            'model' => $model,
            'source_protocol' => $sourceProtocol,
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'is_stream' => $isStream,
        ]);
    }

    /**
     * 更新审计日志
     */
    public function update(AuditLog $log, array $data): void
    {
        $log->update($data);
    }

    /**
     * 标记成功
     */
    public function markSuccess(AuditLog $log, int $latencyMs, ?int $firstTokenMs = null): void
    {
        $data = [
            'status' => 'success',
            'latency_ms' => $latencyMs,
        ];

        // first_token_ms 字段不允许 null，只有非 null 才设置
        if ($firstTokenMs !== null) {
            $data['first_token_ms'] = $firstTokenMs;
        }

        $log->update($data);
    }

    /**
     * 标记失败
     */
    public function markFailed(AuditLog $log, string $error, int $latencyMs): void
    {
        $log->update([
            'status' => 'failed',
            'error' => $error,
            'latency_ms' => $latencyMs,
        ]);
    }
}
