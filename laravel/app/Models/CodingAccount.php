<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Coding 账户模型
 *
 * 管理各平台的 Coding 账户，包括凭证、状态、配额、暂停等。
 * 通过 driver_class 关联到具体的 CodingStatus 驱动，实现不同平台的状态管理策略。
 *
 * 数据表结构 (coding_accounts):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ name                     │ varchar(255)          │ 账户名称                                  │
 * │ platform                 │ varchar(50)           │ 平台类型：aliyun/volcano/zhipu/           │
 * │                          │                       │           github/cursor/infini/custom      │
 * │ driver_class             │ varchar(255)          │ 驱动类名                                  │
 * │ credentials              │ json                  │ 平台凭证：{api_key, api_secret, ...}      │
 * │ status                   │ enum                  │ 状态：active/warning/critical/exhausted/  │
 * │                          │                       │       expired/suspended/error            │
 * │ config                   │ json                  │ 驱动特定配置                              │
 * │ status_override          │ json                  │ 状态覆盖配置：auto_disable, auto_enable,  │
 * │                          │                       │   disable_threshold, warning_threshold 等 │
 * │ last_sync_at             │ timestamp             │ 最后同步时间                              │
 * │ sync_error               │ text                  │ 同步错误信息                              │
 * │ sync_error_count         │ int unsigned          │ 连续同步错误次数（默认0）                  │
 * │ expires_at               │ timestamp             │ 账户过期时间                              │
 * │ disabled_at              │ timestamp             │ 禁用时间                                  │
 * │ pause_duration_minutes   │ int unsigned          │ 暂停时长（分钟）                          │
 * │ pause_reason             │ varchar(255)          │ 暂停原因                                  │
 * │ pause_rule_id            │ bigint unsigned       │ 触发暂停的规则 ID                         │
 * │ last_check_at            │ timestamp             │ 最后检查时间                              │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (platform) - 按平台查询
 * - INDEX (status) - 按状态查询
 * - INDEX (driver_class) - 按驱动查询
 * - INDEX (last_sync_at) - 按同步时间查询
 * - INDEX (expires_at) - 按过期时间查询
 *
 * 迁移历史：
 * - 2026_03_07_100000: 初始创建表（含 quota_config、quota_cached 字段）
 * - 2026_03_09_185419: 新增 disabled_at 字段
 * - 2026_03_13_000003: 移除 quota_config、quota_cached 字段（迁移到 coding_5zm_quotas）
 * - 2026_04_06_140903: 新增 pause_duration_minutes、pause_reason、pause_rule_id 字段
 *
 * 核心功能：
 * 1. 状态管理：7 种状态，支持自动/手动状态切换
 * 2. 暂停机制：支持定时暂停和自动恢复
 * 3. 错误规则暂停：通过 pause_rule_id 关联错误处理规则
 * 4. 状态覆盖：status_override 允许精细控制自动禁用/启用行为
 * 5. 多平台支持：7 种平台类型，通过 driver_class 映射到具体驱动
 *
 * @property int $id 主键
 * @property string $name 账户名称
 * @property string $platform 平台类型
 * @property string $driver_class 驱动类名
 * @property array $credentials 平台凭证
 * @property string $status 账户状态
 * @property array|null $config 驱动特定配置
 * @property array|null $status_override 状态覆盖配置
 * @property Carbon|null $last_sync_at 最后同步时间
 * @property string|null $sync_error 同步错误信息
 * @property int $sync_error_count 连续同步错误次数
 * @property Carbon|null $expires_at 账户过期时间
 * @property Carbon|null $disabled_at 禁用时间
 * @property int|null $pause_duration_minutes 暂停时长（分钟）
 * @property string|null $pause_reason 暂停原因
 * @property int|null $pause_rule_id 触发暂停的规则 ID
 * @property Carbon|null $last_check_at 最后检查时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 *
 * @see Channel 使用此账户的渠道
 * @see CodingUsageLog 使用日志
 * @see CodingStatusLog 状态变更日志
 * @see Coding5ZMQuota 5ZM 配额模型
 * @see ChannelErrorRule 错误规则（暂停关联）
 */
class CodingAccount extends Model
{
    use HasFactory;

    /**
     * 状态常量：正常
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * 状态常量：警告
     */
    public const STATUS_WARNING = 'warning';

    /**
     * 状态常量：临界
     */
    public const STATUS_CRITICAL = 'critical';

    /**
     * 状态常量：耗尽
     */
    public const STATUS_EXHAUSTED = 'exhausted';

    /**
     * 状态常量：过期
     */
    public const STATUS_EXPIRED = 'expired';

    /**
     * 状态常量：暂停
     */
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * 状态常量：错误
     */
    public const STATUS_ERROR = 'error';

    /**
     * 平台类型常量：阿里云百炼
     */
    public const PLATFORM_ALIYUN = 'aliyun';

    /**
     * 平台类型常量：火山方舟
     */
    public const PLATFORM_VOLCANO = 'volcano';

    /**
     * 平台类型常量：智谱GLM
     */
    public const PLATFORM_ZHIPU = 'zhipu';

    /**
     * 平台类型常量：GitHub
     */
    public const PLATFORM_GITHUB = 'github';

    /**
     * 平台类型常量：Cursor
     */
    public const PLATFORM_CURSOR = 'cursor';

    /**
     * 平台类型常量：无问芯穹
     */
    public const PLATFORM_INFINI = 'infini';

