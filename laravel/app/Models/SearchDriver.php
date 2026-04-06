<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 搜索驱动配置模型
 *
 * @property int $id
 * @property string $name 驱动名称
 * @property string $slug 驱动标识
 * @property string $driver_class 驱动类名
 * @property array|null $config 驱动配置
 * @property int $timeout 请求超时秒数
 * @property int $priority 优先级
 * @property bool $is_default 是否为默认驱动
 * @property string $status 状态
 * @property string|null $description 描述
 * @property \Carbon\Carbon|null $last_used_at 最后使用时间
 * @property int $usage_count 使用次数
 * @property string|null $error_message 错误信息
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
     */
    public function getStatusLabel(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * 是否活跃
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 是否默认驱动
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * 搜索记录关联
     */
    public function searchLogs(): HasMany
    {
        return $this->hasMany(SearchLog::class, 'driver_id');
    }

    /**
     * 标记使用
     */
    public function markUsed(): void
    {
        $this->usage_count++;
        $this->last_used_at = now();
        $this->save();
    }

    /**
     * 标记错误
     */
    public function markError(string $message): void
    {
        $this->status = self::STATUS_ERROR;
        $this->error_message = $message;
        $this->save();
    }

    /**
     * 获取默认驱动
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    /**
     * 获取可用的驱动列表(按优先级排序)
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
     */
    public function setAsDefault(): void
    {
        // 先清除其他默认
        static::where('is_default', true)->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }
}