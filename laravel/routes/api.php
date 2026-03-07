<?php

use App\Http\Controllers\Api\ProxyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/openai')->group(function () {

    Route::post('/chat/completions', [ProxyController::class, 'chatCompletions']);
    Route::post('/completions', [ProxyController::class, 'completions']);
    Route::post('/embeddings', [ProxyController::class, 'embeddings']);
    Route::post('/models', [ProxyController::class, 'models']);
});

Route::post('/anthropic/messages', [ProxyController::class, 'anthropicMessages']);
