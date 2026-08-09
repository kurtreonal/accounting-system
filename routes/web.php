<?php

use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\ChartOfAccountsExportController;
use App\Http\Controllers\DemoAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [DemoAuthController::class, 'show'])->name('login');
Route::post('/login', [DemoAuthController::class, 'login'])->name('login.attempt');
Route::get('/dashboard', [DemoAuthController::class, 'dashboard'])->name('dashboard');
Route::get('/chart-of-accounts', [DemoAuthController::class, 'chartOfAccounts'])->name('chart-of-accounts');
Route::post('/chart-of-accounts', [ChartOfAccountsController::class, 'store'])->name('chart-of-accounts.store');
Route::put('/chart-of-accounts/{code}', [ChartOfAccountsController::class, 'update'])->name('chart-of-accounts.update');
Route::patch('/chart-of-accounts/{code}/status', [ChartOfAccountsController::class, 'status'])->name('chart-of-accounts.status');
Route::delete('/chart-of-accounts/{code}', [ChartOfAccountsController::class, 'destroy'])->name('chart-of-accounts.destroy');
Route::get('/journal-entries', [DemoAuthController::class, 'journalEntries'])->name('journal-entries');
Route::get('/chart-of-accounts/export/pdf', [ChartOfAccountsExportController::class, 'pdf'])->name('chart-of-accounts.export.pdf');
Route::get('/chart-of-accounts/export/csv', [ChartOfAccountsExportController::class, 'csv'])->name('chart-of-accounts.export.csv');
Route::post('/logout', [DemoAuthController::class, 'logout'])->name('logout');
