<?php

namespace App\Services\WebParser;

use App\Services\SettingService;
use OpenAI;
use Symfony\Component\Panther\Client;

/**
 * WebParser 服务
 *
 * 通过无头浏览器获取动态网页内容，使用 AI 处理提取高质量 Markdown 文本
 */
class WebParserService
{
    /**
     * 默认处理提示词
     */
    protected const DEFAULT_PROMPT = '提取网页主要内容，去除导航、广告、侧边栏等无关元素，返回干净的 Markdown 格式文本';

    /**
     * 页面加载等待时间（秒）
     */
    protected const WAIT_TIME = 3;

    /**
     * 页面加载超时时间（秒）
     */
    protected const PAGE_TIMEOUT = 30;

    /**
     * 内容预处理器
     */
    protected ContentPreprocessor $preprocessor;

    /**
     * 配置服务
     */
    protected SettingService $settingService;

    /**
     * 构造函数
     */
    public function __construct(ContentPreprocessor $preprocessor, SettingService $settingService)
    {
        $this->preprocessor = $preprocessor;
        $this->settingService = $settingService;
    }

    /**
     * 解析网页
     *
     * @param  string  $url  网页地址
     * @param  string|null  $prompt  处理提示词
     * @return array 解析结果
     */
    public function parse(string $url, ?string $prompt = null): array
    {
        $prompt = $prompt ?? self::DEFAULT_PROMPT;

        // 使用 Panther 获取网页内容
        $html = $this->fetchHtml($url);
        $title = $this->preprocessor->extractTitle($html);

        // 预处理 HTML
        $cleanContent = $this->preprocessor->process($html);

        // 使用 AI 处理内容
        $markdown = $this->processWithAI($cleanContent, $prompt, $title);

        return [
            'url' => $url,
            'title' => $title ?? '未知标题',
            'content' => $markdown,
            'parsed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 使用 Panther 获取网页 HTML
     *
     * @param  string  $url  网页地址
     * @return string HTML 内容
     *
     * @throws \RuntimeException 当页面加载失败时
     */
    protected function fetchHtml(string $url): string
    {
        $client = null;

        try {
            // 容器内 Chrome 需要特殊参数（--no-sandbox 等）
            $client = Client::createChromeClient(
                null,
                [
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--headless',
                    '--disable-software-rasterizer',
                    '--disable-extensions',
                ],
                [
                    'connection_timeout_in_sec' => 10,
                    'request_timeout_in_sec' => self::PAGE_TIMEOUT,
                ]
            );

            // 请求页面
            $crawler = $client->request('GET', $url);

            // 等待页面加载
            $client->waitFor('body', self::WAIT_TIME);

            // 获取页面内容
            return $crawler->html();
        } catch (\Facebook\WebDriver\Exception\TimeoutException $e) {
            throw new \RuntimeException('页面加载超时: '.$url);
        } catch (\Facebook\WebDriver\Exception\WebDriverException $e) {
            throw new \RuntimeException('页面加载失败: '.$e->getMessage());
        } finally {
            // 确保关闭浏览器
            if ($client) {
                $client->quit();
            }
        }
    }

    /**
     * 使用 AI 处理内容
     *
     * @param  string  $content  预处理后的内容
     * @param  string  $prompt  处理提示词
     * @param  string|null  $title  页面标题
     * @return string Markdown 格式文本
     *
     * @throws \RuntimeException 当配置缺失或请求失败时
     */
    protected function processWithAI(string $content, string $prompt, ?string $title): string
    {
        // 从配置读取 AI 参数
        $baseUrl = $this->settingService->get('mcp.webparser_base_url', 'http://127.0.0.1/api/openai/v1');
        $apiKey = $this->settingService->get('mcp.webparser_api_key');
        $model = $this->settingService->get('mcp.webparser_model', 'gpt-4o');
        $temperature = (float) $this->settingService->get('mcp.webparser_temperature', '0.3');

        // 验证必填配置
        if (empty($apiKey)) {
            throw new \RuntimeException('未配置 WebParser API Key，请在系统设置中配置 mcp.webparser_api_key');
        }

        // 创建 OpenAI 客户端
        $client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri($baseUrl)
            ->make();

        // 构建请求消息
        $systemPrompt = "你是一个网页内容提取助手。你的任务是根据用户的要求处理网页内容，返回清晰的 Markdown 格式文本。\n\n网页标题: {$title}";

        $userMessage = "{$prompt}\n\n---\n\n网页内容:\n{$content}";

        // 发送请求
        $response = $client->chat()->create([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => $temperature,
            'max_tokens' => 2000,  // 限制输出长度，减少处理时间
        ]);

        return $response->choices[0]->message->content;
    }
}
