<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ExcelImportController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PerformanceController;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]);
    });

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'updatePassword']);

        // Companies
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/companies/{company}', [CompanyController::class, 'show']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::put('/companies/{company}', [CompanyController::class, 'update']);
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);
        Route::get('/companies/{company}/stats', [CompanyController::class, 'stats']);

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/agents', [UserController::class, 'agents']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::put('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::put('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

        // Agent: my tasks
        Route::get('/my-tasks', [TaskController::class, 'myTasks']);
        Route::get('/my-tasks/{date}', [TaskController::class, 'myTasksByDate']);
        Route::get('/my-route', [TaskController::class, 'myRoute']);
        Route::post('/my-route/optimize', [TaskController::class, 'optimizeRoute']);
        Route::get('/my-dashboard/stats', [DashboardController::class, 'myStats']);

        // Tasks
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
        Route::post('/tasks/auto-assign', [TaskController::class, 'autoAssign']);
        Route::post('/tasks/bulk-assign', [TaskController::class, 'bulkAssign']);
        Route::post('/tasks/{task}/assign', [TaskController::class, 'assign']);
        Route::put('/tasks/{task}/start', [TaskController::class, 'start']);
        Route::put('/tasks/{task}/complete', [TaskController::class, 'complete']);
        Route::put('/tasks/{task}/fail', [TaskController::class, 'fail']);
        Route::put('/tasks/{task}/acknowledge', [TaskController::class, 'acknowledge']);
        Route::post('/tasks/{task}/evidence', [TaskController::class, 'addEvidence']);
        Route::get('/tasks/{task}/comments', [TaskController::class, 'comments']);
        Route::post('/tasks/{task}/comments', [TaskController::class, 'addComment']);

        // Clients
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::put('/clients/{client}', [ClientController::class, 'update']);
        Route::delete('/clients/{client}', [ClientController::class, 'destroy']);
        Route::put('/clients/{client}/status', [ClientController::class, 'updateStatus']);
        Route::post('/clients/bulk-assign', [ClientController::class, 'bulkAssign']);

        // Excel import
        Route::get('/excel-import', [ExcelImportController::class, 'index']);
        Route::get('/excel-import/{import}', [ExcelImportController::class, 'show']);
        Route::post('/excel-import', [ExcelImportController::class, 'import']);
        Route::post('/excel-import/{import}/process', [ExcelImportController::class, 'process']);
        Route::put('/excel-import/{import}', [ExcelImportController::class, 'update']);
        Route::delete('/excel-import/{import}', [ExcelImportController::class, 'destroy']);
        Route::get('/excel-import/template/download', [ExcelImportController::class, 'downloadTemplate']);

        // WhatsApp
        Route::post('/whatsapp/send-bulk', [WhatsAppController::class, 'sendBulk']);
        Route::post('/whatsapp/send-to-client', [WhatsAppController::class, 'sendToClient']);
        Route::get('/whatsapp/messages', [WhatsAppController::class, 'messages']);

        // Reports
        Route::get('/reports', [ReportController::class, 'index']);
        Route::post('/reports/generate', [ReportController::class, 'generate']);
        Route::get('/reports/{report}/download', [ReportController::class, 'download']);
        Route::delete('/reports/{report}', [ReportController::class, 'destroy']);
        Route::get('/reports/schedules', [ReportController::class, 'schedules']);
        Route::post('/reports/schedules', [ReportController::class, 'storeSchedule']);
        Route::put('/reports/schedules/{schedule}', [ReportController::class, 'updateSchedule']);
        Route::delete('/reports/schedules/{schedule}', [ReportController::class, 'destroySchedule']);

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/map-data', [DashboardController::class, 'mapData']);
        Route::get('/dashboard/agent-performance', [DashboardController::class, 'agentPerformance']);

        // Performance (daily metrics per user, reports)
        Route::get('/performance/daily', [PerformanceController::class, 'daily']);
        Route::get('/performance/my', [PerformanceController::class, 'mine']);
        Route::post('/performance/generate', [PerformanceController::class, 'generate']);
        Route::get('/performance/my-reports', [PerformanceController::class, 'myReports']);
        Route::get('/performance/report/{report}/download', [PerformanceController::class, 'download'])->name('performance.report.download');
    });
});