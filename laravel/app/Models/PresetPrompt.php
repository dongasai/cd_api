<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 预设提示词模型
 *
 * 管理模型测试用的预设提示词，支持变量模板和预设 HTTP 头部。
 * 通过分类组织提示词，供 ModelTestLog 关联使用。
 *
 * 数据表结构 (preset_prompts):
 * ┌──────────────────┬───────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 类型          │ 说明                                     │
 * ├──────────────────┼───────────────┼─────────────────────────────────────────┤
 * │ id               │ bigint unsigned│ 主键，自增                               │
 * │ name             │ varchar(100)  │ 提示词名称                                │
 * │ category         │ varchar(50)   │ 分类                                      │
 * │ content          │ text          │ 提示词内容                                │
 * │ variables        │ json          │ 变量模板                                  │
 * │ headers          │ json          │ 预设 HTTP 头部信息                        │
 * │ is_enabled       │ tinyint(1)    │ 是否启用（默认1）                          │
 * │ sort_order       │ int unsigned  │ 排序（默认0）                              │
 * │ created_at       │ timestamp     │ 创建时间                                  │
 * │ updated_at       │ timestamp     │ 更新时间                                  │
 * └──────────────────┴───────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (category, is_enabled) - 按分类+启用状态查询
 * - INDEX (sort_order) - 按排序查询
 *
 * 迁移历史：
 * - 2026_03_14_112508: 初始创建表
 *
 * 核心功能：
 * 1. 提示词管理：预设系统提示词供模型测试使用
 * 2. 变量模板：variables 字段支持动态变量替换
 * 3. 预设头部：headers 字段支持自定义 HTTP 请求头
 * 4. 分类组织：6 种分类（通用/编程/翻译/分析/写作/其他）
 *
 * @property int $id 主键
 * @property string $name 提示词名称
 * @property string $category 分类
 * @property string $content 提示词内容
 * @property array|null $variables 变量模板
 * @property array|null $headers 预设 HTTP 头部信息
 * @property bool $is_enabled 是否启用
 * @property int $sort_order 排序
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 *
 * @see ModelTestLog 模型测试日志
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
     *
     * @see ModelTestLog
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
            'general' => __('menu.preset_prompt_categories.general'),
            'programming' => __('menu.preset_prompt_categories.programming'),
            'translation' => __('menu.preset_prompt_categories.translation'),
            'analysis' => __('menu.preset_prompt_categories.analysis'),
            'writing' => __('menu.preset_prompt_categories.writing'),
            'other' => __('menu.preset_prompt_categories.other'),
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
