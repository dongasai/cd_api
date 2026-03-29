<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelList extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'display_name',
        'provider',
        'hugging_face_id',
        'common_name',
        'aliases',
        'description',
        'capabilities',
        'context_length',
        'pricing_prompt',
        'pricing_completion',
        'pricing_input_cache_read',
        'is_enabled',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'config' => 'array',
            'aliases' => 'array',
            'is_enabled' => 'boolean',
            'context_length' => 'integer',
            'pricing_prompt' => 'decimal:6',
            'pricing_completion' => 'decimal:6',
            'pricing_input_cache_read' => 'decimal:6',
        ];
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled === true;
    }

    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->model_name;
    }

    /**
     * 获取模型别名列表
     *
     * @return array 别名数组，如果没有别名则返回空数组
     */
    public function getAliases(): array
    {
        return $this->aliases ?? [];
    }

    /**
     * 获取模型名称和所有别名的合集
     *
     * 用于模型匹配和路由降级，包含模型自身名称和所有别名
     *
     * @return array 名称数组，格式: ['glm-5', 'GLM-5', 'z-ai/glm-5', ...]
     */
    public function getAllNames(): array
    {
        $names = [$this->model_name];
        $aliases = $this->getAliases();

        return array_unique(array_merge($names, $aliases));
    }
}
