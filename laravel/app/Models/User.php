<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * 支持的货币列表
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
     * The attributes that are mass assignable.
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
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
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
     * @return string 货币代码 (如: USD, CNY)
     */
    public function getCurrency(): string
    {
        return $this->currency ?? 'USD';
    }

    /**
     * 设置用户货币
     *
     * @param  string  $currency  货币代码
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
     * @return string|null 货币名称
     */
    public function getCurrencyName(): ?string
    {
        return self::SUPPORTED_CURRENCIES[$this->getCurrency()] ?? null;
    }
}
