<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_FLOAT = 'float';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_JSON = 'json';

    public const TYPE_ARRAY = 'array';

    public const GROUP_SYSTEM = 'system';

    public const GROUP_QUOTA = 'quota';

    public const GROUP_SECURITY = 'security';

    public const GROUP_FEATURES = 'features';

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
        ];
    }

    public static function getGroups(): array
    {
        return [
            self::GROUP_SYSTEM => '系统设置',
            self::GROUP_QUOTA => '配额设置',
            self::GROUP_SECURITY => '安全设置',
            self::GROUP_FEATURES => '功能开关',
        ];
    }

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

    public function getGroupLabel(): string
    {
        return self::getGroups()[$this->group] ?? $this->group;
    }

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    public static function findByKey(string $group, string $key): ?self
    {
        return static::where('group', $group)->where('key', $key)->first();
    }

    public static function getValue(string $group, string $key, mixed $default = null): mixed
    {
        $setting = static::findByKey($group, $key);

        return $setting ? $setting->getTypedValue() : $default;
    }

    public static function setValue(string $group, string $key, mixed $value, string $type = self::TYPE_STRING): self
    {
        return static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