    /**
     * 平台类型常量：自定义
     */
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
        'config',
        'status_override',
        'last_sync_at',
        'sync_error',
        'sync_error_count',
        'expires_at',
        'disabled_at',
        'pause_duration_minutes',
        'pause_reason',
        'pause_rule_id',
        'last_check_at',
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
            'config' => 'array',
            'status_override' => 'array',
            'last_sync_at' => 'datetime',
            'last_check_at' => 'datetime',
            'expires_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * 关联的渠道
     *
     * 一个账户可被多个渠道使用
     *
     * @see Channel
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'coding_account_id');
    }

    /**
     * 使用记录
     *
     * @see CodingUsageLog
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(CodingUsageLog::class, 'account_id');
    }

    /**
     * 状态变更日志
     *
     * @see CodingStatusLog
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
     * 获取配额配置（已弃用 - 使用驱动特定表）
     *
     * @deprecated 使用驱动特定的配额模型代替，如 Coding5ZMQuota
     * @see Coding5ZMQuota
     */
    public function getQuotaConfig(): array
    {
        // 返回默认配置，实际配置现在存储在驱动特定的表中
        return [
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
            self::PLATFORM_INFINI => '无问芯穹',
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
     *
     * 用于后台展示的标签颜色映射
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
     *
     * 返回 Heroicon 图标名，用于后台展示
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

    /**
     * 获取自动重新开启小时数
     */
    public function getAutoReopenHours(): int
    {
        $config = $this->getQuotaConfig();

        return (int) ($config['auto_reopen_hours'] ?? 0);
    }

    /**
     * 检查是否应该自动重新开启
     *
     * 条件：auto_reopen_hours > 0 且状态为耗尽/暂停 且已超过指定时间
     */
    public function shouldAutoReopen(): bool
    {
        $hours = $this->getAutoReopenHours();

        if ($hours <= 0) {
            return false;
        }

        if (! in_array($this->status, [self::STATUS_EXHAUSTED, self::STATUS_SUSPENDED], true)) {
            return false;
        }

        if ($this->disabled_at === null) {
            return false;
        }

        return $this->disabled_at->addHours($hours)->isPast();
    }

    /**
     * 标记为禁用状态
     *
     * 同时设置 status 和 disabled_at
     */
    public function markAsDisabled(string $status): void
    {
        $this->update([
            'status' => $status,
            'disabled_at' => now(),
        ]);
    }

    /**
     * 重新开启账户
     *
     * 恢复为 active 状态，清除 disabled_at
     */
    public function reopen(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'disabled_at' => null,
        ]);
    }

    /**
     * 获取状态覆盖配置
     *
     * 控制自动禁用/启用行为、阈值、优先级和回退渠道
     *
     * @return array{
     *   auto_disable: bool,
     *   auto_enable: bool,
     *   disable_threshold: float,
     *   warning_threshold: float,
     *   priority: int,
     *   fallback_channel_id: int|null
     * }
     */
    public function getStatusOverride(): array
    {
        return $this->status_override ?? [
            'auto_disable' => true,
            'auto_enable' => true,
            'disable_threshold' => 0.95,
            'warning_threshold' => 0.80,
            'priority' => 1,
            'fallback_channel_id' => null,
        ];
    }

    /**
     * 检查是否允许自动禁用
     */
    public function allowsAutoDisable(): bool
    {
        $override = $this->getStatusOverride();

        return $override['auto_disable'] ?? true;
    }

    /**
     * 检查是否允许自动启用
     */
    public function allowsAutoEnable(): bool
    {
        $override = $this->getStatusOverride();

        return $override['auto_enable'] ?? true;
    }

    /**
     * 获取禁用阈值
     */
    public function getDisableThreshold(): float
    {
        $override = $this->getStatusOverride();

        return (float) ($override['disable_threshold'] ?? 0.95);
    }

    /**
     * 获取警告阈值
     */
    public function getWarningThreshold(): float
    {
        $override = $this->getStatusOverride();

        return (float) ($override['warning_threshold'] ?? 0.80);
    }

    /**
     * 更新最后检查时间
     */
    public function updateLastCheckAt(): void
    {
        $this->update(['last_check_at' => now()]);
    }

    /**
     * 检查账户是否暂停中
     *
     * 需同时满足：状态为 suspended、有暂停时长、有禁用时间
     */
    public function isPaused(): bool
    {
        return $this->status === self::STATUS_SUSPENDED
            && $this->pause_duration_minutes !== null
            && $this->disabled_at !== null;
    }

    /**
     * 检查账户是否应该自动恢复（基于 pause_duration_minutes）
     *
     * 当 disabled_at + pause_duration_minutes 已过时，应自动恢复
     *
     * @see ChannelErrorRule 通过错误规则暂停的账户
     */
    public function shouldAutoRecoverFromPause(): bool
    {
        if (! $this->isPaused()) {
            return false;
        }

        $recoverAt = $this->disabled_at->addMinutes($this->pause_duration_minutes);

        return now()->gte($recoverAt);
    }

    /**
     * 获取暂停恢复时间
     *
     * 返回 disabled_at + pause_duration_minutes 的计算结果
     */
    public function getPauseRecoverAt(): ?Carbon
    {
        if (! $this->isPaused()) {
            return null;
        }

        return $this->disabled_at->addMinutes($this->pause_duration_minutes);
    }

    /**
     * 检查是否因错误规则暂停
     *
     * @see ChannelErrorRule 错误处理规则
     */
    public function isPausedByErrorRule(): bool
    {
        return $this->isPaused() && $this->pause_rule_id !== null;
    }
}
