# WebParser MCP 工具开发计划

## 任务概述
创建 WebParser MCP 工具，通过无头浏览器获取动态网页内容，使用 AI 处理提取高质量 Markdown 文本。

## 技术方案
- 无头浏览器：Symfony Panther
- AI 处理：OpenAI SDK（openai-php/laravel v0.19.0）
- 配置存储：system_settings 表

## 参数设计
- `url` (必需)：网页地址
- `prompt` (可选)：处理提示词，默认："提取网页主要内容，去除导航、广告、侧边栏等无关元素，返回干净的 Markdown 格式文本"

## 输出格式
Markdown 文本

## 配置项
| group | key | value | type | label | description |
|-------|-----|-------|------|-------|-------------|
| mcp | webparser_channel_id | null | integer | WebParser渠道ID | 用于AI处理的OpenAI渠道ID |
| mcp | webparser_model | gpt-4o | string | WebParser模型 | 用于AI处理的模型名称 |

## 文件结构
```
laravel/
├── app/Mcp/Tools/WebParserTool.php        # MCP 工具类
├── app/Services/WebParser/
│   ├── WebParserService.php              # 核心解析服务
│   └── ContentPreprocessor.php           # HTML 预处理
└── database/seeders/SystemSettingSeeder.php  # 配置项迁移
```

## 执行步骤
1. 安装 Symfony Panther 依赖
2. 新增配置项到 system_settings 表
3. 创建 ContentPreprocessor 服务
4. 创建 WebParserService 服务
5. 创建 WebParserTool MCP 工具
6. 注册工具到 CdApiServer
7. 代码格式化
8. 运行 Seeder 添加配置

## 创建时间
2026-04-05 22:22:40