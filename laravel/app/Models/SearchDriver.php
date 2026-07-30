<?php

namespace App\Models;

use App\Services\Search\Contracts\SearchDriverInterface;
use App\Services\Search\DriverManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 搜索驱动配置模型
 *
 * 管理搜索服务的驱动配置，支持多驱动切换、优先级排序和默认驱动设置。
 *
 * ┌─────────────────┬──────────────────┬──────────┬────────────────────────────────────────┐
 * │ 字段名          │ 类型             │ 可空     │ 说明                                   │
 * ├─────────────────┼──────────────────┼──────────┼────────────────────────────────────────┤
 * │ id              │ bigint unsigned  │ 否       │ 主键ID，自增                           │
 * │ name            │ varchar(50)      │ 否       │ 驱动名称，如"Bing搜索"                 │
 * │ slug            │ varchar(50)      │ 否       │ 驱动标识，唯一，如"bing"               │
 * │ driver_class    │ varchar(200)     │ 否       │ 驱动类全名，如"App\Services\Search..." │
 * │ config          │ json             │ 是       │ 驱动配置参数(JSON格式)                 │
 * │ timeout         │ int unsigned     │ 否       │ 请求超时秒数，默认30秒                 │
 * │ priority        │ int unsigned     │ 否       │ 优先级，数字越大优先级越高             │
 * │ is_default      │ tinyint(1)       │ 否       │ 是否为默认驱动，0/1                    │
 * │ status          │ enum             │ 否       │ 状态：active/inactive/error            │
 * │ description     │ text             │ 是       │ 驱动描述说明                           │
 * │ last_used_at    │ timestamp        │ 是       │ 最后使用时间                           │
 * │ usage_count     │ bigint unsigned  │ 否       │ 累计使用次数，默认0                    │
 * │ error_message   │ text             │ 是       │ 最后一次错误信息                       │
 * │ created_at      │ timestamp        │ 是       │ 创建时间                               │
 * │ updated_at      │ timestamp        │ 是       │ 更新时间                               │
 * │ deleted_at      │ timestamp        │ 是       │ 软删除时间                             │
 * └─────────────────┴──────────────────┴──────────┴────────────────────────────────────────┘
 *
 * 索引说明：
 * - PRIMARY: id (主键)
 * - UNIQUE: slug (唯一索引，驱动标识)
 * - INDEX: status (状态查询)
 * - INDEX: is_default (默认驱动查询)
 * - INDEX: priority (优先级排序)
 *
 * 迁移历史：
 * - 2026_04_06_153500: 创建 search_drivers 表
 *
 * 核心功能：
 * 1. 多驱动配置管理：支持配置多个搜索驱动，每个驱动独立配置参数
 * 2. 默认驱动机制：可设置一个默认驱动，自动用于无指定驱动的请求
 * 3. 优先级排序：支持按优先级获取可用驱动列表
 * 4. 状态管理：驱动可标记为活跃/未激活/错误三种状态
 * 5. 使用统计：自动记录驱动使用次数和最后使用时间
 * 6. 错误追踪：驱动出错时记录错误信息并切换状态
 *
 * @property int $id 主键ID
 * @property string $name 驱动名称
 * @property string $slug 驱动标识(唯一)
 * @property string $driver_class 驱动类全名
 * @property array|null $config 驱动配置(JSON)
 * @property int $timeout 请求超时秒数，默认30
 * @property int $priority 优先级，数字越大优先级越高
 * @property bool $is_default 是否为默认驱动
 * @property string $status 状态：active/inactive/error
 * @property string|null $description 驱动描述
 * @property Carbon|null $last_used_at 最后使用时间
 * @property int $usage_count 累计使用次数
 * @property string|null $error_message 错误信息
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 *
 * @see SearchDriverInterface 搜索驱动接口
 * @see DriverManager 驱动管理器
 * @see SearchLog 搜索日志模型
 */
