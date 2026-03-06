<?php

use App\Http\Controllers\Api\ChannelCodingStatusController;
use App\Http\Controllers\Api\CodingAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API 路由
Route::prefix('api/v1')->group(function () {
    // Coding账户管理
    Route::get('/coding-accounts', [CodingAccountController::class, 'index']);
    Route::post('/coding-accounts', [CodingAccountController::class, 'store']);
    Route::get('/coding-accounts/platforms', [CodingAccountController::class, 'platforms']);
    Route::get('/coding-accounts/statuses', [CodingAccountController::class, 'statuses']);
    Route::get('/coding-accounts/drivers', [CodingAccountController::class, 'drivers']);
    Route::get('/coding-accounts/{id}', [CodingAccountController::class, 'show']);
    Route::put('/coding-accounts/{id}', [CodingAccountController::class, 'update']);
    Route::delete('/coding-accounts/{id}', [CodingAccountController::class, 'destroy']);
    Route::post('/coding-accounts/{id}/sync', [CodingAccountController::class, 'sync']);
    Route::post('/coding-accounts/{id}/validate', [CodingAccountController::class, 'validateCredentials']);
    Route::get('/coding-accounts/{id}/quota', [CodingAccountController::class, 'quota']);
    Route::get('/coding-accounts/{id}/usage', [CodingAccountController::class, 'usage']);
    Route::get('/coding-accounts/{id}/logs', [CodingAccountController::class, 'logs']);

    // 渠道Coding状态管理
    Route::get('/channels/{id}/coding-status', [ChannelCodingStatusController::class, 'show']);
    Route::post('/channels/{id}/coding-status', [ChannelCodingStatusController::class, 'update']);
    Route::post('/channels/{id}/disable', [ChannelCodingStatusController::class, 'disable']);
    Route::post('/channels/{id}/enable', [ChannelCodingStatusController::class, 'enable']);
    Route::post('/channels/{id}/check-quota', [ChannelCodingStatusController::class, 'checkQuota']);
    Route::post('/channels/batch-check', [ChannelCodingStatusController::class, 'batchCheck']);
});
