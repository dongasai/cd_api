<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Debug file viewer route
Route::get('/debug/file', function (Request $request) {
    if (! config('app.debug')) {
        abort(403, 'Debug mode is disabled');
    }

    $path = $request->query('path');

    if (! $path || ! is_file($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    // Security: only allow files within the project directory
    $basePath = realpath(base_path());
    $realPath = realpath($path);

    if ($realPath === false || ! str_starts_with($realPath, $basePath)) {
        return response()->json(['error' => 'Access denied'], 403);
    }

    try {
        $content = file_get_contents($realPath);
        $lines = explode("\n", $content);

        return response()->json(['lines' => $lines]);
    } catch (Exception $e) {
        return response()->json(['error' => 'Failed to read file'], 500);
    }
})->name('debug.file');
