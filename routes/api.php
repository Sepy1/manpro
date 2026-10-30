<?php

use App\Http\Controllers\Api\ExternCrBoardController;
use App\Http\Controllers\Api\BackupMonitoringEventController;
use App\Http\Controllers\Api\PcInventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['backup-monitoring.api-key', 'throttle:60,1'])->prefix('backup-monitoring')->group(function () {
    Route::post('/events', [BackupMonitoringEventController::class, 'store']);
});

Route::middleware(['inventory.api-key', 'throttle:60,1'])->prefix('inventory')->group(function () {
    Route::get('/offices', [PcInventoryController::class, 'offices']);
    Route::post('/', [PcInventoryController::class, 'store']);
});

Route::middleware(['extern-cr.api-key'])->prefix('cr-eksternal')->group(function () {
    Route::get('/dashboard', [ExternCrBoardController::class, 'index']);
    Route::get('/{externCr}', [ExternCrBoardController::class, 'show']);
    Route::patch('/{externCr}/status', [ExternCrBoardController::class, 'updateStatus']);
});
