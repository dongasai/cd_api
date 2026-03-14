<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected const CACHE_KEY = 'system_settings';

    protected const CACHE_TTL = 3600;

    protected ?array $settings = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key, 2);
        $group = $parts[0] ?? 'system';
        $settingKey = $parts[1] ?? '';

        if (empty($settingKey)) {
            return $this->getGroup($group);
        }

        $settings = $this->all();

        $fullKey = $group.'.'.$settingKey;

        if (! isset($settings[$fullKey])) {
            return $default;
        }

        return $settings[$fullKey];
    }

    public function getGroup(string $group): array
    {
        $settings = $this->all();
        $result = [];

        foreach ($settings as $key => $value) {
            if (str_starts_with($key, $group.'.')) {
                $result[substr($key, strlen($group) + 1)] = $value;
            }
        }

        return $result;
    }

    public function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key, 2);
        $group = $parts[0] ?? 'system';
        $settingKey = $parts[1] ?? '';

        if (empty($settingKey)) {
            throw new \InvalidArgumentException('Setting key must be in format "group.key"');
        }

        $setting = SystemSetting::where('group', $group)
            ->where('key', $settingKey)
            ->first();

        if ($setting) {
            $setting->update(['value' => $value]);
        }

        $this->clearCache();
    }

    public function all(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        return $this->settings = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            function () {
                $settings = [];
                foreach (SystemSetting::all() as $setting) {
                    $settings[$setting->group->value.'.'.$setting->key] = $setting->getTypedValue();
                }

                return $settings;
            }
        );
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->settings = null;
    }

    public function getPublicSettings(): array
    {
        $settings = $this->all();
        $publicSettings = [];

        foreach (SystemSetting::where('is_public', true)->get() as $setting) {
            $key = $setting->group->value.'.'.$setting->key;
            if (isset($settings[$key])) {
                $publicSettings[$key] = $settings[$key];
            }
        }

        return $publicSettings;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }
}
