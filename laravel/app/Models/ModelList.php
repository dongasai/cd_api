<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 模型列表模型
 *
 * 管理系统支持的 AI 模型信息，包括模型别名、定价、能力等元数据。
 * 提供统一的模型注册中心，用于模型匹配、路由降级和计费计算。
 *
 * 数据表结构 (model_lists):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ model_name               │ varchar(100)          │ 模型名称（唯一）                          │
 * │ display_name             │ varchar(100)          │ 显示名称                                  │
 * │ provider                 │ varchar(50)           │ 提供商                                    │
 * │ hugging_face_id          │ varchar(100)          │ Hugging Face 模型 ID                      │
 * │ common_name              │ varchar(100)          │ 通用名字                                  │
 * │ aliases                  │ json                  │ 模型别名列表                              │
 * │ description              │ text                  │ 描述                                      │
 * │ capabilities             │ json                  │ 能力列表                                  │
 * │ context_length           │ int unsigned          │ 上下文长度                                │
 * │ pricing_prompt           │ decimal(10,6)         │ 输入价格（每百万 token）                   │
 * │ pricing_completion       │ decimal(10,6)         │ 输出价格（每百万 token）                   │
 * │ pricing_input_cache_read │ decimal(10,6)         │ 缓存读取价格（每百万 token）               │
 * │ is_enabled               │ tinyint(1)            │ 是否启用（默认1）                          │
 * │ config                   │ json                  │ 额外配置                                  │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * │ deleted_at               │ timestamp             │ 软删除时间                                │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - UNIQUE (model_name) - 模型名称唯一约束
 * - INDEX (provider) - 按提供商查询
 * - INDEX (is_enabled) - 按启用状态查询
 *
 * 迁移历史：
 * - 2026_03_07_204713: 初始创建表
 * - 2026_03_09_181126: 新增 hugging_face_id、common_name、定价字段
 * - 2026_03_29_022927: 新增 aliases 字段（模型别名列表）
 *
 * 核心功能：
 * 1. 模型注册：统一管理系统支持的模型列表
 * 2. 模型别名：aliases 字段支持多名称匹配和路由降级
 * 3. 定价管理：支持输入/输出/缓存读取三维度定价
 * 4. 能力标识：capabilities 标记模型支持的功能
 * 5. 模型匹配：getAllNames() 提供模型名称+别名合集
 *
 * @property int $id 主键
 * @property string $model_name 模型名称（唯一）
 * @property string|null $display_name 显示名称
 * @property string|null $provider 提供商
 * @property string|null $hugging_face_id Hugging Face 模型 ID
 * @property string|null $common_name 通用名字
 * @property array|null $aliases 模型别名列表
 * @property string|null $description 描述
 * @property array|null $capabilities 能力列表
 * @property int|null $context_length 上下文长度
 * @property string|null $pricing_prompt 输入价格（每百万 token）
 * @property string|null $pricing_completion 输出价格（每百万 token）
 * @property string|null $pricing_input_cache_read 缓存读取价格（每百万 token）
 * @property bool $is_enabled 是否启用
 * @property array|null $config 额外配置
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 *
 * @see ChannelModel 渠道模型配置
 */
class ModelList extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * 检查模型是否启用
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled === true;
    }

    /**
     * 获取显示名称
     *
     * 优先返回 display_name，未设置则返回 model_name
     */
    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->model_name;
    }

    /**
     * 获取模型别名列表
     *
     * 用于模型匹配和路由降级
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
