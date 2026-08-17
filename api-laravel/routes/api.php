<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CrudController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MovementController;
use App\Http\Controllers\Api\V1\PresenceController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('dashboard', DashboardController::class);
        Route::get('presence/{kind}', [PresenceController::class, 'presence']);
        Route::get('audit', [PresenceController::class, 'audit'])->middleware('permission:audit.view');
        Route::post('sync', [MovementController::class, 'sync'])->middleware('permission:movements.create');
        Route::get('movements/{kind}', [MovementController::class, 'index']);
        Route::post('movements/{kind}/{id}/correct', [MovementController::class, 'correct'])->middleware('permission:movements.correct');
        Route::post('movements/{kind}', [MovementController::class, 'store'])->middleware('permission:movements.create');
        Route::get('reports/pdf', [ReportController::class, 'pdf'])->middleware('permission:reports.view');
        Route::get('reports/xlsx', [ReportController::class, 'xlsx'])->middleware('permission:reports.view');
        Route::get('{resource}', [CrudController::class, 'index'])->whereIn('resource', ['people', 'vehicles', 'categories', 'departments', 'locations']);
        Route::post('{resource}', [CrudController::class, 'store'])->middleware('permission:registrations.manage')->whereIn('resource', ['people', 'vehicles', 'categories', 'departments', 'locations']);
        Route::get('{resource}/{id}', [CrudController::class, 'show'])->whereIn('resource', ['people', 'vehicles', 'categories', 'departments', 'locations']);
        Route::put('{resource}/{id}', [CrudController::class, 'update'])->middleware('permission:registrations.manage')->whereIn('resource', ['people', 'vehicles', 'categories', 'departments', 'locations']);
    });
});
