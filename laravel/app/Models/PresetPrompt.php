<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 预设提示词模型
 */
class PresetPrompt extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'content',
        'variables',
        'headers',
        'is_enabled',
        'sort_order',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'headers' => 'array',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * 关联的测试日志
     */
    public function testLogs(): HasMany
    {
        return $this->hasMany(ModelTestLog::class, 'prompt_preset_id');
    }

    /**
     * 作用域：仅启用的提示词
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 作用域：按分类筛选
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 获取预设Headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers ?? [];
    }

    /**
     * 获取变量模板
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables ?? [];
    }

    /**
     * 获取所有分类选项
     *
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            'general' => '通用',
            'programming' => '编程',
            'translation' => '翻译',
            'analysis' => '分析',
            'writing' => '写作',
            'other' => '其他',
        ];
    }

    /**
     * 获取分类标签
     */
    public function getCategoryLabel(): string
    {
        return self::getCategories()[$this->category] ?? $this->category;
    }
}
