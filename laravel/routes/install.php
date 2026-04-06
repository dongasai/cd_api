<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpgradeController;
use Illuminate\Support\Facades\Route;

// 安装路由 - 不使用 web 中间件，避免 APP_KEY 缺失时报错
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');
    Route::get('/environment', [InstallController::class, 'environment'])->name('environment');
    Route::post('/check-environment', [InstallController::class, 'checkEnvironment'])->name('check_environment');
    Route::get('/config', [InstallController::class, 'config'])->name('config');
    Route::post('/test-connection', [InstallController::class, 'testDatabaseConnection'])->name('test_connection');
    Route::post('/save-config', [InstallController::class, 'saveConfig'])->name('save_config');
    Route::get('/database-check', [InstallController::class, 'databaseCheck'])->name('database_check');
    Route::post('/clean-database', [InstallController::class, 'cleanDatabase'])->name('clean_database');
    Route::get('/migrate', [InstallController::class, 'migrate'])->name('migrate');
    Route::post('/pending-migrations', [InstallController::class, 'getPendingMigrations'])->name('pending_migrations');
    Route::post('/migrate-one', [InstallController::class, 'migrateOne'])->name('migrate_one');
    Route::post('/execute-migrate', [InstallController::class, 'executeMigrate'])->name('execute_migrate');
    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/create-admin', [InstallController::class, 'createAdmin'])->name('create_admin');
    Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
    Route::post('/generate-key', [InstallController::class, 'generateKey'])->name('generate_key');
});

// 升级路由 - 不使用 web 中间件
Route::prefix('upgrade')->name('upgrade.')->group(function () {
    Route::get('/', [UpgradeController::class, 'index'])->name('index');
    Route::post('/execute', [UpgradeController::class, 'execute'])->name('execute');
});
