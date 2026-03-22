<?php

namespace App\Services\Protocol\Driver\Concerns;

/**
 * Shared\DTO 转换 Trait
 *
 * 提供协议结构体与 Shared\DTO 之间的双向转换能力
 */
trait Convertible
{
    /**
     * 转换为 Shared\DTO 实例
     */
    abstract public function toSharedDTO(): object;

    /**
     * 从 Shared\DTO 创建实例
     *
     * @param  object  $dto  Shared\DTO 实例
     */
    abstract public static function fromSharedDTO(object $dto): static;
}
