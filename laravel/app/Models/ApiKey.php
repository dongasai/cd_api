<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'key_hash',
        'key_prefix',
        'permissions',
        'allowed_models',
        'model_mappings',
        'rate_limit',
        'expires_at',
        'last_used_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'allowed_models' => 'array',
            'model_mappings' => 'array',
            'rate_limit' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    public function getMaskedKey(): string
    {
        if (empty($this->key_prefix)) {
            return '未设置';
        }

        return $this->key_prefix.'...';
    }

    /**
     * 获取模型映射配置
     */
    public function getModelMappings(): array
    {
        return $this->model_mappings ?? [];
    }

    /**
     * 解析模型名称，如果映射存在则返回映射后的模型
     *
     * @param  string  $model  原始模型名称
     * @return string 映射后的模型名称
     */
    public function resolveModel(string $model): string
    {
        $mappings = $this->getModelMappings();

        return $mappings[$model] ?? $model;
    }
}
