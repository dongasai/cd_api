<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 语言切换路由
Route::get('/admin/locale/{locale}', [LocaleController::class, '__invoke'])
    ->name('filament.admin.locale');
