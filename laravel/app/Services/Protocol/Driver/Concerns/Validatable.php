<?php

namespace App\Services\Protocol\Driver\Concerns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 验证 Trait
 *
 * 提供基于 Laravel Validator 的验证能力
 */
trait Validatable
{
    /**
     * 定义验证规则
     *
     * @return array<string, string|array>
     */
    abstract public function validationRules(): array;

    /**
     * 验证当前实例
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        $data = $this->toArray();
        $rules = $this->validationRules();

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * 从数组创建并验证
     *
     * @param  array  $data  原始数据
     *
     * @throws ValidationException
     */
    public static function fromArrayValidated(array $data): static
    {
        $instance = static::fromArray($data);
        $instance->validate();

        return $instance;
    }

    /**
     * 获取验证错误信息
     *
     * @return array<string, string[]>
     */
    public function getValidationErrors(): array
    {
        $data = $this->toArray();
        $rules = $this->validationRules();

        $validator = Validator::make($data, $rules);

        return $validator->failed() ? $validator->errors()->toArray() : [];
    }

    /**
     * 转换为数组（供验证使用）
     */
    abstract public function toArray(): array;

    /**
     * 从数组创建实例
     */
    abstract public static function fromArray(array $data): static;
}
