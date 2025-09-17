<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\UserInvitationController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [SessionController::class, 'login']);
Route::post('/logout', [SessionController::class, 'logout']);

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/invite-user', [UserInvitationController::class, 'invite']);
Route::get('/invitation/{token}', [UserInvitationController::class, 'validateToken']);
Route::post('/finish-registration/{token}', [UserInvitationController::class, 'completeRegistration']);

Route::get('/mssql-test', function () {
    try {
        DB::connection('sqlsrv_stbspot')->getPdo();
        return '✅ Conectado a SQL Server (sqlsrv)';
    } catch (\Throwable $e) {
        return '❌ Error: ' . $e->getMessage();
    }
});