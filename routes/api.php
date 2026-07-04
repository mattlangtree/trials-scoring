<?php

use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\ScoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/events/{event}')->whereNumber('event')->group(function () {
    Route::get('/', [EventController::class, 'show']);

    Route::post('/observer/claims', [ClaimController::class, 'store']);

    Route::post('/scores', [ScoreController::class, 'store']);

    Route::get('/riders/{riderNumber}/progress', [RiderController::class, 'progress'])
        ->whereNumber('riderNumber');
    Route::get('/riders/{riderNumber}', [RiderController::class, 'show'])
        ->whereNumber('riderNumber');
});
