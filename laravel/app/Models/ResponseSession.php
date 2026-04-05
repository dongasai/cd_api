<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Response API 会话状态模型
 */
class ResponseSession extends Model
{
    use HasFactory;

    protected $table = 'response_sessions';

    protected $fillable = [
        'response_id',           // 当前响应ID
        'previous_response_id',  // 上一次响应ID（对话链）
        'api_key_id',            // API Key ID
        'messages',              // 完整消息历史（JSON）
        'model',                 // 模型名称
        'total_tokens',          // Token消耗
        'message_count',         // 消息数量
        'expires_at',            // 过期时间
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * 关联 ApiKey
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * 获取上一次响应
     */
    public function previous(): ?self
    {
        if (empty($this->previous_response_id)) {
            return null;
        }

        return self::where('response_id', $this->previous_response_id)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * 查找有效会话
     */
    public static function findValid(string $responseId, ?int $apiKeyId = null): ?self
    {
        $query = self::where('response_id', $responseId)
            ->where('expires_at', '>', now());

        if ($apiKeyId !== null) {
            $query->where('api_key_id', $apiKeyId);
        }

        return $query->first();
    }

    /**
     * 获取对话链长度
     */
    public function getChainLength(): int
    {
        $length = 1;
        $current = $this;

        while ($current->previous_response_id) {
            $previous = $current->previous();
            if (! $previous) {
                break;
            }
            $length++;
            $current = $previous;
        }

        return $length;
    }

    /**
     * 获取完整对话链（从最早到当前）
     *
     * @return array<ResponseSession>
     */
    public function getConversationChain(): array
    {
        $chain = [];

        // 先收集到根节点
        $nodes = [$this];
        $current = $this;
        while ($current->previous_response_id) {
            $previous = $current->previous();
            if (! $previous) {
                break;
            }
            $nodes[] = $previous;
            $current = $previous;
        }

        // 反转得到从最早到当前的顺序
        return array_reverse($nodes);
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * 获取消息数量
     */
    public function getMessageCount(): int
    {
        return count($this->messages ?? []);
    }
}
