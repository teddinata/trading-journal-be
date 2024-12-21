<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TradingJournalController;
use App\Http\Controllers\Api\TradingStatsController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // Trading Position Routes
        Route::prefix('trading')->group(function () {
            Route::get('positions', [TradingJournalController::class, 'index']);
            Route::post('positions', [TradingJournalController::class, 'store']);
            Route::get('positions/{position}', [TradingJournalController::class, 'show']);
            Route::post('positions/{position}/transaction', [TradingJournalController::class, 'update']);
            Route::delete('positions/{position}', [TradingJournalController::class, 'destroy']);

            // Stats Routes
            Route::get('stats/summary', [TradingStatsController::class, 'summary']);
        });

        // // Trading Journal Routes
        // Route::prefix('trading-journals')->group(function () {
        //     Route::get('/', [TradingJournalController::class, 'index']);
        //     Route::post('/', [TradingJournalController::class, 'store']);
        //     Route::get('{tradingJournal}', [TradingJournalController::class, 'show']);
        //     Route::put('{tradingJournal}', [TradingJournalController::class, 'update']);
        //     Route::delete('{tradingJournal}', [TradingJournalController::class, 'destroy']);
        // });

        // Trading Stats Routes
        // Route::prefix('trading-stats')->group(function () {
        //     Route::get('summary', [TradingStatsController::class, 'summary']);
        //     Route::get('daily', [TradingStatsController::class, 'dailyStats']);
        //     Route::get('strategies', [TradingStatsController::class, 'strategyAnalysis']);
        // });
    });
});
