<?php

namespace App\Models;

use App\Mcp\Servers\CdApiServer;
use App\Mcp\Tools;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MCP 客户端模型
 *
 * 存储外部 MCP Server 的连接配置，支持 stdio 和 HTTP 两种传输协议。
 * 用于管理系统连接的 MCP Server，包括连接状态、能力信息等。
 *
 * 数据表结构 (mcp_clients):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ name                     │ varchar(255)          │ 客户端名称                                │
 * │ slug                     │ varchar(100)          │ 标识符（唯一）                            │
 * │ transport                │ enum                  │ 传输协议：stdio/http（默认 http）          │
 * │ url                      │ varchar(500)          │ HTTP+SSE URL                             │
 * │ command                  │ varchar(500)          │ stdio 命令                                │
 * │ args                     │ json                  │ stdio 参数                                │
 * │ headers                  │ json                  │ HTTP 请求头                               │
 * │ timeout                  │ int unsigned          │ 连接超时秒数（默认30）                     │
 * │ status                   │ enum                  │ 状态：active/inactive/error（默认 inactive）│
 * │ last_connected_at        │ timestamp             │ 最后连接时间                              │
 * │ connection_error         │ text                  │ 连接错误信息                              │
 * │ capabilities             │ json                  │ 服务器能力列表                            │
 * │ description              │ text                  │ 描述                                      │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * │ deleted_at               │ timestamp             │ 软删除时间                                │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - UNIQUE (slug) - 标识符唯一约束
 * - INDEX (status) - 按状态查询
 * - INDEX (transport) - 按传输协议查询
 *
 * 迁移历史：
 * - 2026_04_06_131338: 初始创建表
 * - 2026_04_06_131855: transport 枚举值从 http_sse 改为 http（匹配 php-mcp/client）
 * - 2026_04_06_134200: 添加后台管理菜单
 *
 * 核心功能：
 * 1. 双协议支持：stdio（本地进程）和 HTTP（远程服务）
 * 2. 连接管理：跟踪连接状态和错误信息
 * 3. 能力发现：capabilities 存储服务器支持的工具列表
 * 4. 软删除：支持逻辑删除
 *
 * @property int $id 主键
 * @property string $name 客户端名称
 * @property string $slug 标识符（唯一）
 * @property string|null $transport 传输协议：stdio/http
 * @property string|null $url HTTP+SSE URL
 * @property string|null $command stdio 命令
 * @property array|null $args stdio 参数
 * @property array|null $headers HTTP 请求头
 * @property int $timeout 连接超时秒数
 * @property string $status 状态：active/inactive/error
 * @property Carbon|null $last_connected_at 最后连接时间
 * @property string|null $connection_error 连接错误信息
 * @property array|null $capabilities 服务器能力列表
 * @property string|null $description 描述
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 *
 * @see CdApiServer 本系统 MCP Server
 * @see Tools MCP 工具
 */
class McpClient extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 状态常量：活跃
     */
    const STATUS_ACTIVE = 'active';

    /**
     * 状态常量：未激活
     */
    const STATUS_INACTIVE = 'inactive';

    /**
     * 状态常量：错误
     */
    const STATUS_ERROR = 'error';

    /**
     * 传输协议常量：Stdio（本地进程）
     */
    const TRANSPORT_STDIO = 'stdio';

    /**
     * 传输协议常量：HTTP（远程服务）
     */
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
