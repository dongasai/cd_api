<?php

use App\Http\Controllers\Api\MockController;
use App\Http\Controllers\Api\ProxyController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateApiKey::class])->group(function () {
    Route::prefix('openai/v1')->group(function () {
        Route::post('/chat/completions', [ProxyController::class, 'chatCompletions']);
        Route::post('/completions', [ProxyController::class, 'completions']);
        Route::post('/embeddings', [ProxyController::class, 'embeddings']);
        Route::get('/models', [ProxyController::class, 'models']);

        // Responses API 端点
        Route::post('/responses', [ProxyController::class, 'openai_responses']);
    });

    Route::post('/anthropic/messages', [ProxyController::class, 'anthropicMessages']);
    Route::post('/anthropic/v1/messages', [ProxyController::class, 'anthropicMessages']);

    // Anthropic models endpoint
    Route::get('/anthropic/models', [ProxyController::class, 'anthropicModels']);
    Route::get('/anthropic/v1/models', [ProxyController::class, 'anthropicModels']);
});

// 伪 LLM Mock 端点（无需认证，返回固定内容）
Route::prefix('llm')->group(function () {
    // OpenAI 兼容
    Route::post('/openai/v1/chat/completions', [MockController::class, 'openaiChatCompletions']);
    Route::get('/openai/v1/models', [MockController::class, 'openaiModels']);

    // Anthropic 兼容
    Route::post('/anthropic/messages', [MockController::class, 'anthropicMessages']);
    Route::post('/anthropic/v1/messages', [MockController::class, 'anthropicMessages']);
    Route::get('/anthropic/models', [MockController::class, 'anthropicModels']);
    Route::get('/anthropic/v1/models', [MockController::class, 'anthropicModels']);
});
