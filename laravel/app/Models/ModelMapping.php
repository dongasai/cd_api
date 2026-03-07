<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelMapping extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'alias',
        'actual_model',
        'channel_id',
        'enabled',
        'capabilities',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'capabilities' => 'array',
        ];
    }

    /**
     * 关联渠道
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }

    /**
     * 获取完整的模型标识（包含别名和实际模型）
     */
    public function getFullIdentifier(): string
    {
        return "{$this->alias} → {$this->actual_model}";
    }
}
