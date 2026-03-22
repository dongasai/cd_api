<?php

namespace App\Services\Protocol\Driver\Concerns;

use JsonSerializable;

/**
 * JSON 序列化 Trait
 *
 * 实现 JsonSerializable 接口，提供 JSON 序列化/反序列化能力
 */
trait JsonSerializiable
{
    /**
     * 转换为 JSON 字符串
     *
     * @param  int  $options  JSON 编码选项
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 从 JSON 字符串创建实例
     *
     * @param  string  $json  JSON 字符串
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(
                'JSON 解析失败: '.json_last_error_msg()
            );
        }

        return static::fromArray($data);
    }

    /**
     * 转换为 JSON 序列化数据
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 转换为数组
     */
    abstract public function toArray(): array;

    /**
     * 从数组创建实例
     */
    abstract public static function fromArray(array $data): static;
}
