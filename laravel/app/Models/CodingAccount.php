<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodingAccount extends Model
{
    use HasFactory;

    /**
     * 状态常量
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ERROR = 'error';

    /**
     * 平台类型常量
     */
    public const PLATFORM_ALIYUN = 'aliyun';
    public const PLATFORM_VOLCANO = 'volcano';
    public const PLATFORM_ZHIPU = 'zhipu';
    public const PLATFORM_GITHUB = 'github';
    public const PLATFORM_CURSOR = 'cursor';
    public const PLATFORM_CUSTOM = 'custom';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'platform',
        'driver_class',
        'credentials',
        'status',
        'quota_config',
        'quota_cached',
        'config',
        'last_sync_at',
        'sync_error',
        'sync_error_count',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'quota_config' => 'array',
            'quota_cached' => 'array',
            'config' => 'array',
            'last_sync_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * 关联的渠道
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'coding_account_id');
    }

    /**
     * 使用记录
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(CodingUsageLog::class, 'account_id');
    }

    /**
     * 状态变更日志
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(CodingStatusLog::class, 'account_id');
    }

    /**
     * 检查账户是否可用
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 检查账户是否已耗尽
     */
    public function isExhausted(): bool
    {
        return $this->status === self::STATUS_EXHAUSTED;
    }

    /**
     * 检查账户是否已过期
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * 获取配额配置
     */
    public function getQuotaConfig(): array
    {
        return $this->quota_config ?? [
            'limits' => [],
            'thresholds' => [
                'warning' => 0.80,
                'critical' => 0.90,
                'disable' => 0.95,
            ],
            'cycle' => 'monthly',
            'reset_day' => 1,
        ];
    }

    /**
     * 获取凭证
     */
    public function getCredentials(): array
    {
        return $this->credentials ?? [];
    }

    /**
     * 获取驱动特定配置
     */
    public function getDriverConfig(): array
    {
        return $this->config ?? [];
    }

    /**
     * 获取平台列表
     *
     * @return array<string, string>
     */
    public static function getPlatforms(): array
    {
        return [
            self::PLATFORM_ALIYUN => '阿里云百炼',
            self::PLATFORM_VOLCANO => '火山方舟',
            self::PLATFORM_ZHIPU => '智谱GLM',
            self::PLATFORM_GITHUB => 'GitHub',
            self::PLATFORM_CURSOR => 'Cursor',
            self::PLATFORM_CUSTOM => '自定义',
        ];
    }

    /**
     * 获取状态列表
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => '正常',
            self::STATUS_WARNING => '警告',
            self::STATUS_CRITICAL => '临界',
            self::STATUS_EXHAUSTED => '耗尽',
            self::STATUS_EXPIRED => '过期',
            self::STATUS_SUSPENDED => '暂停',
            self::STATUS_ERROR => '错误',
        ];
    }

    /**
     * 获取状态颜色
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_WARNING => 'warning',
            self::STATUS_CRITICAL => 'danger',
            self::STATUS_EXHAUSTED => 'gray',
            self::STATUS_EXPIRED => 'gray',
            self::STATUS_SUSPENDED => 'gray',
            self::STATUS_ERROR => 'danger',
            default => 'gray',
        };
    }

    /**
     * 获取平台图标
     */
    public function getPlatformIcon(): string
    {
        return match ($this->platform) {
            self::PLATFORM_ALIYUN => 'heroicon-o-cloud',
            self::PLATFORM_VOLCANO => 'heroicon-o-fire',
            self::PLATFORM_ZHIPU => 'heroicon-o-brain',
            self::PLATFORM_GITHUB => 'heroicon-o-code-bracket',
            self::PLATFORM_CURSOR => 'heroicon-o-cursor-arrow-rays',
            default => 'heroicon-o-cog',
        };
    }
}
