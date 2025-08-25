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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BudgetCaseController;
use App\Http\Controllers\BudgetCaseSimulatorController;
use App\Http\Controllers\ExtraQuotaController;

Route::post('/verify-otp', [VerifyOtpController::class, 'verify']);
Route::post('/resend-otp', [OtpController::class, 'resend']);

Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    Route::prefix('me')->group(function () {
        Route::get('/clients',        [ForecastController::class, 'getClients']);
        Route::get('/profit-centers', [ForecastController::class, 'getProfitCenters']);
        Route::get('/assignments',    [ForecastController::class, 'getAssignments']);
    });
    
    Route::prefix('forecast')->group(function () {
        Route::get('/series',  [ForecastController::class, 'getSeries']);
        Route::put('/series',  [ForecastController::class, 'saveSeries']);
        Route::get('/current-month-versions', [ForecastController::class, 'getCurrentMonthVersions']);
    });
    
    Route::prefix('budget-cases')->group(function () {
        Route::post('/',         [BudgetCaseController::class, 'store']);     // save/update
        Route::get('/',          [BudgetCaseController::class, 'show']);      // fetch one by clientPC+FY (query params)
        Route::post('/simulate', [BudgetCaseSimulatorController::class, 'simulate']);
        Route::post('/sales-ytd',[BudgetCaseSimulatorController::class, 'salesYtd']);
    });

    Route::prefix('deviations')->group(function () {
        Route::get('/',            [DeviationController::class, 'index']);
        Route::post('/run',        [DeviationController::class, 'runForMe']);
        Route::put('/{id}/justify',[DeviationController::class, 'justify']);
    });

    Route::prefix('users/{user}/details')->group(function () {
        Route::get   ('/', [UserDetailController::class, 'show']);
        Route::post  ('/', [UserDetailController::class, 'storeOrUpdate']);
        Route::delete('/', [UserDetailController::class, 'destroy']);
    });

    Route::prefix('extra-quota')->group(function () {
        // Available (CEO totals per FY & PC)
        Route::get   ('/available',        [ExtraQuotaController::class, 'availableIndex']);
        Route::post  ('/available',        [ExtraQuotaController::class, 'availableUpsert']);
        Route::delete('/available/{id}',   [ExtraQuotaController::class, 'availableDelete']);

        // Assignments (user allocations, publish lock)
        Route::get   ('/assignments',             [ExtraQuotaController::class, 'assignmentsIndex']);
        Route::post  ('/assignments',             [ExtraQuotaController::class, 'assignmentsStore']);
        Route::patch ('/assignments/{id}',        [ExtraQuotaController::class, 'assignmentsUpdate']);
        Route::post  ('/assignments/{id}/publish',[ExtraQuotaController::class, 'assignmentsPublish']);
        Route::delete('/assignments/{id}',        [ExtraQuotaController::class, 'assignmentsDelete']);

        // Sales Opportunities (versioned by group)
        Route::get  ('/opportunities',                 [ExtraQuotaController::class, 'opportunitiesIndex']);
        Route::post ('/opportunities',                 [ExtraQuotaController::class, 'opportunitiesStore']);
        Route::get  ('/opportunities/{group_id}',      [ExtraQuotaController::class, 'opportunityShowLatest']);
        Route::post ('/opportunities/{group_id}/version', [ExtraQuotaController::class, 'opportunityNewVersion']);

        // Budget
        Route::get ('/budget/{group_id}/{version}',      [ExtraQuotaController::class, 'budgetGrid']);
        Route::post('/budget/{group_id}/{version}/save', [ExtraQuotaController::class, 'budgetSave']);

        // Forecast
        Route::get ('/forecast/{group_id}/{version}',      [ExtraQuotaController::class, 'forecastGrid']);
        Route::post('/forecast/{group_id}/{version}/save', [ExtraQuotaController::class, 'forecastSave']);
    });
});
