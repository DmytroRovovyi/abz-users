<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PositionController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);

Route::get('/positions', [PositionController::class, 'index']);

