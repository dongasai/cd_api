<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * 获取服务器时间工具
 *
 * 返回服务器当前时间，支持指定时区
 */
#[Description('获取服务器当前时间，返回包含多种格式的结构化对象：iso8601、timestamp、datetime、date、time、timezone、day_of_week、day_name、is_weekend')]
class GetServerTimeTool extends Tool
{
    /**
     * 处理工具请求
     *
     * @param  Request  $request  MCP 请求对象
     * @return Response MCP 响应对象
     */
    public function handle(Request $request): Response
    {
        // 获取时区参数，默认 Asia/Shanghai
        $timezone = $request->get('timezone', 'Asia/Shanghai');

        // 验证时区是否有效
        try {
            $time = now()->setTimezone($timezone);
        } catch (\Exception $e) {
            return Response::error('无效的时区: '.$timezone);
        }

        // 返回结构化时间对象
        return Response::json([
            'iso8601' => $time->toIso8601String(),
            'timestamp' => $time->timestamp,
            'datetime' => $time->format('Y-m-d H:i:s'),
            'date' => $time->format('Y-m-d'),
            'time' => $time->format('H:i:s'),
            'timezone' => $timezone,
            'timezone_offset' => $time->format('P'),
            'day_of_week' => $time->dayOfWeekIso, // 1-7, 周一为1
            'day_name' => $this->getDayName($time->dayOfWeekIso),
            'is_weekend' => $time->isWeekend(),
        ]);
    }

    /**
     * 获取星期名称
     */
    protected function getDayName(int $dayOfWeek): string
    {
        $days = [
            1 => '周一',
            2 => '周二',
            3 => '周三',
            4 => '周四',
            5 => '周五',
            6 => '周六',
            7 => '周日',
        ];

        return $days[$dayOfWeek] ?? '';
    }

    /**
     * 定义工具输入参数 Schema
     *
     * @param  JsonSchema  $schema  Schema 构建器
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()
                ->description('目标时区，默认 Asia/Shanghai（如：UTC、America/New_York）')
                ->default('Asia/Shanghai'),
        ];
    }
}
