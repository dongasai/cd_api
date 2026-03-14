<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelAffinityRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'model_patterns',
        'path_patterns',
        'user_agent_patterns',
        'key_sources',
        'key_combine_strategy',
        'ttl_seconds',
        'param_override_template',
        'skip_retry_on_failure',
        'include_group_in_key',
        'is_enabled',
        'priority',
        'hit_count',
        'last_hit_at',
    ];

    protected $attributes = [
        'key_combine_strategy' => 'first',
        'ttl_seconds' => 120,
        'skip_retry_on_failure' => false,
        'include_group_in_key' => false,
        'is_enabled' => true,
        'priority' => 0,
        'hit_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'user_agent_patterns' => 'array',
            'key_sources' => 'array',
            'param_override_template' => 'array',
            'skip_retry_on_failure' => 'boolean',
            'include_group_in_key' => 'boolean',
            'is_enabled' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
