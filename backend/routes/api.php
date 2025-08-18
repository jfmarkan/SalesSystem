<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\RegisterController as RegisterController;
use App\Http\Controllers\Auth\VerifyOtpController as VerifyOtpController;
use App\Http\Controllers\Auth\LoginController as LoginController;
use App\Http\Controllers\Auth\OtpController as OtpController;

use App\Http\Controllers\DeviationController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\UserDetailController;

Route::post('/verify-otp', [VerifyOtpController::class, 'verify']);
Route::post('/resend-otp', [OtpController::class, 'resend']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('me')->group(function () {
        Route::get('/clients',        [ForecastController::class, 'getClients']);
        Route::get('/profit-centers', [ForecastController::class, 'getProfitCenters']);
        Route::get('/assignments',    [ForecastController::class, 'getAssignments']);
    });

    Route::get('/forecast/series',  [ForecastController::class, 'getSeries']);
    Route::put('/forecast/series',  [ForecastController::class, 'saveSeries']);
    Route::get('/forecast/current-month-versions', [ForecastController::class, 'getCurrentMonthVersions']);

    Route::get('/deviations', [DeviationController::class, 'index']);
    Route::post('/deviations/run', [DeviationController::class, 'runForMe']);
    Route::put('/deviations/{id}/justify', [DeviationController::class, 'justify']);

    Route::get   ('users/{user}/details', [UserDetailController::class, 'show']);
    Route::post  ('users/{user}/details', [UserDetailController::class, 'storeOrUpdate']);
    Route::delete('users/{user}/details', [UserDetailController::class, 'destroy']);
});