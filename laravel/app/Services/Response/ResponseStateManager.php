<?php

namespace App\Services\Response;

use App\Models\ResponseSession;
use Illuminate\Support\Facades\Log;

/**
 * Response API 会话状态管理器
 *
 * 负责存储和检索 Responses API 的会话状态
 */
class ResponseStateManager
{
    /**
     * 默认过期时间（小时）
     */
    const DEFAULT_EXPIRY_HOURS = 24;

    /**
     * 生成全局唯一的 Response ID
     */
    public static function generateResponseId(): string
    {
        return 'resp_'.bin2hex(random_bytes(12)); // 24位十六进制字符
    }

    /**
     * 存储会话状态
     *
     * @param  string|null  $responseId  响应ID（为null时自动生成）
     * @param  array  $messages  完整消息历史
     * @param  int|null  $apiKeyId  API Key ID
     * @param  string  $model  模型名称
     * @param  int  $totalTokens  Token消耗总数
     * @param  string|null  $previousResponseId  上一次响应ID（对话链关联）
     */
    public function store(
        ?string $responseId,
        array $messages,
        ?int $apiKeyId = null,
        string $model = '',
        int $totalTokens = 0,
        ?string $previousResponseId = null
    ): ResponseSession {
        // 自动生成 Response ID
        if (empty($responseId)) {
            $responseId = self::generateResponseId();
        }

        // 检查是否已存在
        $existing = ResponseSession::where('response_id', $responseId)->first();
        if ($existing) {
            // 更新现有记录
            $existing->update([
                'messages' => $messages,
                'total_tokens' => $totalTokens,
                'message_count' => count($messages),
                'expires_at' => now()->addHours(self::DEFAULT_EXPIRY_HOURS),
            ]);

            return $existing;
        }

        // 创建新记录
        return ResponseSession::create([
            'response_id' => $responseId,
            'previous_response_id' => $previousResponseId,
            'api_key_id' => $apiKeyId,
            'messages' => $messages,
            'model' => $model,
            'total_tokens' => $totalTokens,
            'message_count' => count($messages),
            'expires_at' => now()->addHours(self::DEFAULT_EXPIRY_HOURS),
        ]);
    }

    /**
     * 检索会话历史
     *
     * @param  string  $responseId  响应ID
     * @param  int|null  $apiKeyId  API Key ID（用于隔离验证）
     * @return array|null 消息历史数组，不存在或过期返回 null
     */
    public function retrieve(string $responseId, ?int $apiKeyId = null): ?array
    {
        $query = ResponseSession::where('response_id', $responseId)
            ->where('expires_at', '>', now());

        // 严格隔离：必须提供 api_key_id
        if ($apiKeyId !== null) {
            $query->where('api_key_id', $apiKeyId);
        } else {
            // 没有 api_key_id 上下文，拒绝访问
            Log::warning('Response session retrieve attempted without API key context', [
                'response_id' => $responseId,
            ]);

            return null;
        }

        $session = $query->first();

        if (! $session) {
            return null;
        }

        // 更新最后访问时间
        $session->touch();

        return $session->messages;
    }

    /**
     * 追加消息到现有会话
     *
     * @param  string  $responseId  响应ID
     * @param  array  $newMessages  新消息（可包含多条）
     * @param  int  $additionalTokens  新增的Token数
     * @return bool 是否成功
     */
    public function append(string $responseId, array $newMessages, int $additionalTokens = 0): bool
    {
        $session = ResponseSession::where('response_id', $responseId)
            ->where('expires_at', '>', now())
            ->first();

        if (! $session) {
            return false;
        }

        $messages = $session->messages ?? [];
        $messages = array_merge($messages, $newMessages);

        $session->update([
            'messages' => $messages,
            'total_tokens' => $session->total_tokens + $additionalTokens,
            'message_count' => count($messages),
        ]);

        return true;
    }

    /**
     * 删除会话
     *
     * @param  string  $responseId  响应ID
     * @return bool 是否成功
     */
    public function forget(string $responseId): bool
    {
        $deleted = ResponseSession::where('response_id', $responseId)->delete();

        return $deleted > 0;
    }

    /**
     * 清理过期会话
     *
     * @return int 清理的记录数
     */
    public function cleanupExpired(): int
    {
        $count = ResponseSession::where('expires_at', '<', now())->delete();

        if ($count > 0) {
            Log::info('Cleaned up expired response sessions', [
                'count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * 获取统计信息
     *
     * @param  int|null  $apiKeyId  API Key ID（可选，用于筛选）
     * @return array 统计信息
     */
    public function getStats(?int $apiKeyId = null): array
    {
        $query = ResponseSession::query();

        if ($apiKeyId !== null) {
            $query->where('api_key_id', $apiKeyId);
        }

        return [
            'total_sessions' => $query->count(),
            'active_sessions' => (clone $query)->where('expires_at', '>', now())->count(),
            'expired_sessions' => (clone $query)->where('expires_at', '<', now())->count(),
            'total_tokens' => (clone $query)->sum('total_tokens'),
            'total_messages' => (clone $query)->sum('message_count'),
        ];
    }

    /**
     * 获取会话完整信息
     *
     * @param  string  $responseId  响应ID
     * @param  int|null  $apiKeyId  API Key ID
     */
    public function getSession(string $responseId, ?int $apiKeyId = null): ?ResponseSession
    {
        $query = ResponseSession::where('response_id', $responseId)
            ->where('expires_at', '>', now());

        if ($apiKeyId !== null) {
            $query->where('api_key_id', $apiKeyId);
        }

        return $query->first();
    }
}
