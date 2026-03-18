<?php

use Dcat\Admin\Admin;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Admin::routes();

Route::group([
    'prefix' => config('admin.route.prefix'),
    'namespace' => config('admin.route.namespace'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {

    // 覆盖默认的用户设置路由，使用自定义控制器
    $router->get('auth/setting', 'AuthController@getSetting')->name('setting');
    $router->put('auth/setting', 'AuthController@putSetting');

    $router->get('/', 'HomeController@index');

    // API密钥管理
    $router->resource('api-keys', 'ApiKeyController');

    // 渠道管理
    $router->resource('channels', 'ChannelController');

    // 渠道模型配置
    $router->resource('channel-models', 'ChannelModelController');

    // 渠道分组管理
    $router->resource('channel-groups', 'ChannelGroupController');

    // 渠道标签管理
    $router->resource('channel-tags', 'ChannelTagController');

    // 模型列表管理
    $router->resource('model-lists', 'ModelListController');

    // Coding账户管理
    $router->resource('coding-accounts', 'CodingAccountController');

    // 系统设置管理
    $router->resource('system-settings', 'SystemSettingController');

    // 渠道亲和性规则
    $router->resource('channel-affinity-rules', 'ChannelAffinityRuleController');

    // 渠道亲和性缓存
    $router->resource('channel-affinity-cache', 'ChannelAffinityCacheController')->only(['index', 'show']);

    // User-Agent规则管理
    $router->resource('user-agents', 'UserAgentController');

    // 日志中心 - 只读
    $router->resource('audit-logs', 'AuditLogController')->only(['index', 'show']);
    $router->resource('request-logs', 'RequestLogController')->only(['index', 'show']);
    $router->resource('response-logs', 'ResponseLogController')->only(['index', 'show']);
    $router->resource('channel-request-logs', 'ChannelRequestLogController')->only(['index', 'show']);
    $router->resource('operation-logs', 'OperationLogController')->only(['index', 'show']);

    // JSON 字段预览
    $router->get('json-preview/{table}/{id}/{field}', 'JsonPreviewController@show')->name('json-preview');
    $router->get('json-preview-embed/{table}/{id}/{field}', 'JsonPreviewController@embed')->name('json-preview-embed');

    // SSE Chunks 预览
    $router->get('sse-chunks-embed/{table}/{id}/{field}', 'JsonPreviewController@sseChunksEmbed')->name('sse-chunks-embed');

    // 预设提示词管理
    $router->resource('preset-prompts', 'PresetPromptController');

    // 模型测试(旧版)
    $router->get('model-test/old', 'ModelTestController@index')->name('model-test.old');
    $router->match(['GET', 'POST'], 'model-test/old/test', 'ModelTestController@test')->name('model-test.old.test');
    $router->get('model-test/old/channel-models/{channel_id}', 'ModelTestController@getChannelModels')->name('model-test.old.channel-models');
    $router->get('model-test/old/logs', 'ModelTestController@grid')->name('model-test.old.logs');

    // 模型测试(OpenAI Chat UI)
    $router->get('model-test/openai', 'ModelTestController@openaiTest')->name('model-test.openai');

    // 渠道统计
    $router->get('channel-stats', 'ChannelStatsController@index')->name('channel-stats');

    // 测试图表
    $router->get('test-chart', 'TestChartController@index')->name('test-chart');

});
