<?php

use App\Http\Controllers\DemoAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [DemoAuthController::class, 'show'])->name('login');
Route::post('/login', [DemoAuthController::class, 'login'])->name('login.attempt');
Route::get('/dashboard', [DemoAuthController::class, 'dashboard'])->name('dashboard');
Route::post('/logout', [DemoAuthController::class, 'logout'])->name('logout');
