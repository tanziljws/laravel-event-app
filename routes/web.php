<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Laravel Event App API',
        'version' => '1.0.0',
        'status' => 'running',
        'message' => 'Welcome to Event App API',
        'endpoints' => [
            'api' => url('/api'),
            'events' => url('/api/events'),
            'banners' => url('/api/banners'),
            'admin_dashboard' => url('/api/admin/dashboard'),
        ],
        'documentation' => 'This is a REST API for Event Management System'
    ], 200, [], JSON_PRETTY_PRINT);
});
