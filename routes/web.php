<?php

use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\ChartOfAccountsExportController;
use App\Http\Controllers\DemoAuthController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\JournalEntryController;
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
Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries');
Route::get('/journal-entries/export/csv', [JournalEntryController::class, 'csv'])->name('journal-entries.export.csv');
Route::get('/journal-entries/{journalNumber}/print', [JournalEntryController::class, 'print'])->name('journal-entries.print');
Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
Route::put('/journal-entries/{journalNumber}', [JournalEntryController::class, 'update'])->name('journal-entries.update');
Route::delete('/journal-entries/{journalNumber}', [JournalEntryController::class, 'destroy'])->name('journal-entries.destroy');
Route::post('/journal-entries/{journalNumber}/submit-review', [JournalEntryController::class, 'submitForReview'])->name('journal-entries.submit-review');
Route::post('/journal-entries/{journalNumber}/return-draft', [JournalEntryController::class, 'returnToDraft'])->name('journal-entries.return-draft');
Route::post('/journal-entries/{journalNumber}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
Route::post('/journal-entries/{journalNumber}/reverse', [JournalEntryController::class, 'reverse'])->name('journal-entries.reverse');
Route::get('/general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger');
Route::get('/general-ledger/data', [GeneralLedgerController::class, 'data'])->name('general-ledger.data');
Route::get('/general-ledger/export/csv', [GeneralLedgerController::class, 'csv'])->name('general-ledger.export.csv');
Route::get('/chart-of-accounts/export/pdf', [ChartOfAccountsExportController::class, 'pdf'])->name('chart-of-accounts.export.pdf');
Route::get('/chart-of-accounts/export/csv', [ChartOfAccountsExportController::class, 'csv'])->name('chart-of-accounts.export.csv');
Route::post('/logout', [DemoAuthController::class, 'logout'])->name('logout');
