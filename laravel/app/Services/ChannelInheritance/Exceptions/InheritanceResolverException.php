<?php

namespace App\Services\ChannelInheritance\Exceptions;

/**
 * 渠道继承解析异常类
 *
 * 用于处理渠道继承关系解析过程中的错误
 */
class InheritanceResolverException extends \RuntimeException
{
    /**
     * 错误类型常量
     */
    public const TYPE_CIRCULAR_INHERITANCE = 'circular_inheritance';

    public const TYPE_DEPTH_EXCEEDED = 'depth_exceeded';

    public const TYPE_PARENT_NOT_FOUND = 'parent_not_found';

    public const TYPE_PARENT_SOFT_DELETED = 'parent_soft_deleted';

    public const TYPE_INVALID_CONFIG = 'invalid_config';

    public const TYPE_UNKNOWN = 'unknown_error';

    /**
     * 错误类型
     */
    protected string $errorType;

    /**
     * 涉及渠道 ID 列表
     */
    protected ?array $channelIds = null;

    /**
     * 构造函数
     *
     * @param  string  $message  错误消息
     * @param  string  $errorType  错误类型
     * @param  int  $code  错误码
     * @param  \Throwable|null  $previous  前一个异常
     * @param  array|null  $channelIds  涉及渠道 ID 列表
     */
    public function __construct(
        string $message,
        string $errorType = self::TYPE_UNKNOWN,
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $channelIds = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
        $this->channelIds = $channelIds;
    }

    /**
     * 获取错误类型
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * 获取涉及渠道 ID 列表
     */
    public function getChannelIds(): ?array
    {
        return $this->channelIds;
    }

    /**
     * 创建循环继承异常
     *
     * @param  array  $channelIds  形成循环的渠道 ID 列表
     * @param  string  $message  自定义错误消息
     */
    public static function circularInheritance(array $channelIds, ?string $message = null): self
    {
        $chain = implode(' -> ', $channelIds).' -> '.$channelIds[0];
        $msg = $message ?? "检测到循环继承: {$chain}";

        return new self($msg, self::TYPE_CIRCULAR_INHERITANCE, 0, null, $channelIds);
    }

    /**
     * 创建继承深度超限异常
     *
     * @param  int  $currentDepth  当前深度
     * @param  int  $maxDepth  最大允许深度
     * @param  array  $channelIds  继承链
     */
    public static function depthExceeded(int $currentDepth, int $maxDepth, array $channelIds): self
    {
        $chain = implode(' -> ', $channelIds);
        $msg = "继承深度超限: 当前 {$currentDepth} 层，最大允许 {$maxDepth} 层。继承链: {$chain}";

        return new self($msg, self::TYPE_DEPTH_EXCEEDED, 0, null, $channelIds);
    }

    /**
     * 创建父渠道不存在异常
     *
     * @param  int  $parentId  父渠道 ID
     * @param  int  $childId  子渠道 ID
     */
    public static function parentNotFound(int $parentId, int $childId): self
    {
        return new self(
            "父渠道不存在: parent_id={$parentId}，子渠道 ID={$childId}",
            self::TYPE_PARENT_NOT_FOUND,
            0,
            null,
            [$parentId, $childId]
        );
    }

    /**
     * 创建父渠道已软删除异常
     *
     * @param  int  $parentId  父渠道 ID
     * @param  int  $childId  子渠道 ID
     */
    public static function parentSoftDeleted(int $parentId, int $childId): self
    {
        return new self(
            "父渠道已被软删除: parent_id={$parentId}，子渠道 ID={$childId}",
            self::TYPE_PARENT_SOFT_DELETED,
            0,
            null,
            [$parentId, $childId]
        );
    }

    /**
     * 创建无效配置异常
     *
     * @param  string  $reason  无效原因
     * @param  array  $config  问题配置
     */
    public static function invalidConfig(string $reason, array $config = []): self
    {
        return new self(
            "继承配置无效: {$reason}",
            self::TYPE_INVALID_CONFIG,
            0,
            null,
            $config
        );
    }

    /**
     * 是否为可恢复错误
     *
     * 循环继承和配置错误需要人工修复，不属于临时错误
     */
    public function isRecoverable(): bool
    {
        return ! in_array($this->errorType, [
            self::TYPE_CIRCULAR_INHERITANCE,
            self::TYPE_INVALID_CONFIG,
        ]);
    }

    /**
     * 是否为父渠道问题
     *
     * 父渠道不存在或被删除属于数据问题
     */
    public function isParentIssue(): bool
    {
        return in_array($this->errorType, [
            self::TYPE_PARENT_NOT_FOUND,
            self::TYPE_PARENT_SOFT_DELETED,
        ]);
    }
}
