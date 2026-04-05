<?php

namespace App\Mcp\Tools;

use App\Services\WebParser\WebParserService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * WebParser MCP 工具
 *
 * 通过无头浏览器获取动态网页内容，使用 AI 处理提取高质量 Markdown 文本
 */
#[Description('网页解析工具：通过无头浏览器获取动态网页内容，使用 AI 提取高质量 Markdown 文本。参数：url（必需）- 网页地址，prompt（可选）- 处理提示词')]
class WebParserTool extends Tool
{
    /**
     * WebParser 服务实例
     */
    protected WebParserService $service;

    /**
     * 构造函数
     */
    public function __construct(WebParserService $service)
    {
        $this->service = $service;
    }

    /**
     * 处理工具请求
     *
     * @param  Request  $request  MCP 请求对象
     * @return Response MCP 响应对象
     */
    public function handle(Request $request): Response
    {
        $url = $request->get('url', '');
        $prompt = $request->get('prompt');

        // 验证 URL
        if (empty($url)) {
            return Response::error('URL 不能为空');
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return Response::error('无效的 URL 格式');
        }

        try {
            $result = $this->service->parse($url, $prompt);

            // 返回 Markdown 格式文本
            $markdown = "# {$result['title']}\n\n";
            $markdown .= "**来源**: {$result['url']}\n\n";
            $markdown .= "**解析时间**: {$result['parsed_at']}\n\n";
            $markdown .= "---\n\n";
            $markdown .= $result['content'];

            return Response::json([
                'format' => 'markdown',
                'content' => $markdown,
            ]);
        } catch (\RuntimeException $e) {
            return Response::error('解析失败: '.$e->getMessage());
        } catch (\Exception $e) {
            return Response::error('解析过程发生错误: '.$e->getMessage());
        }
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
            'url' => $schema->string()
                ->description('网页地址，必须为有效的 HTTP/HTTPS URL')
                ->required(),
            'prompt' => $schema->string()
                ->description('处理提示词，指导 AI 如何处理网页内容。不传则使用默认提示词'),
        ];
    }
}
