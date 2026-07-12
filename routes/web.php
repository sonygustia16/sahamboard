<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StockFilterController;
use App\Http\Controllers\StockAnalysisController;
use App\Http\Controllers\EntryPlanController;
use App\Http\Controllers\LotSizingController;
use App\Http\Controllers\MoneyManagementController;
use App\Http\Controllers\WatchlistController;
use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SahamBoard (versi lengkap, 6 fitur, dengan persistence)
|--------------------------------------------------------------------------
*/

// Halaman login sengaja di luar EnsureAuthenticated (kalau tidak, bakal infinite redirect loop).
// Throttle ketat (10x/menit) untuk mencegah brute force password.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(EnsureAuthenticated::class)->group(function () {
    // Rate limit lebih longgar untuk halaman yang manggil Yahoo Finance API,
    // supaya tidak gampang disalahgunakan untuk hammer request ke server eksternal.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/', [StockFilterController::class, 'index'])->name('index');
        Route::get('/chart-data/{stockCode}', [StockFilterController::class, 'chartData'])->name('chart-data');
        Route::get('/analysis', [StockAnalysisController::class, 'index'])->name('analysis.index');
        Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    });

    Route::get('/entry', [EntryPlanController::class, 'index'])->name('entry.index');
    Route::post('/entry', [EntryPlanController::class, 'store'])->name('entry.store');
    Route::delete('/entry/{entryPlan}', [EntryPlanController::class, 'destroy'])->name('entry.destroy');

    Route::get('/lot-sizing', [LotSizingController::class, 'index'])->name('lotsizing.index');

    Route::get('/money-management', [MoneyManagementController::class, 'index'])->name('money-management.index');
    Route::post('/money-management', [MoneyManagementController::class, 'store'])->name('money-management.store');
    Route::post('/money-management/holding', [MoneyManagementController::class, 'storeHolding'])->name('money-management.holding.store');
    Route::delete('/money-management/holding/{holding}', [MoneyManagementController::class, 'destroyHolding'])->name('money-management.holding.destroy');

    Route::get('/watchlist/alerts-check', [WatchlistController::class, 'alertsCheck'])->name('watchlist.alerts-check');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::delete('/watchlist/{watchlist}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');
});

