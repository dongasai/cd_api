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

    // 模型倍率管理
    $router->resource('model-multipliers', 'ModelMultiplierController');

    // 渠道亲和性规则
    $router->resource('channel-affinity-rules', 'ChannelAffinityRuleController');

    // 日志中心 - 只读
    $router->resource('audit-logs', 'AuditLogController')->only(['index', 'show']);
    $router->resource('request-logs', 'RequestLogController')->only(['index', 'show']);
    $router->resource('response-logs', 'ResponseLogController')->only(['index', 'show']);
    $router->resource('channel-request-logs', 'ChannelRequestLogController')->only(['index', 'show']);
    $router->resource('operation-logs', 'OperationLogController')->only(['index', 'show']);

});
