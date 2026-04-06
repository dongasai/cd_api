<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MCP 客户端模型
 *
 * 存储外部 MCP Server 的连接配置
 *
 * @property int $id
 * @property string $name 客户端名称
 * @property string $slug 标识符
 * @property string $transport 传输协议 (stdio|http_sse)
 * @property string|null $url HTTP+SSE URL
 * @property string|null $command stdio 命令
 * @property array|null $args stdio 参数
 * @property array|null $headers HTTP 请求头
 * @property int $timeout 连接超时秒数
 * @property string $status 状态 (active|inactive|error)
 * @property \Carbon\Carbon|null $last_connected_at 最后连接时间
 * @property string|null $connection_error 连接错误信息
 * @property array|null $capabilities 服务器能力列表
 * @property string|null $description 描述
 */
class McpClient extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 状态常量
     */
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_ERROR = 'error';

    /**
     * 传输协议常量
     */
    const TRANSPORT_STDIO = 'stdio';

    const TRANSPORT_HTTP = 'http';

    /**
     * 可填充字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'transport',
        'url',
        'command',
        'args',
        'headers',
        'timeout',
        'status',
        'last_connected_at',
        'connection_error',
        'capabilities',
        'description',
    ];

    /**
     * 字段类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'args' => 'array',
            'headers' => 'array',
            'capabilities' => 'array',
            'timeout' => 'integer',
            'last_connected_at' => 'datetime',
        ];
    }

    /**
     * 获取状态选项列表
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => '活跃',
            self::STATUS_INACTIVE => '未激活',
            self::STATUS_ERROR => '错误',
        ];
    }

    /**
     * 获取传输协议选项列表
     *
     * @return array<string, string>
     */
    public static function getTransports(): array
    {
        return [
            self::TRANSPORT_HTTP => 'HTTP+SSE',
            self::TRANSPORT_STDIO => 'Stdio',
        ];
    }

    /**
     * 获取状态标签
     */
    public function getStatusLabel(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * 获取传输协议标签
     */
    public function getTransportLabel(): string
    {
        return self::getTransports()[$this->transport] ?? $this->transport;
    }

    /**
     * 是否为 HTTP 传输
     */
    public function isHttp(): bool
    {
        return $this->transport === self::TRANSPORT_HTTP;
    }

    /**
     * 是否为 Stdio 传输
     */
    public function isStdio(): bool
    {
        return $this->transport === self::TRANSPORT_STDIO;
    }

    /**
     * 是否活跃状态
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 是否错误状态
     */
    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }
}
