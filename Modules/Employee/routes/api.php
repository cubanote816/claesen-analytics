<?php

use Illuminate\Support\Facades\Route;
use Modules\Employee\App\Http\Controllers\EmployeeDashboardController;
use Modules\Employee\App\Http\Controllers\EmployeeModuleController;
use Modules\Employee\App\Http\Controllers\EmployeeStatsController;
use Modules\Employee\App\Http\Controllers\EmployeeTimeController;
use Modules\Employee\App\Http\Controllers\ProjectController;
use Modules\Employee\App\Http\Controllers\ProjectInvoiceController;
use Modules\Employee\App\Http\Controllers\WorkHoursAnalyticsController;

Route::group(['prefix' => 'v1', 'middleware' => ['api']], function () {

    Route::group(['prefix' => 'employees', 'middleware' => ['auth:sanctum']], function () {

        Route::get('/',          [EmployeeModuleController::class,   'getAllEmployees']);
        Route::get('/rankings',  [EmployeeDashboardController::class, 'getEmployeeRankings']);
        Route::get('/dashboard', [EmployeeDashboardController::class, 'getDashboardData'])->name('employees.dashboard');

        // Stats per period
        Route::get('/{employeeId}/stats/{periodType}', [EmployeeStatsController::class, 'getPeriodStats'])
            ->where(['periodType' => 'current-week|previous-week|current-month|previous-month']);

        // Time routes
        Route::get('/{employeeId}',                                  [EmployeeTimeController::class, 'getTimeStats']);
        Route::get('/{employeeId}/time/month/{yearMonth}/weeks',     [EmployeeTimeController::class, 'getMonthWeeks']);

        Route::prefix('/{employeeId}/time')->group(function () {
            Route::get('stats',                    [EmployeeTimeController::class, 'getTimeStats'])->name('employee.time.stats');
            Route::get('day/{date}',               [EmployeeTimeController::class, 'getDayStats'])->name('employee.time.day');
            Route::get('week/{startDate}/{endDate?}', [EmployeeTimeController::class, 'getWeekStats'])->name('employee.time.week');
        });

        // Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard',              [WorkHoursAnalyticsController::class, 'getDashboardData']);
            Route::get('/projects/efficiency',    [WorkHoursAnalyticsController::class, 'getProjectEfficiency']);
            Route::get('/projects/productivity',  [WorkHoursAnalyticsController::class, 'getEmployeeProjectProductivity']);
            Route::get('/schedule/compliance',    [WorkHoursAnalyticsController::class, 'getScheduleCompliance']);
        });

        // Projects
        Route::prefix('projects')->group(function () {
            Route::get('/with-worked-hours',              [ProjectController::class, 'getProjectsWithWorkedHours']);
            Route::get('/active',                         [ProjectInvoiceController::class, 'getActiveProjects']);
            Route::get('/pending-invoices',               [ProjectInvoiceController::class, 'getPendingInvoices']);
            Route::get('/active-with-pending',            [ProjectInvoiceController::class, 'getActiveProjectsWithPendingInvoices']);
            Route::get('/{projectId}/basic-details',      [ProjectController::class, 'getProjectBasicDetails']);
            Route::get('/{projectId}/workers',            [ProjectController::class, 'getProjectWithWorkers']);
            Route::get('/{projectId}/details-with-workers', [ProjectController::class, 'getProjectDetailsWithWorkers']);
        });
    });
});
