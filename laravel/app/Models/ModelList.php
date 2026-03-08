<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelList extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'display_name',
        'provider',
        'description',
        'capabilities',
        'context_length',
        'is_enabled',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'config' => 'array',
            'is_enabled' => 'boolean',
            'context_length' => 'integer',
        ];
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled === true;
    }

    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->model_name;
    }
}
