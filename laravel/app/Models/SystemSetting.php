<?php

namespace App\Models;

use App\Admin\Controllers\SystemSettingController;
use App\Enums\SettingGroup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 系统设置模型
 *
 * 用于存储和管理系统的键值对配置，支持多种数据类型（字符串、整数、浮点数、布尔值、JSON、数组）。
 * 按分组管理配置，支持公开/私密设置，用于系统级参数、功能开关等场景。
 *
 * ┌─────────────┬─────────────────────┬───────────┬───────────────────────────────────┐
 * │ 字段名      │ 类型                │ 可空      │ 说明                              │
 * ├─────────────┼─────────────────────┼───────────┼───────────────────────────────────┤
 * │ id          │ bigint unsigned     │ 否        │ 主键，自增                        │
 * │ group       │ varchar(50)         │ 否        │ 配置分组，默认 'system'           │
 * │ key         │ varchar(100)        │ 否        │ 配置键名                          │
 * │ value       │ text                │ 是        │ 配置值                            │
 * │ type        │ enum                │ 否        │ 值类型，默认 'string'             │
 * │ label       │ varchar(100)        │ 是        │ 显示标签                          │
 * │ description │ text                │ 是        │ 配置说明                          │
 * │ is_public   │ tinyint(1)          │ 否        │ 是否公开，默认 0                  │
 * │ sort_order  │ int unsigned        │ 否        │ 排序，默认 0                      │
 * │ created_at  │ timestamp           │ 是        │ 创建时间                          │
 * │ updated_at  │ timestamp           │ 是        │ 更新时间                          │
 * └─────────────┴─────────────────────┴───────────┴───────────────────────────────────┘
 *
 * 索引说明：
 * - PRIMARY: id
 * - INDEX: group
 * - UNIQUE: group + key
 *
 * 迁移历史：
 * - 2026_03_08_105449: 创建 system_settings 表
 *
 * 核心功能：
 * 1. 支持多种数据类型：字符串、整数、浮点数、布尔值、JSON、数组
 * 2. 按分组管理配置，便于组织和检索
 * 3. 支持公开/私密设置控制访问权限
 * 4. 提供类型安全的值获取和设置方法
 * 5. 支持分组和键的唯一性约束
 *
 * @property int $id 主键ID
 * @property SettingGroup $group 配置分组
 * @property string $key 配置键名
 * @property string|null $value 配置值（原始存储）
 * @property string $type 值类型（string/integer/float/boolean/json/array）
 * @property string|null $label 显示标签
 * @property string|null $description 配置说明
 * @property bool $is_public 是否公开
 * @property int $sort_order 排序
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 *
 * @see SettingGroup 配置分组枚举
 * @see SystemSettingController 后台管理控制器
 */
class SystemSetting extends Model
{
    use HasFactory;

    /** 类型常量：字符串 */
    public const TYPE_STRING = 'string';

    /** 类型常量：整数 */
    public const TYPE_INTEGER = 'integer';

    /** 类型常量：浮点数 */
    public const TYPE_FLOAT = 'float';

    /** 类型常量：布尔值 */
    public const TYPE_BOOLEAN = 'boolean';

    /** 类型常量：JSON对象 */
    public const TYPE_JSON = 'json';

    /** 类型常量：数组 */
    public const TYPE_ARRAY = 'array';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'sort_order' => 'integer',
            'group' => SettingGroup::class,
        ];
    }

    /**
     * 获取所有分组选项
     *
     * 返回 SettingGroup 枚举定义的所有分组选项。
     *
     * @return array 分组选项数组，键为分组值，值为分组标签
     *
     * @see SettingGroup::options()
     */
    public static function getGroups(): array
    {
        return SettingGroup::options();
    }

    /**
     * 获取所有类型选项
     *
     * 返回系统支持的所有配置值类型及其中文标签。
     *
     * @return array 类型选项数组，键为类型常量，值为中文标签
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_STRING => '字符串',
            self::TYPE_INTEGER => '整数',
            self::TYPE_FLOAT => '浮点数',
            self::TYPE_BOOLEAN => '布尔值',
            self::TYPE_JSON => 'JSON对象',
            self::TYPE_ARRAY => '数组',
        ];
    }

    /**
     * 获取类型化的配置值
     *
     * 根据 type 字段将存储的字符串值转换为对应类型的值。
     * - integer: 转换为整数
     * - float: 转换为浮点数
     * - boolean: 转换为布尔值（支持 '1', 'true', 'on', 'yes' 等）
     * - json/array: 解析为数组
     * - string: 原样返回
     *
     * @return mixed 转换后的配置值
     *
     * @see self::TYPE_STRING
     * @see self::TYPE_INTEGER
     * @see self::TYPE_FLOAT
     * @see self::TYPE_BOOLEAN
     * @see self::TYPE_JSON
     * @see self::TYPE_ARRAY
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            self::TYPE_INTEGER => (int) $this->value,
            self::TYPE_FLOAT => (float) $this->value,
            self::TYPE_BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_JSON, self::TYPE_ARRAY => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * 设置 value 属性的访问器
     *
     * 自动将不同类型的值转换为字符串存储：
     * - 数组：编码为 JSON
     * - 布尔值：转换为 '1' 或 '0'
     * - 其他：强制转为字符串
     *
     * @param  mixed  $value  要设置的值
     */
    public function setValueAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $this->attributes['value'] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($value)) {
            $this->attributes['value'] = $value ? '1' : '0';
        } else {
            $this->attributes['value'] = (string) $value;
        }
    }

    /**
     * 获取分组标签
     *
     * @return string 分组的中文标签
     *
     * @see SettingGroup::label()
     */
    public function getGroupLabel(): string
    {
        return $this->group->label();
    }

    /**
     * 获取类型标签
     *
     * @return string 类型的中文标签，如果类型未知则返回原始类型值
     *
     * @see self::getTypes()
     */
    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * 根据分组和键查找设置
     *
     * 使用 group + key 唯一索引进行精确查找。
     *
     * @param  string  $group  配置分组
     * @param  string  $key  配置键名
     * @return self|null 找到返回模型实例，否则返回 null
     */
    public static function findByKey(string $group, string $key): ?self
    {
        return static::where('group', $group)->where('key', $key)->first();
    }

    /**
     * 获取配置值
     *
     * 根据分组和键获取配置值，如果不存在则返回默认值。
     * 返回值会自动根据 type 字段进行类型转换。
     *
     * @param  string  $group  配置分组
     * @param  string  $key  配置键名
     * @param  mixed  $default  默认值（当配置不存在时返回）
     * @return mixed 类型化的配置值或默认值
     *
     * @see self::findByKey()
     * @see self::getTypedValue()
     */
    public static function getValue(string $group, string $key, mixed $default = null): mixed
    {
        $setting = static::findByKey($group, $key);

        return $setting ? $setting->getTypedValue() : $default;
    }

    /**
     * 设置配置值
     *
     * 创建或更新指定分组和键的配置值。
     * 如果配置已存在则更新，否则创建新记录。
     *
     * @param  string  $group  配置分组
     * @param  string  $key  配置键名
     * @param  mixed  $value  配置值
     * @param  string  $type  值类型，默认 TYPE_STRING
     * @return self 创建或更新后的模型实例
     *
     * @see self::TYPE_STRING
     * @see self::TYPE_INTEGER
     * @see self::TYPE_FLOAT
     * @see self::TYPE_BOOLEAN
     * @see self::TYPE_JSON
     * @see self::TYPE_ARRAY
     */
    public static function setValue(string $group, string $key, mixed $value, string $type = self::TYPE_STRING): self
    {
        return static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