class SearchDriver extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 状态常量
     */
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_ERROR = 'error';

    /**
     * 表名
     */
    protected $table = 'search_drivers';

    /**
     * 可填充字段
     */
    protected $fillable = [
        'name',
        'slug',
        'driver_class',
        'config',
        'timeout',
        'priority',
        'is_default',
        'status',
        'description',
        'last_used_at',
        'usage_count',
        'error_message',
    ];

    /**
     * 字段类型转换
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'timeout' => 'integer',
            'priority' => 'integer',
            'is_default' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * 获取状态选项列表
     *
     * 返回所有可用状态及其对应的中文标签。
     *
     * @return array<string, string> 状态键值对，如 ['active' => '活跃', ...]
     *
     * @see SearchDriver::getStatusLabel() 获取单个状态标签
     * @see SearchDriver::isActive() 检查是否活跃状态
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => '活跃',
            self::STATUS_INACTIVE => '未激活',
            self::STATUS_ERROR => '错误',
        ];
    }

    /**
     * 获取状态标签
     *
     * 将当前实例的状态值转换为可读的中文标签。
     *
     * @return string 状态标签，如"活跃"、"未激活"、"错误"
     *
     * @see SearchDriver::getStatuses() 获取完整状态映射表
     * @see SearchDriver::STATUS_ACTIVE 状态常量
     */
    public function getStatusLabel(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * 是否活跃
     *
     * 检查当前驱动实例是否处于活跃状态。
     *
     * @return bool 当前状态为 active 时返回 true
     *
     * @see SearchDriver::STATUS_ACTIVE 活跃状态常量
     * @see SearchDriver::markError() 标记错误状态方法
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 是否默认驱动
     *
     * 检查当前驱动实例是否被标记为默认驱动。
     *
     * @return bool 是默认驱动时返回 true
     *
     * @see SearchDriver::getDefault() 获取默认驱动
     * @see SearchDriver::setAsDefault() 设置为默认驱动
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * 搜索记录关联
     *
     * 建立与搜索日志模型的一对多关联关系。
     *
     * @return HasMany<SearchLog, self> 关联的搜索日志集合
     *
     * @see SearchLog 搜索日志模型
     * @see SearchDriver::markUsed() 标记使用时会创建搜索日志
     */
    public function searchLogs(): HasMany
    {
        return $this->hasMany(SearchLog::class, 'driver_id');
    }

    /**
     * 标记使用
     *
     * 增加使用计数并更新最后使用时间，通常在搜索请求完成后调用。
     *
     *
     * @see SearchDriver::usage_count 使用次数字段
     * @see SearchDriver::last_used_at 最后使用时间字段
     * @see SearchLog 搜索日志记录
     */
    public function markUsed(): void
    {
        $this->usage_count++;
        $this->last_used_at = now();
        $this->save();
    }

    /**
     * 标记错误
     *
     * 将驱动状态设置为错误并记录错误信息。
     *
     * @param  string  $message  错误信息描述
     *
     * @see SearchDriver::STATUS_ERROR 错误状态常量
     * @see SearchDriver::error_message 错误信息字段
     * @see SearchDriver::isActive() 检查是否活跃
     */
    public function markError(string $message): void
    {
        $this->status = self::STATUS_ERROR;
        $this->error_message = $message;
        $this->save();
    }

    /**
     * 获取默认驱动
     *
     * 查询被标记为默认且状态为活跃的驱动实例。
     *
     * @return self|null 默认驱动实例，不存在时返回 null
     *
     * @see SearchDriver::setAsDefault() 设置为默认驱动
     * @see SearchDriver::is_default 是否默认字段
     * @see SearchDriver::STATUS_ACTIVE 活跃状态
     * @see DriverManager 驱动管理器
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    /**
     * 获取可用的驱动列表(按优先级排序)
     *
     * 查询所有状态为活跃的驱动，按优先级降序排列。
     * 优先级相同时，默认驱动排在前面。
     *
     * @return array<array<string, mixed>> 驱动数据数组
     *
     * @see SearchDriver::priority 优先级字段
     * @see SearchDriver::is_default 是否默认字段
     * @see SearchDriver::STATUS_ACTIVE 活跃状态
     * @see DriverManager::getDriver() 获取驱动实例
     */
    public static function getAvailable(): array
    {
        return static::where('status', self::STATUS_ACTIVE)
            ->orderByDesc('priority')
            ->orderByDesc('is_default')
            ->get()
            ->toArray();
    }

    /**
     * 设置为默认驱动
     *
     * 将当前驱动设为默认，同时清除其他驱动的默认标记。
     * 确保系统中始终只有一个默认驱动。
     *
     *
     * @see SearchDriver::getDefault() 获取默认驱动
     * @see SearchDriver::is_default 是否默认字段
     * @see DriverManager::setDefaultDriver() 驱动管理器设置默认
     */
    public function setAsDefault(): void
    {
        // 先清除其他默认
        static::where('is_default', true)->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }
}
