<?php

namespace App\Services\Router\Logger;

use App\Models\RequestLog;
use App\Services\Protocol\Contracts\ProtocolRequest;
use Illuminate\Http\Request;

/**
 * 请求日志记录服务
 */
class RequestLogger
{
    /**
     * 创建请求日志
     */
    public function create(Request $request, array $rawRequest, string $protocol, ?int $auditLogId = null): RequestLog
    {
        return RequestLog::create([
            'audit_log_id' => $auditLogId,
            'request_id' => $request->attributes->get('request_id'),
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
     */
    public function updateModel(RequestLog $log, ProtocolRequest $request): void
    {
        // 转换为数组获取所有参数
        $requestData = $request->toArray();

        $log->update([
            'model' => $request->getModel(),
            'model_params' => [
                'temperature' => $requestData['temperature'] ?? null,
                'top_p' => $requestData['top_p'] ?? null,
                'max_tokens' => $requestData['max_tokens'] ?? $requestData['max_completion_tokens'] ?? null,
                'stream' => $request->isStream(),
            ],
        ]);
    }

    /**
     * 更新请求日志的渠道信息
     */
    public function updateForChannel(RequestLog $log, $channel, string $actualModel): void
    {
        $log->update([
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'upstream_model' => $actualModel,
        ]);
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
     * 提取模型参数
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
}
