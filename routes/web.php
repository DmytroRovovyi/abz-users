<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserPageController;

Route::get('/', [UserPageController::class, 'index']);
Route::post('/users', [UserPageController::class, 'store'])->name('users.store');
