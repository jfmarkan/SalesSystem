<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\RegisterController as RegisterController;
use App\Http\Controllers\Auth\VerifyOtpController as VerifyOtpController;
use App\Http\Controllers\Auth\LoginController as LoginController;
use App\Http\Controllers\Auth\OtpController as OtpController;

use App\Http\Controllers\BudgetCaseController;
use App\Http\Controllers\BudgetCaseSimulatorController;
use App\Http\Controllers\CompanyAnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviationController;
use App\Http\Controllers\ExtraQuotaController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\UserDetailController;

Route::post('/verify-otp', [VerifyOtpController::class, 'verify']);
Route::post('/resend-otp', [OtpController::class, 'resend']);

// routes/api.php
Route::get('/analytics/debug-team-users', [CompanyAnalyticsController::class, 'debugTeamUsers']);

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

    Route::prefix('analytics')->group(function () {
        Route::get('/tree',   [CompanyAnalyticsController::class, 'tree']);
        Route::get('/totals', [CompanyAnalyticsController::class, 'totals']);
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
        Route::get('/assignments/my-profit-centers', [ExtraQuotaController::class, 'myProfitCenters']);
    Route::get('/assignments/my-volume', [ExtraQuotaController::class, 'myVolume']);
    Route::get('/assignments/my-availability', [ExtraQuotaController::class, 'myAvailability']);

    // Opportunities
    Route::get('/opportunities', [ExtraQuotaController::class, 'indexOpportunities']);
    Route::get('/opportunities/{groupId}', [ExtraQuotaController::class, 'showOpportunityGroup'])->whereNumber('groupId');
    Route::post('/opportunities', [ExtraQuotaController::class, 'createOpportunity']);
    Route::post('/opportunities/{groupId}/version', [ExtraQuotaController::class, 'createVersion'])->whereNumber('groupId');

    // Budget
    Route::get('/budget/{groupId}/{version}', [ExtraQuotaController::class, 'getBudget'])
        ->whereNumber('groupId')->whereNumber('version');
    Route::post('/budget/{groupId}/{version}/save', [ExtraQuotaController::class, 'saveBudget'])
        ->whereNumber('groupId')->whereNumber('version');

    // Forecast
    Route::get('/forecast/{groupId}/{version}', [ExtraQuotaController::class, 'getForecast'])
        ->whereNumber('groupId')->whereNumber('version');
    Route::post('/forecast/{groupId}/{version}/save', [ExtraQuotaController::class, 'saveForecast'])
        ->whereNumber('groupId')->whereNumber('version');

    // Seasonality
    Route::get('/profit-centers/seasonality', [ExtraQuotaController::class, 'seasonality']);

    // Finalize (won|lost)
    Route::post('/opportunities/{groupId}/{version}/finalize', [ExtraQuotaController::class, 'finalizeOpportunity'])
        ->whereNumber('groupId')->whereNumber('version');
    });
});
