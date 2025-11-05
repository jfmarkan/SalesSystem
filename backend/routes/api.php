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
use App\Http\Controllers\UserAdministrationController;
use App\Http\Controllers\UserDetailController;
use App\Http\Controllers\ErpImportController;
use App\Http\Controllers\DeviationDetectController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ActionItemController;

use App\Http\Controllers\Admin\UsersController as UserAdminController;
use App\Http\Controllers\Admin\SessionAdminController as SessionAdminController;
use App\Http\Controllers\Admin\ClientsController as ClientAdminController;
use App\Http\Controllers\Admin\ProfitCentersController as ProfitCenterAdminController;
use App\Http\Controllers\Admin\ToolsController as ToolsAdminController;
use App\Http\Controllers\Admin\KpiController;
use App\Http\Controllers\Admin\ProgressController;
use App\Http\Controllers\Admin\ClientStatsController;
use App\Http\Controllers\BudgetGenerationController;


Route::post('/verify-otp', [VerifyOtpController::class, 'verify']);
Route::post('/resend-otp', [OtpController::class, 'resend']);

Route::get('/deviations/detect', [DeviationDetectController::class, 'detect']);
Route::get('/erp/auto-sales-update', [ErpImportController::class, 'auto']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/budgets/generate', [BudgetGenerationController::class, 'generate']);
    Route::post('/erp/manual-sales-import', [ErpImportController::class, 'manual']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/calendar/events', [CalendarController::class, 'events']);
    Route::patch('/action-items/{action_item}', [ActionItemController::class, 'update']);
    Route::get('/unit-conversions/by-pc', [DashboardController::class, 'unitsByPc']);
    Route::get('/extra/portfolio', [ExtraQuotaController::class, 'portfolio']);
    Route::get('/profit-centers/{code}/extra-portfolio', [ExtraQuotaController::class, 'pcPortfolio']);

    Route::prefix('me')->group(function () {
        Route::get('/clients',        [ForecastController::class, 'getClients']);
        Route::get('/profit-centers', [ForecastController::class, 'getProfitCenters']);
        Route::get('/assignments',    [ForecastController::class, 'getAssignments']);
    });

    Route::prefix('forecast')->group(function () {
        Route::get('/series',  [ForecastController::class, 'getSeries']);
        Route::put('/series',  [ForecastController::class, 'saveSeries']);
        Route::get('/series-table', [ForecastController::class, 'getSeriesTable']);
        Route::put('/series-table', [ForecastController::class, 'saveSeriesTable']);
        Route::get('/current-month-versions', [ForecastController::class, 'getCurrentMonthVersions']);
    });

    Route::prefix('sales-force')->group(function () {
        Route::get('/users',        [UserAdministrationController::class, 'index']);
        Route::get('/roles',        [UserAdministrationController::class, 'roles']);
        Route::get('/teams',        [UserAdministrationController::class, 'teams']);
        Route::get('/clients',      [UserAdministrationController::class, 'clients']); // ?userId=

        Route::patch('/users/{id}/block',  [UserAdministrationController::class, 'block']);
        Route::patch('/users/{id}/roles',  [UserAdministrationController::class, 'updateRole']);
        Route::patch('/users/{id}/teams',  [UserAdministrationController::class, 'updateTeams']);

        Route::post('/users/{id}/transfer', [UserAdministrationController::class, 'transfer']);
        Route::get('/users/{id}/logs',       [UserAdministrationController::class, 'logs']); // opcional
    });

    Route::prefix('analytics')->group(function () {
        Route::get('/tree',   [CompanyAnalyticsController::class, 'tree']);
        Route::get('/totals', [CompanyAnalyticsController::class, 'totals']);
        Route::get('/series', [CompanyAnalyticsController::class, 'series']);
        Route::get('/pc/overview', [CompanyAnalyticsController::class, 'pcOverview']);
        Route::get('/pc/list',     [CompanyAnalyticsController::class, 'pcList']);
    });

    Route::prefix('budget-cases')->group(function () {
        Route::post('/',         [BudgetCaseController::class, 'store']);     // save/update
        Route::get('/',          [BudgetCaseController::class, 'index']);
        Route::get('/exists', [BudgetCaseController::class, 'exists']);
        Route::post('/simulate', [BudgetCaseSimulatorController::class, 'simulate']);
        Route::post('/sales-ytd',[BudgetCaseSimulatorController::class, 'salesYtd']);
    });

    Route::prefix('deviations')->group(function () {
        Route::get('/',            [DeviationController::class, 'index']);
        Route::get('/by-user-month', [DeviationController::class, 'byUserAndMonth']);
        Route::put('/{id}/justify',[DeviationController::class, 'justify']);
    });

    Route::prefix('users/{user}')->group(function () {
        Route::get      ('/details', [UserDetailController::class, 'show']);
        Route::post     ('/details', [UserDetailController::class, 'storeOrUpdate']);
        Route::delete   ('/details', [UserDetailController::class, 'destroy']);
        Route::put      ('/password',   [UserDetailController::class, 'updatePassword']);
    });

    Route::prefix('extra-quota')->group(function () {
        Route::get      ('/analysis/summary', [ExtraQuotaController::class, 'analysisSummary']);
        Route::get      ('/assignments/my-profit-centers', [ExtraQuotaController::class, 'myProfitCenters']);
        Route::get      ('/assignments/my-volume', [ExtraQuotaController::class, 'myVolume']);
        Route::get      ('/assignments/my-availability', [ExtraQuotaController::class, 'myAvailability']);
        Route::get      ('/user/{userId}', [ExtraQuotaController::class, 'listByUser']);
        Route::get      ('/user/{userId}/all', [ExtraQuotaController::class, 'listAllByUserFY']);
        Route::patch    ('/{id}', [ExtraQuotaController::class, 'updateAssignmentVolume']);
        Route::post     ('/assign', [ExtraQuotaController::class, 'upsertAssignment']);
// Opportunities
        Route::get      ('/opportunities', [ExtraQuotaController::class, 'indexOpportunities']);
        Route::get      ('/opportunities/{groupId}', [ExtraQuotaController::class, 'showOpportunityGroup'])->whereNumber('groupId');
        Route::post     ('/opportunities', [ExtraQuotaController::class, 'createOpportunity']);
        Route::post     ('/opportunities/{groupId}/version', [ExtraQuotaController::class, 'createVersion'])->whereNumber('groupId');
// Budget
        Route::get      ('/budget/{groupId}/{version}', [ExtraQuotaController::class, 'getBudget'])->whereNumber('groupId')->whereNumber('version');
        Route::post     ('/budget/{groupId}/{version}/save', [ExtraQuotaController::class, 'saveBudget'])->whereNumber('groupId')->whereNumber('version');
// Forecast
        Route::get      ('/forecast/{groupId}/{version}', [ExtraQuotaController::class, 'getForecast'])->whereNumber('groupId')->whereNumber('version');
        Route::post     ('/forecast/{groupId}/{version}/save', [ExtraQuotaController::class, 'saveForecast'])->whereNumber('groupId')->whereNumber('version');
// Seasonality
        Route::get      ('/profit-centers/seasonality', [ExtraQuotaController::class, 'seasonality']);
// Finalize (won|lost)
        Route::post     ('/opportunities/{groupId}/{version}/finalize', [ExtraQuotaController::class, 'finalizeOpportunity'])->whereNumber('groupId')->whereNumber('version');
// ===== Checks y utilidades Extra-Quota =====
        Route::get      ('/clients', [ExtraQuotaController::class, 'listClients']);
        Route::get      ('/clients/by-number/{num}', [ExtraQuotaController::class, 'getClientByNumber'])->whereNumber('num');
        Route::get      ('/clients/{cgn}/profit-centers', [ExtraQuotaController::class, 'clientProfitCenters'])->whereNumber('cgn');
        Route::get      ('/clients/exists-in-pc', [ExtraQuotaController::class, 'clientExistsInPc']);
        Route::post     ('/budget/merge', [ExtraQuotaController::class, 'mergeBudget']);
        Route::post     ('/forecast/merge',       [ExtraQuotaController::class, 'mergeForecast']);
    });

    Route::prefix('settings')->group(function () {

        // Users
        Route::get('/users', [UserAdminController::class, 'index']);
        Route::post('/users', [UserAdminController::class, 'store']);
        Route::post('/users/{id}/block', [UserAdminController::class, 'block']);
        Route::post('/users/{id}/unblock', [UserAdminController::class, 'unblock']);
        Route::post('/users/{id}/kick', [UserAdminController::class, 'kick']);

        // Sessions / online
        Route::get('/sessions/online', [SessionAdminController::class, 'online']);
        Route::delete('/sessions/by-user/{id}', [SessionAdminController::class, 'destroyByUser']);

        // Clients
        Route::get('/clients/summary', [ClientAdminController::class, 'summary']);
        Route::get('/clients/{clientGroup}/pcs', [ClientAdminController::class, 'pcs']);
        Route::put('/clients/{clientGroup}', [ClientAdminController::class, 'update']);
        Route::post('/clients/{clientGroup}/pcs', [ClientAdminController::class, 'setPcs']);
        Route::post('/clients/{clientGroup}/assign-user', [ClientAdminController::class, 'assignUser']);
        Route::post('/clients/{clientGroup}/block', [ClientAdminController::class, 'block']);
        Route::delete('/clients/{clientGroup}', [ClientAdminController::class, 'destroy']);

        // Profit centers
        Route::get('/profit-centers/summary', [ProfitCenterAdminController::class, 'summary']);
        Route::put('/profit-centers/{code}', [ProfitCenterAdminController::class, 'update']);
        Route::put('/profit-centers/{code}/conversion', [ProfitCenterAdminController::class, 'updateConversion']);
        Route::put('/profit-centers/{code}/seasonality', [ProfitCenterAdminController::class, 'updateSeasonality']);

        // Tools / flags
        Route::post('/tools/rebuild-sales', [ToolsAdminController::class, 'rebuildSales']);
        Route::post('/tools/generate-budget', [ToolsAdminController::class, 'generateBudget']);
        Route::post('/tools/clients-update', [ToolsAdminController::class, 'clientsUpdate']);
        Route::post('/flags/budget-season', [ToolsAdminController::class, 'setBudgetSeason']);

        // KPIs and Progress
        Route::get('/kpis/summary', [KpiController::class, 'index']);
        Route::get('/progress/summary', [ProgressController::class, 'index']);
        Route::get('/clients/{clientGroup}/stats', [ClientStatsController::class, 'show']);
    });
});
