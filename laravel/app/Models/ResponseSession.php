<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Response API 会话状态模型
 *
 * 管理 OpenAI Responses API 的会话状态，支持多轮对话链追溯。
 * 每条记录代表一次响应的完整上下文，通过 previous_response_id 链接形成对话链。
 *
 * ┌──────────────────────┬──────────────────┬───┬─────────────────────────────────────────┐
 * │ 字段                 │ 类型             │ 空│ 说明                                    │
 * ├──────────────────────┼──────────────────┼───┼─────────────────────────────────────────┤
 * │ id                   │ bigint unsigned  │ NO│ 主键自增 ID                             │
 * │ response_id          │ varchar(255)     │ NO│ 当前响应 ID（唯一）                     │
 * │ previous_response_id │ varchar(255)     │ YES│ 上一次响应 ID（用于追溯对话链）         │
 * │ api_key_id           │ bigint unsigned  │ YES│ API Key ID                             │
 * │ messages             │ json             │ NO│ 完整消息历史                            │
 * │ model                │ varchar(100)     │ NO│ 模型名称                                │
 * │ total_tokens         │ int unsigned     │ NO│ 总 Token 消耗（默认 0）                 │
 * │ message_count        │ int unsigned     │ NO│ 消息数量（默认 0）                      │
 * │ expires_at           │ timestamp        │ NO│ 过期时间                                │
 * │ created_at           │ timestamp        │ YES│ 创建时间                               │
 * │ updated_at           │ timestamp        │ YES│ 更新时间                               │
 * └──────────────────────┴──────────────────┴───┴─────────────────────────────────────────┘
 *
 * 索引：
 *   - PRIMARY                            id（主键）
 *   - UNIQUE  response_sessions_response_id_unique           response_id
 *   - INDEX   response_sessions_previous_response_id_index   previous_response_id（对话链追溯）
 *   - INDEX   response_sessions_api_key_id_expires_at_index  api_key_id + expires_at（清理和查询）
 *   - INDEX   response_sessions_expires_at_index             expires_at（过期清理）
 *
 * 迁移历史：
 *   - 2026_04_05_000001: 创建 response_sessions 表，支持 Responses API 会话状态管理
 *
 * 核心功能：
 *   1. 会话状态持久化 — 存储 Responses API 的完整消息历史和上下文
 *   2. 对话链管理 — 通过 previous_response_id 构建多轮对话链
 *   3. 过期机制 — 基于 expires_at 自动管理会话生命周期
 *   4. Token 统计 — 追踪每次会话的 Token 消耗
 *
 * @property int $id 主键 ID
 * @property string $response_id 当前响应 ID（唯一）
 * @property string|null $previous_response_id 上一次响应 ID（对话链上游）
 * @property int|null $api_key_id 关联的 API Key ID
 * @property array $messages 完整消息历史（JSON 数组）
 * @property string $model 使用的模型名称
 * @property int $total_tokens 总 Token 消耗
 * @property int $message_count 消息数量
 * @property \DateTime $expires_at 会话过期时间
 * @property \DateTime|null $created_at 创建时间
 * @property \DateTime|null $updated_at 更新时间
 *
 * @see ApiKey                        关联的 API Key 模型
 * @see \App\Services\Response\       Response 会话管理服务
 * @see \App\Services\Protocol\Driver\OpenAIResponses\  Responses API 协议驱动
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
     * 关联 ApiKey（多对一）
     *
     * @see ApiKey
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * 获取上一次响应（对话链上游节点）
     *
     * 通过 previous_response_id 查找上一轮响应，仅返回未过期的记录。
     * 返回 null 表示当前节点为对话链起点或上游已过期。
     *
     * @return self|null 上一条响应，不存在或已过期时返回 null
     *
     * @see self::getConversationChain() 获取完整对话链
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
     * 查找有效会话（未过期）
     *
     * 根据 response_id 查找会话，可选限定 api_key_id 进行权限校验。
     * 仅返回未过期的记录。
     *
     * @param  string  $responseId  响应 ID
     * @param  int|null  $apiKeyId  API Key ID（可选，用于权限隔离）
     * @return self|null 有效会话，不存在或已过期时返回 null
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
     *
     * 从当前节点向上追溯，统计对话链中的有效节点总数。
     * 遇到已过期或不存在的上游节点时停止。
     *
     * @return int 对话链长度（至少为 1，即当前节点自身）
     *
     * @see self::getConversationChain() 获取完整对话链内容
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
     * 先向上追溯到对话链起点（根节点），再反转为从最早到当前的顺序。
     * 返回的数组中第一个元素为对话链起点，最后一个为当前会话。
     *
     * @return array<ResponseSession> 按时间顺序排列的会话数组
     *
     * @see self::getChainLength() 仅获取链长度
     * @see self::previous()       向上追溯单步
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
     * 判断会话是否已过期
     *
     * @return bool true 表示已过期，不可继续使用
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * 获取消息数量
     *
     * 优先返回数据库中的 message_count 字段值；
     * 若为空则回退到 messages 数组的实际长度。
     *
     * @return int 消息条数
     */
    public function getMessageCount(): int
    {
        return count($this->messages ?? []);
    }
}
