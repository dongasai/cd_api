<?php

namespace App\Services\Protocol\Driver\Concerns;

/**
 * 协议响应 Trait
 *
 * 提供 ProtocolResponse 接口的默认实现
 */
trait ProtocolResponseTrait
{
    /**
     * 流式后处理（默认空实现）
     *
     * 大多数协议不需要流式后处理，空实现
     *
     * @param  array  $chunks  累积的流式块
     * @param  object|null  $context  协议上下文（从请求传递）
     */
    public function postStreamProcess(array $chunks, ?object $context = null): void
    {
        // 默认无操作
    }
}
