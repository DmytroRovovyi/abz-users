<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

// User api.
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);

// Position api.
Route::get('/positions', [PositionController::class, 'index']);

// Token api.
Route::get('/token', [TokenController::class, 'getToken']);

