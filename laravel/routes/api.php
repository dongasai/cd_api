<?php

use App\Http\Controllers\Api\ProxyController;
use Illuminate\Support\Facades\Route;

Route::prefix('openai/v1')->group(function () {

    Route::post('/chat/completions', [ProxyController::class, 'chatCompletions']);
    Route::post('/completions', [ProxyController::class, 'completions']);
    Route::post('/embeddings', [ProxyController::class, 'embeddings']);
    Route::get('/models', [ProxyController::class, 'models']);
});

Route::post('/anthropic/messages', [ProxyController::class, 'anthropicMessages']);
Route::post('/anthropic/v1/messages', [ProxyController::class, 'anthropicMessages']);
