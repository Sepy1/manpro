<?php

use App\Http\Controllers\Api\ExternCrBoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['extern-cr.api-key'])->prefix('cr-eksternal')->group(function () {
    Route::get('/dashboard', [ExternCrBoardController::class, 'index']);
    Route::get('/{externCr}', [ExternCrBoardController::class, 'show']);
    Route::patch('/{externCr}/status', [ExternCrBoardController::class, 'updateStatus']);
});
