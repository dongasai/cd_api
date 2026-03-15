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

    // 渠道亲和性缓存
    $router->resource('channel-affinity-cache', 'ChannelAffinityCacheController')->only(['index', 'show']);

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

    // 模型测试
    $router->get('model-test', 'ModelTestController@index')->name('model-test');
    $router->post('model-test/test', 'ModelTestController@test')->name('model-test.test');
    $router->get('model-test/channel-models/{channel_id}', 'ModelTestController@getChannelModels')->name('model-test.channel-models');
    $router->get('model-test/logs', 'ModelTestController@grid')->name('model-test.logs');

});
