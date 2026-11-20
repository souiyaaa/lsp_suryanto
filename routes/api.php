<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'success' => true,
    'message' => 'API is healthy',
    'data' => ['status' => 'ok', 'timestamp' => now()->toIso8601String()],
]));

