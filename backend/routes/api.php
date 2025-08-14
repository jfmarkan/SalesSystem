<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\RegisterController as RegisterController;
use App\Http\Controllers\Auth\VerifyOtpController as VerifyOtpController;
use App\Http\Controllers\Auth\LoginController as LoginController;
use App\Http\Controllers\Auth\OtpController as OtpController;

use App\Http\Controllers\Dashboard\RadarController as DashboardRadarController;

use App\Http\Controllers\ForecastController;

Route::post('/verify-otp', [VerifyOtpController::class, 'verify']);
Route::post('/resend-otp', [OtpController::class, 'resend']);

// Usuario autenticado (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get('/me/clients', [ForecastController::class, 'getClients']);
    Route::get('/me/profit-centers', [ForecastController::class, 'getProfitCenters']);
    Route::get('/me/assignments', [ForecastController::class, 'getAssignments']);
    Route::get('/forecast/selector-options', [ForecastController::class, 'selectorOptions']);

    Route::get('/forecast/list', [ForecastController::class, 'forecastList']);
    Route::get('/forecast/detail/{assignmentId}', [ForecastController::class, 'detail']);
    Route::post('/forecast/save/{assignmentId}', [ForecastController::class, 'save']);
    Route::get('/forecast/summary/{assignmentId}', [ForecastController::class, 'summary']);
    Route::get('/forecast/monthly-evolution/{assignmentId}', [ForecastController::class, 'monthlyEvolution']);
    Route::get('/forecast/version-history/{assignmentId}', [ForecastController::class, 'versionHistory']);

    #Route::get('/radar', [DashboardRadarController::class, 'radar']);
    #Route::get('/radar-table', [DashboardRadarController::class, 'radarTable']);
});