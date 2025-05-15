<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\TokenController;

Route::prefix('v1')->group(function () {

    // User api.
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::match(['put', 'post'], '/users/{id}', [UserController::class, 'update']);

    // Position api.
    Route::get('/positions', [PositionController::class, 'index']);

    // Token api.
    Route::get('/token', [TokenController::class, 'getToken']);
});


