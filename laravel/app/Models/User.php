<?php

namespace App\Models;

use App\Admin\Controllers\UserController;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User 模型 - 应用程序用户
 *
 * 继承 Laravel 的 Authenticatable，提供认证功能和国际化支持。
 * 支持多语言和货币设置，与 Dcat Admin 后台管理集成。
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │ 字段名          │ 类型         │ 说明                           │
 * ├─────────────────────────────────────────────────────────────────┤
 * │ id              │ bigint       │ 主键，自增                     │
 * │ name            │ varchar(255) │ 用户名称                       │
 * │ email           │ varchar(255) │ 用户邮箱（唯一）               │
 * │ locale          │ varchar(10)  │ 用户界面语言，默认 zh_CN       │
 * │ currency        │ varchar(3)   │ 用户默认货币，默认 USD         │
 * │ email_verified_at│ timestamp   │ 邮箱验证时间（可为空）         │
 * │ password        │ varchar(255) │ 加密后的密码                   │
 * │ remember_token  │ varchar(100) │ 记住我令牌                     │
 * │ created_at      │ timestamp    │ 创建时间                       │
 * │ updated_at      │ timestamp    │ 更新时间                       │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * 索引：
 * - PRIMARY KEY (id)
 * - UNIQUE INDEX users_email_unique (email)
 *
 * 迁移历史：
 * - 2026_03_06_211700: 创建 users 表（基础字段：name, email, password）
 * - 2026_03_06_152747: 添加 locale 字段（用户界面语言）
 * - 2026_03_09_183305: 添加 currency 字段（用户默认货币）
 *
 * 核心功能：
 * 1. 用户认证（继承 Authenticatable）
 * 2. 多语言支持（locale 字段）
 * 3. 多货币支持（currency 字段）
 * 4. 通知功能（Notifiable trait）
 *
 * @property int $id 用户ID
 * @property string $name 用户名称
 * @property string $email 用户邮箱
 * @property string $locale 用户界面语言
 * @property string $currency 用户默认货币
 * @property Carbon|null $email_verified_at 邮箱验证时间
 * @property string $password 加密后的密码
 * @property string|null $remember_token 记住我令牌
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 *
 * @see Authenticatable 基类认证功能
 * @see UserController Dcat Admin 用户管理
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * 支持的货币列表
     *
     * 货币代码到货币名称的映射，用于国际化显示。
     *
     * @var array<string, string>
     */
    public const SUPPORTED_CURRENCIES = [
        'USD' => '美元',
        'CNY' => '人民币',
        'EUR' => '欧元',
        'GBP' => '英镑',
        'JPY' => '日元',
        'KRW' => '韩元',
        'HKD' => '港币',
        'TWD' => '新台币',
    ];

    /**
     * 批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'currency',
    ];

    /**
     * 序列化时隐藏的字段
     *
     * 防止敏感信息泄露。
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 获取字段类型转换定义
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 获取用户货币
     *
     * 如果未设置货币，返回默认值 USD。
     *
     * @return string 货币代码 (如: USD, CNY)
     *
     * @see SUPPORTED_CURRENCIES 支持的货币列表
     */
    public function getCurrency(): string
    {
        return $this->currency ?? 'USD';
    }

    /**
     * 设置用户货币
     *
     * 只接受 SUPPORTED_CURRENCIES 中定义的货币代码。
     * 自动转换为大写存储。
     *
     * @param  string  $currency  货币代码
     *
     * @see SUPPORTED_CURRENCIES 支持的货币列表
     */
    public function setCurrency(string $currency): void
    {
        $currency = strtoupper($currency);

        if (array_key_exists($currency, self::SUPPORTED_CURRENCIES)) {
            $this->currency = $currency;
            $this->save();
        }
    }

    /**
     * 获取货币名称
     *
     * 根据当前用户的货币代码返回对应的货币名称。
     *
     * @return string|null 货币名称，如果货币代码无效则返回 null
     *
     * @see getCurrency() 获取用户货币代码
     * @see SUPPORTED_CURRENCIES 支持的货币列表
     */
    public function getCurrencyName(): ?string
    {
        return self::SUPPORTED_CURRENCIES[$this->getCurrency()] ?? null;
    }
}
